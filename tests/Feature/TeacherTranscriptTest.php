<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\TranscriptState;
use App\Models\User;
use App\Services\TranscriptReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class TeacherTranscriptTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'guru']);
        $this->student = User::factory()->create([
            'role' => 'siswa',
            'name' => 'Siswa Transkrip',
            'nomor_induk' => 'NIS-001',
        ]);
        $this->classroom = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Matematika XI',
            'subject' => 'Matematika',
            'color' => 'indigo',
        ]);
        $this->classroom->students()->attach($this->student);

        $assignment = Assignment::create([
            'classroom_id' => $this->classroom->id,
            'title' => 'Latihan Aljabar',
            'points' => 100,
        ]);
        Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->student->id,
            'submitted_at' => now(),
            'score' => 80,
        ]);

        $quiz = Quiz::create([
            'classroom_id' => $this->classroom->id,
            'title' => 'Kuis Persamaan',
            'is_published' => true,
        ]);
        Question::create([
            'quiz_id' => $quiz->id,
            'type' => Question::TYPE_PG,
            'body' => 'Soal satu',
            'option_a' => 'A',
            'option_b' => 'B',
            'correct_option' => 'a',
            'points' => 50,
        ]);
        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $this->student->id,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now(),
            'score' => 40,
            'is_graded' => true,
        ]);
    }

    public function test_teacher_can_view_class_transcript_and_normalized_average(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('transcripts.index', ['classroom' => $this->classroom->id]))
            ->assertOk()
            ->assertSee('Transkrip Nilai Siswa')
            ->assertSee('Siswa Transkrip')
            ->assertSee('Latihan Aljabar')
            ->assertSee('Kuis Persamaan')
            ->assertSee('80 / 100')
            ->assertSee('40 / 50')
            ->assertSee('Cetak')
            ->assertSee('Unduh CSV')
            ->assertSee('Unduh Excel')
            ->assertSee(route('transcripts.print', $this->classroom))
            ->assertSee(route('transcripts.excel', $this->classroom))
            ->assertSee(route('transcripts.csv', $this->classroom));
    }

    public function test_draft_quiz_is_excluded_even_after_its_deadline_and_from_final_snapshot(): void
    {
        $draft = Quiz::create([
            'classroom_id' => $this->classroom->id,
            'title' => 'Draf Kuis Kedaluwarsa',
            'start_at' => now()->subHours(2),
            'end_at' => now()->subHour(),
            'is_published' => false,
        ]);
        Question::create([
            'quiz_id' => $draft->id,
            'type' => Question::TYPE_PG,
            'body' => 'Soal draf tidak boleh masuk transkrip',
            'option_a' => 'A',
            'option_b' => 'B',
            'correct_option' => 'a',
            'points' => 100,
        ]);
        QuizAttempt::create([
            'quiz_id' => $draft->id,
            'user_id' => $this->student->id,
            'started_at' => now()->subHours(2),
            'finished_at' => now()->subHour(),
            'score' => 100,
            'is_graded' => true,
        ]);

        $report = app(TranscriptReportService::class)->build($this->classroom);
        $this->assertNotContains('quiz-'.$draft->id, $report['activities']->pluck('key')->all());
        $this->assertArrayNotHasKey('quiz-'.$draft->id, $report['rows']->first()['scores']);

        $this->actingAs($this->teacher)
            ->get(route('transcripts.index', ['classroom' => $this->classroom->id]))
            ->assertOk()
            ->assertDontSee($draft->title);
        $csv = $this->get(route('transcripts.csv', $this->classroom))->assertOk()->streamedContent();
        $this->assertStringNotContainsString($draft->title, $csv);

        $this->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])
            ->assertRedirect();
        $snapshot = TranscriptState::where('classroom_id', $this->classroom->id)->firstOrFail()->snapshot;
        $this->assertNotContains('quiz-'.$draft->id, collect($snapshot['activities'])->pluck('key')->all());
        $this->assertArrayNotHasKey('quiz-'.$draft->id, $snapshot['rows'][0]['scores']);
    }

    public function test_teacher_previews_then_downloads_wide_transcript_as_pdf(): void
    {
        // Dua komponen sudah dibuat di setUp. Delapan tambahan tepat memenuhi batas 10 kolom.
        for ($i = 1; $i <= 8; $i++) {
            Assignment::create(['classroom_id' => $this->classroom->id, 'title' => 'Komponen tambahan '.$i, 'points' => 100]);
        }

        $singlePage = $this->actingAs($this->teacher)->get(route('transcripts.print', $this->classroom))
            ->assertOk()
            ->assertDontSee('Bagian 1 dari');
        $this->assertSame(1, substr_count($singlePage->getContent(), 'class="teacher-table"'));

        // Kolom ke-11 harus diteruskan ke halaman kedua.
        Assignment::create(['classroom_id' => $this->classroom->id, 'title' => 'Komponen tambahan 9', 'points' => 100]);
        $preview = $this->get(route('transcripts.print', $this->classroom))
            ->assertOk()
            ->assertSee('Pratinjau transkrip')
            ->assertSee('Unduh PDF')
            ->assertSee(route('transcripts.pdf', $this->classroom))
            ->assertSee('Bagian 1 dari 2')
            ->assertSee('Bagian 2 dari 2')
            ->assertSee('© 2026 Muhammad Misfalah Saputra')
            ->assertDontSee('window.print');
        $this->assertSame(2, substr_count($preview->getContent(), 'class="teacher-table"'));

        $response = $this->get(route('transcripts.pdf', $this->classroom))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $other = User::factory()->create(['role' => 'guru']);
        $this->actingAs($other)->get(route('transcripts.print', $this->classroom))->assertForbidden();
        $this->actingAs($other)->get(route('transcripts.pdf', $this->classroom))->assertForbidden();
        $this->actingAs($this->student)->get(route('transcripts.pdf', $this->classroom))->assertForbidden();
    }

    public function test_teacher_can_download_utf8_csv_transcript(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('transcripts.csv', $this->classroom))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString("sep=,\r\n", $content);
        $this->assertStringContainsString('No.,"Nomor Induk","Nama Siswa",', $content);
        $this->assertStringContainsString('Siswa Transkrip', $content);
        $this->assertStringContainsString('80', $content);
    }

    public function test_teacher_can_download_a_real_xlsx_archive(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('transcripts.excel', $this->classroom))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);

        $path = tempnam(sys_get_temp_dir(), 'test-transcript-');
        file_put_contents($path, $content);
        $reader = new Reader();

        try {
            $reader->open($path);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
                break;
            }

            $this->assertSame('TRANSKRIP NILAI SISWA', $rows[0][0]);
            $this->assertSame('No.', $rows[3][0]);
            $this->assertSame('Siswa Transkrip', $rows[4][2]);
            $this->assertEquals(80, $rows[4][3]);
            $this->assertEquals(80, $rows[4][4]);
        } finally {
            $reader->close();
            @unlink($path);
        }
    }

    public function test_students_and_other_teachers_cannot_access_a_teacher_transcript(): void
    {
        $this->actingAs($this->student)
            ->get(route('transcripts.index'))
            ->assertForbidden();

        $otherTeacher = User::factory()->create(['role' => 'guru']);
        $this->actingAs($otherTeacher)
            ->get(route('transcripts.excel', $this->classroom))
            ->assertForbidden();

        $this->actingAs($otherTeacher)
            ->get(route('transcripts.index', ['classroom' => $this->classroom->id]))
            ->assertForbidden();
    }
}
