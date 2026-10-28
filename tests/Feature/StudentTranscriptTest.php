<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\TranscriptScoreOverride;
use App\Models\TranscriptState;
use App\Models\User;
use App\Models\VideoAttempt;
use App\Models\VideoLesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class StudentTranscriptTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private User $peer;
    private Classroom $classroom;
    private Quiz $quiz;
    private Quiz $draft;
    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->create(['role' => 'guru']);
        $this->student = User::factory()->create(['role' => 'siswa', 'name' => 'Siswa Pemilik', 'nomor_induk' => '001234']);
        $this->peer = User::factory()->create(['role' => 'siswa', 'name' => 'Identitas Teman Rahasia', 'nomor_induk' => 'NIS-RAHASIA']);
        $this->classroom = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'Kelas transkrip siswa']);
        $this->classroom->students()->attach([$this->student->id, $this->peer->id]);
        foreach (['Tugas dinilai' => 80, 'Tugas nilai nol' => 0, 'Tugas belum dikumpulkan' => null] as $title => $score) {
            $assignment = Assignment::create(['classroom_id' => $this->classroom->id, 'title' => $title, 'points' => 100]);
            if ($score !== null) {
                $submission = Submission::create(['assignment_id' => $assignment->id, 'user_id' => $this->student->id, 'score' => $score, 'submitted_at' => now()]);
                if ($score === 80) $this->submission = $submission;
            }
        }
        $this->quiz = $this->makeQuiz('Kuis dengan penalti', true, 35, true);
        $this->makeQuiz('Kuis menunggu esai', true, 10, false);
        $this->draft = $this->makeQuiz('Draf kuis rahasia', false, 50, true);
        foreach ([true, false] as $published) {
            $lesson = VideoLesson::create([
                'classroom_id' => $this->classroom->id, 'creator_id' => $this->teacher->id,
                'title' => $published ? 'Video dinilai' : 'Draf video rahasia',
                'source_type' => 'url', 'source_url' => 'https://example.test/video.mp4', 'is_published' => $published,
            ]);
            VideoAttempt::create(['video_lesson_id' => $lesson->id, 'user_id' => $this->student->id, 'score' => 90, 'completed_at' => now()]);
        }
    }

    private function makeQuiz(string $title, bool $published, float $score, bool $graded): Quiz
    {
        $quiz = Quiz::create(['classroom_id' => $this->classroom->id, 'title' => $title, 'is_published' => $published]);
        $quiz->questions()->create(['type' => Question::TYPE_PG, 'body' => 'Pertanyaan', 'option_a' => 'A', 'option_b' => 'B', 'correct_option' => 'a', 'points' => 50]);
        QuizAttempt::create([
            'quiz_id' => $quiz->id, 'user_id' => $this->student->id, 'started_at' => now()->subMinute(),
            'finished_at' => now(), 'score' => $score, 'raw_score' => 40, 'penalty_score' => 5, 'is_graded' => $graded,
        ]);
        return $quiz;
    }

    public function test_student_sees_only_own_final_scores_in_a_recap_table(): void
    {
        $this->actingAs($this->teacher)->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])->assertRedirect();
        $response = $this->actingAs($this->student)->get(route('student.transcripts.index', [
            'classroom' => $this->classroom->id, 'student_id' => $this->peer->id,
        ]))->assertOk()->assertSee('Transkrip Saya')->assertSee('Rekap nilai seluruh kelas')
            ->assertSee('role="region"', false)->assertSee('<table', false)
            ->assertSee('Kuis dengan penalti')->assertSee('Nilai sementara')
            ->assertSee('Menunggu esai')->assertSee('Belum dikumpulkan')
            ->assertDontSee($this->peer->name)->assertDontSee($this->peer->nomor_induk)
            ->assertDontSee('Draf kuis rahasia')->assertDontSee('Draf video rahasia')
            ->assertDontSee('Koreksi nilai')->assertDontSee('Konfirmasi Final Validation');
        $entries = $response->viewData('entries');
        $this->assertCount(6, $entries);
        $this->assertSame($this->classroom->id, $entries[0]['classroom']->id);
        $this->assertSame(4, $response->viewData('completed'));
        $this->assertSame(60.0, $response->viewData('average'));
        $this->assertSame(7, substr_count($response->getContent(), '<tr '));
    }

    public function test_final_corrections_are_shared_without_exposing_internal_notes(): void
    {
        TranscriptScoreOverride::create([
            'classroom_id' => $this->classroom->id, 'user_id' => $this->student->id,
            'activity_type' => 'quiz', 'activity_id' => $this->quiz->id,
            'score' => 25, 'reason' => 'Catatan internal guru rahasia', 'updated_by' => $this->teacher->id,
        ]);
        $this->actingAs($this->teacher)->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])->assertRedirect();
        $response = $this->actingAs($this->student)->get(route('student.transcripts.index'))
            ->assertOk()->assertSee('Koreksi guru')->assertDontSee('Catatan internal guru rahasia');
        $entry = $response->viewData('entries')->first(fn ($entry) => ($entry['activity']['key'] ?? null) === 'quiz-'.$this->quiz->id);
        $this->assertSame(25.0, $entry['score']['score']);
        $this->assertSame(55.0, $response->viewData('average'));
        $this->assertArrayNotHasKey('reason', $entry['score']);
    }

    public function test_final_snapshot_keeps_scores_and_publication_visibility_frozen(): void
    {
        $this->actingAs($this->teacher)->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])->assertRedirect();
        $this->submission->update(['score' => 1]);
        $this->draft->update(['is_published' => true]);
        $this->quiz->delete();
        $response = $this->actingAs($this->student)->get(route('student.transcripts.index'))->assertOk()
            ->assertSee('Rekap nilai seluruh kelas')->assertSee('Kuis dengan penalti')
            ->assertDontSee('Draf kuis rahasia')->assertDontSee($this->peer->name);
        $entries = $response->viewData('entries');
        $this->assertSame(80.0, $entries->first(fn ($entry) => ($entry['activity']['key'] ?? null) === 'assignment-'.$this->submission->assignment_id)['score']['score']);
        $this->assertSame(35.0, $entries->first(fn ($entry) => ($entry['activity']['key'] ?? null) === 'quiz-'.$this->quiz->id)['score']['score']);
        $this->assertSame(60.0, $response->viewData('average'));
    }

    public function test_legacy_snapshot_hides_unpublished_activities(): void
    {
        $this->actingAs($this->teacher)->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])->assertRedirect();
        $state = TranscriptState::where('classroom_id', $this->classroom->id)->firstOrFail();
        $snapshot = $state->snapshot;
        foreach ($snapshot['activities'] as &$activity) unset($activity['student_visible']);
        unset($activity);
        $state->update(['snapshot' => $snapshot]);
        $response = $this->actingAs($this->student)->get(route('student.transcripts.index'))
            ->assertOk()->assertDontSee('Draf kuis rahasia')->assertDontSee('Draf video rahasia');
        $this->assertSame(60.0, $response->viewData('average'));
    }

    public function test_csv_and_excel_contain_only_the_authenticated_students_report(): void
    {
        $this->actingAs($this->teacher)->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])->assertRedirect();
        $this->actingAs($this->student);
        $csv = $this->get(route('student.transcripts.all.csv', ['student_id' => $this->peer->id]))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFsep=,\r\n", $csv);
        $this->assertStringContainsString($this->student->name, $csv);
        $this->assertStringContainsString('Menunggu esai', $csv);
        $this->assertStringContainsString($this->classroom->name, $csv);
        $this->assertStringNotContainsString($this->peer->name, $csv);
        $this->assertStringNotContainsString('Draf kuis rahasia', $csv);
        $this->assertStringNotContainsString('Draf video rahasia', $csv);

        $content = $this->get(route('student.transcripts.all.excel'))
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->streamedContent();
        $this->assertStringStartsWith('PK', $content);
        $path = tempnam(sys_get_temp_dir(), 'test-student-transcript-');
        file_put_contents($path, $content);
        $reader = new Reader;
        try {
            $reader->open($path);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) $rows[] = $row->toArray();
            }
            $text = json_encode($rows);
            $this->assertStringContainsString($this->student->name, $text);
            $this->assertStringContainsString('Menunggu esai', $text);
            $this->assertStringContainsString($this->classroom->name, $text);
            $this->assertStringContainsString('Persentase (%)', $text);
            $this->assertStringNotContainsString($this->peer->name, $text);
            $this->assertStringNotContainsString('Draf kuis rahasia', $text);
            $this->assertStringNotContainsString('Draf video rahasia', $text);
            $this->assertSame('001234', $rows[1][3]);
            $this->assertEquals(60, $rows[3][1]);
            $activityRow = collect($rows)->first(fn ($item) => ($item[4] ?? null) === 'Tugas dinilai');
            $this->assertNotNull($activityRow);
            $this->assertEquals(80, $activityRow[2]);
            $this->assertEquals(100, $activityRow[7]);
            $this->assertEquals(80, $activityRow[8]);
            $this->assertSame('Dinilai', $activityRow[3]);
        } finally {
            $reader->close();
            unlink($path);
        }
    }

    public function test_csv_escapes_formula_like_text(): void
    {
        $this->student->update(['name' => '=SUM(1,2)']);
        $this->submission->assignment->update(['title' => '  =HYPERLINK("bad")']);
        $this->actingAs($this->teacher)->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])->assertRedirect();
        $csv = $this->actingAs($this->student)->get(route('student.transcripts.all.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringContainsString("'  =HYPERLINK", $csv);
    }

    public function test_guest_wrong_roles_and_non_members_cannot_access_transcripts(): void
    {
        $globalUrls = [route('student.transcripts.index'), route('student.transcripts.all.csv'), route('student.transcripts.all.excel')];
        foreach ($globalUrls as $url) $this->get($url)->assertRedirect(route('login'));
        foreach ([$this->teacher, User::factory()->create(['role' => 'admin'])] as $user) {
            foreach ($globalUrls as $url) $this->actingAs($user)->get($url)->assertForbidden();
        }
        $outsider = User::factory()->create(['role' => 'siswa']);
        $this->actingAs($outsider)->get(route('student.transcripts.index'))->assertOk()
            ->assertSee('Belum ada kelas yang diikuti')->assertDontSee($this->classroom->name);
        $outsideCsv = $this->get(route('student.transcripts.all.csv'))->assertOk()->streamedContent();
        $this->assertStringNotContainsString($this->classroom->name, $outsideCsv);
        $this->classroom->students()->detach($this->student);
        $this->get(route('student.transcripts.index'))->assertOk()->assertDontSee($this->classroom->name);
        $this->assertStringNotContainsString($this->classroom->name,
            $this->get(route('student.transcripts.all.csv'))->assertOk()->streamedContent());
    }

    public function test_student_cannot_edit_finalize_or_download_teacher_report(): void
    {
        $this->actingAs($this->student)->put(route('transcripts.scores.update', $this->classroom), [])->assertForbidden();
        $this->delete(route('transcripts.scores.clear', $this->classroom))->assertForbidden();
        $this->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])->assertForbidden();
        $this->get(route('transcripts.csv', $this->classroom))->assertForbidden();
        $this->get(route('transcripts.excel', $this->classroom))->assertForbidden();
    }

    public function test_empty_states_and_late_enrollment_are_handled(): void
    {
        $newStudent = User::factory()->create(['role' => 'siswa']);
        $this->actingAs($newStudent)->get(route('student.transcripts.index'))->assertOk()->assertSee('Belum ada kelas yang diikuti');
        $emptyClass = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'Kelas kosong']);
        $emptyClass->students()->attach($this->student);
        $this->actingAs($this->student)->get(route('student.transcripts.index'))
            ->assertOk()->assertSee('Belum divalidasi')->assertSee('Kelas kosong')->assertDontSee('Kuis dengan penalti');
        $this->actingAs($this->teacher)->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])->assertRedirect();
        $this->actingAs($this->student)->get(route('student.transcripts.index'))
            ->assertOk()->assertSee('Belum divalidasi')->assertSee('Tugas dinilai');
        $this->classroom->students()->attach($newStudent);
        $this->actingAs($newStudent)->get(route('student.transcripts.index'))->assertOk()->assertSee('Belum tercantum dalam transkrip final');
        $lateCsv = $this->get(route('student.transcripts.all.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Belum tercantum dalam transkrip final', $lateCsv);
        $this->assertStringNotContainsString('Tugas dinilai', $lateCsv);
        $this->actingAs($this->student)->get(route('student.transcripts.index', ['classroom' => $emptyClass->id]))
            ->assertOk()->assertSee('Tugas dinilai')->assertSee('Kelas kosong');
    }

    public function test_student_cannot_see_or_download_transcript_before_teacher_validation(): void
    {
        $response = $this->actingAs($this->student)->get(route('student.transcripts.index'))->assertOk()
            ->assertSee('Belum divalidasi')
            ->assertDontSee('Tugas dinilai')
            ->assertDontSee('Kuis dengan penalti')
            ->assertSee('Unduh CSV')
            ->assertSee('Excel (.xlsx)');
        $this->assertSame(0, $response->viewData('validatedCount'));
        $this->assertNull($response->viewData('entries')[0]['score']);
        $this->assertNull($response->viewData('average'));
        $pendingCsv = $this->get(route('student.transcripts.all.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Belum divalidasi', $pendingCsv);
        $this->assertStringNotContainsString('Tugas dinilai', $pendingCsv);
        $this->actingAs($this->teacher)->get(route('transcripts.index', ['classroom' => $this->classroom->id]))
            ->assertOk()->assertSee('Draft transkrip');
    }

    public function test_student_cannot_read_finalization_without_a_saved_snapshot(): void
    {
        $this->actingAs($this->teacher)->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])->assertRedirect();
        TranscriptState::where('classroom_id', $this->classroom->id)->firstOrFail()->update(['snapshot' => null]);

        $this->actingAs($this->student)->get(route('student.transcripts.index'))
            ->assertOk()->assertSee('Belum divalidasi')->assertDontSee('Tugas dinilai');
        $pendingCsv = $this->get(route('student.transcripts.all.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Belum divalidasi', $pendingCsv);
        $this->assertStringNotContainsString('Tugas dinilai', $pendingCsv);
    }

    public function test_all_classes_are_combined_and_pending_class_has_no_scores_in_page_and_exports(): void
    {
        $pending = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'Biologi tanpa validasi']);
        $pending->students()->attach($this->student);
        $pendingAssignment = Assignment::create([
            'classroom_id' => $pending->id, 'title' => 'Nilai Biologi Rahasia', 'points' => 10,
        ]);
        Submission::create([
            'assignment_id' => $pendingAssignment->id, 'user_id' => $this->student->id,
            'score' => 5, 'submitted_at' => now(),
        ]);
        $this->actingAs($this->teacher)->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])->assertRedirect();

        $response = $this->actingAs($this->student)->get(route('student.transcripts.index'))->assertOk()
            ->assertSee($pending->name)->assertSee($this->classroom->name)
            ->assertSee('Belum divalidasi')->assertSee('Tugas dinilai')
            ->assertDontSee('Nilai Biologi Rahasia')->assertDontSee($this->peer->name);
        $this->assertSame(1, $response->viewData('validatedCount'));
        $this->assertSame(60.0, $response->viewData('average'));
        $pendingEntry = $response->viewData('entries')->first(fn ($entry) => $entry['classroom']->id === $pending->id);
        $this->assertNull($pendingEntry['activity']);
        $this->assertNull($pendingEntry['score']);

        $csv = $this->get(route('student.transcripts.all.csv'))->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFsep=,\r\n", $csv);
        $this->assertStringContainsString($pending->name, $csv);
        $this->assertStringNotContainsString('Nilai Biologi Rahasia', $csv);
        $this->assertStringNotContainsString($this->peer->name, $csv);
        $csvRows = array_map('str_getcsv', explode("\r\n", $csv));
        $pendingCsv = collect($csvRows)->first(fn ($row) => ($row[1] ?? null) === $pending->name);
        $this->assertSame('', $pendingCsv[2]);
        $this->assertSame('', $pendingCsv[8]);
        $this->assertSame('Belum divalidasi', $pendingCsv[3]);

        $content = $this->get(route('student.transcripts.all.excel'))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->streamedContent();
        $this->assertStringStartsWith('PK', $content);
        $path = tempnam(sys_get_temp_dir(), 'test-all-student-transcript-');
        file_put_contents($path, $content);
        $reader = new Reader;
        try {
            $reader->open($path);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) $rows[] = $row->toArray();
            }
            $pendingXlsx = collect($rows)->first(fn ($row) => ($row[1] ?? null) === $pending->name);
            $this->assertSame('Belum divalidasi', $pendingXlsx[3]);
            $this->assertEmpty($pendingXlsx[2]);
            $this->assertEmpty($pendingXlsx[8]);
            $this->assertStringNotContainsString('Nilai Biologi Rahasia', json_encode($rows));
            $this->assertStringNotContainsString($this->peer->name, json_encode($rows));
        } finally {
            $reader->close();
            unlink($path);
        }

        $this->actingAs($this->teacher)->post(route('transcripts.finalize', $pending), ['final_validation' => 1])->assertRedirect();
        $after = $this->actingAs($this->student)->get(route('student.transcripts.index'))->assertOk()
            ->assertSee('Nilai Biologi Rahasia')->assertDontSee('Belum divalidasi');
        $this->assertSame(2, $after->viewData('validatedCount'));
        $this->assertSame(58.0, $after->viewData('average'));
    }
}
