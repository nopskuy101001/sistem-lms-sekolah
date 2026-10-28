<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\TranscriptScoreOverride;
use App\Models\User;
use App\Services\TranscriptReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranscriptFinalValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Classroom $classroom;
    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'guru']);
        $this->student = User::factory()->create(['role' => 'siswa']);
        $this->classroom = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Kelas Finalisasi',
            'subject' => 'Matematika',
        ]);
        $this->classroom->students()->attach($this->student);
        $this->quiz = Quiz::create([
            'classroom_id' => $this->classroom->id,
            'title' => 'Kuis sudah ditutup',
            'start_at' => now()->subHours(2),
            'end_at' => now()->subHour(),
            'is_published' => true,
        ]);
        Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => Question::TYPE_PG,
            'body' => '1+1?',
            'option_a' => '2',
            'option_b' => '3',
            'correct_option' => 'a',
            'points' => 50,
        ]);
    }

    private function scorePayload(float $score): array
    {
        return [
            'student_id' => $this->student->id,
            'activity_type' => 'quiz',
            'activity_id' => $this->quiz->id,
            'score' => $score,
            'reason' => 'Diberi nilai oleh guru setelah tenggat',
        ];
    }

    public function test_teacher_can_grade_a_missing_attempt_after_quiz_expired_and_sync_all_views(): void
    {
        $this->assertFalse($this->quiz->isOpen());
        $this->assertSame('Tidak mengerjakan (waktu habis)',
            app(TranscriptReportService::class)->build($this->classroom)['rows'][0]['scores']['quiz-'.$this->quiz->id]['status']);
        $this->actingAs($this->teacher)->put(route('transcripts.scores.update', $this->classroom), $this->scorePayload(0))
            ->assertRedirect(route('transcripts.index', ['classroom' => $this->classroom->id]));

        $score = app(TranscriptReportService::class)->build($this->classroom)['rows'][0]['scores']['quiz-'.$this->quiz->id];
        $this->assertSame(0.0, $score['score']);
        $this->assertTrue($score['manual']);
        $this->assertTrue($score['final']);
        $this->assertNull(QuizAttempt::where('quiz_id', $this->quiz->id)->first());

        $this->get(route('classrooms.grades', $this->classroom))->assertOk()->assertSee('koreksi guru');
        $this->get(route('transcripts.index', ['classroom' => $this->classroom->id]))->assertOk()->assertSee('0 / 50');
        $csv = $this->get(route('transcripts.csv', $this->classroom))->assertOk()->streamedContent();
        $this->assertStringContainsString(',0,0', $csv);
    }

    public function test_teacher_can_edit_then_clear_override_without_touching_original_attempt(): void
    {
        $attempt = QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'user_id' => $this->student->id,
            'started_at' => now()->subHours(2),
            'finished_at' => now()->subHour(),
            'score' => 35,
            'is_graded' => true,
        ]);
        $this->actingAs($this->teacher)->put(route('transcripts.scores.update', $this->classroom), $this->scorePayload(45))->assertRedirect();
        $this->assertSame(35.0, (float) $attempt->fresh()->score);
        $this->assertSame(45.0, app(TranscriptReportService::class)->build($this->classroom)['rows'][0]['scores']['quiz-'.$this->quiz->id]['score']);
        $this->delete(route('transcripts.scores.clear', $this->classroom), $this->scorePayload(45))->assertRedirect();
        $this->assertDatabaseMissing('transcript_score_overrides', ['classroom_id' => $this->classroom->id]);
        $this->assertSame(35.0, app(TranscriptReportService::class)->build($this->classroom)['rows'][0]['scores']['quiz-'.$this->quiz->id]['score']);
        $this->assertDatabaseCount('transcript_score_audits', 2);
    }

    public function test_final_validation_requires_checkbox_locks_updates_and_freezes_report(): void
    {
        $this->actingAs($this->teacher);
        $this->post(route('transcripts.finalize', $this->classroom))->assertSessionHasErrors('final_validation');
        $this->put(route('transcripts.scores.update', $this->classroom), $this->scorePayload(25))->assertRedirect();
        $this->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])->assertRedirect();

        $before = app(TranscriptReportService::class)->build($this->classroom);
        $this->assertTrue($before['finalized']);
        $this->assertSame(25.0, $before['rows'][0]['scores']['quiz-'.$this->quiz->id]['score']);

        $this->put(route('transcripts.scores.update', $this->classroom), $this->scorePayload(10))->assertStatus(409);
        $this->delete(route('transcripts.scores.clear', $this->classroom), $this->scorePayload(10))->assertStatus(409);
        $this->post(route('transcripts.finalize', $this->classroom), ['final_validation' => 1])->assertStatus(409);
        // Even if a source attempt is inserted later, snapshot must remain immutable.
        QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'user_id' => $this->student->id,
            'started_at' => now()->subMinutes(90),
            'finished_at' => now()->subHour(),
            'score' => 40,
            'is_graded' => true,
        ]);
        $after = app(TranscriptReportService::class)->build($this->classroom);
        $this->assertSame($before['rows'][0]['scores'], $after['rows'][0]['scores']);
    }

    public function test_strict_authorization_and_max_score_validation(): void
    {
        $stranger = User::factory()->create(['role' => 'guru']);
        $this->actingAs($stranger)->put(route('transcripts.scores.update', $this->classroom), $this->scorePayload(10))->assertForbidden();
        $this->actingAs($this->student)->put(route('transcripts.scores.update', $this->classroom), $this->scorePayload(10))->assertForbidden();
        $this->actingAs($this->teacher)->put(route('transcripts.scores.update', $this->classroom), $this->scorePayload(51))->assertSessionHasErrors('score');
        $this->put(route('transcripts.scores.update', $this->classroom), $this->scorePayload(-1))->assertSessionHasErrors('score');
        $notMember = User::factory()->create(['role' => 'siswa']);
        $this->put(route('transcripts.scores.update', $this->classroom), [
            ...$this->scorePayload(10), 'student_id' => $notMember->id,
        ])->assertSessionHasErrors('student_id');
        $this->assertSame(0, TranscriptScoreOverride::count());
    }
}
