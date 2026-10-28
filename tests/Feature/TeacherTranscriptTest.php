<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\User;
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
            ->assertSee('Excel (.xlsx)');
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
