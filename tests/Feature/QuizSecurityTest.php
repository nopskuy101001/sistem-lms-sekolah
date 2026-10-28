<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizSecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuizSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Quiz $quiz;
    private Question $question;
    private QuizAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'guru']);
        $this->student = User::factory()->create(['role' => 'siswa']);

        $classroom = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Matematika XI',
            'color' => 'indigo',
        ]);
        $classroom->students()->attach($this->student->id);

        $this->quiz = Quiz::create([
            'classroom_id' => $classroom->id,
            'title' => 'Ujian Persamaan',
            'duration_minutes' => 10,
            'is_published' => true,
            'monitoring_enabled' => true,
        ]);

        $this->question = Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => Question::TYPE_PG,
            'body' => 'Berapakah 2 + 2?',
            'option_a' => '3',
            'option_b' => '4',
            'correct_option' => 'b',
            'points' => 10,
        ]);

        $this->attempt = QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'user_id' => $this->student->id,
            'started_at' => now(),
        ]);
    }

    public function test_secure_quiz_workspace_is_shown_to_student(): void
    {
        $this->actingAs($this->student)
            ->get(route('attempts.take', $this->attempt))
            ->assertOk()
            ->assertSee('Mode ujian terpantau')
            ->assertSee('Jawaban tersimpan otomatis');
    }

    public function test_answers_are_autosaved_on_server(): void
    {
        $this->actingAs($this->student)
            ->postJson(route('attempts.answers.save', $this->attempt), [
                'answers' => [$this->question->id => 'b'],
            ])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertDatabaseHas('quiz_answers', [
            'quiz_attempt_id' => $this->attempt->id,
            'question_id' => $this->question->id,
            'answer' => 'b',
        ]);
    }

    public function test_focus_violation_is_recorded_and_duplicate_is_ignored(): void
    {
        $payload = ['event_type' => 'tab_hidden', 'metadata' => ['visibility_state' => 'hidden']];

        $this->actingAs($this->student)
            ->postJson(route('attempts.security-events.store', $this->attempt), $payload)
            ->assertOk()
            ->assertJson(['recorded' => true, 'violation_count' => 1]);

        $this->actingAs($this->student)
            ->postJson(route('attempts.security-events.store', $this->attempt), $payload)
            ->assertOk()
            ->assertJson(['recorded' => false, 'violation_count' => 1]);

        $this->assertDatabaseCount('quiz_security_events', 1);
    }

    public function test_another_student_cannot_write_attempt_security_events(): void
    {
        $otherStudent = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($otherStudent)
            ->postJson(route('attempts.security-events.store', $this->attempt), [
                'event_type' => 'window_blur',
            ])
            ->assertForbidden();
    }

    public function test_expired_attempt_uses_last_autosaved_answer(): void
    {
        Carbon::setTestNow($this->attempt->started_at->copy()->addMinute());
        $this->actingAs($this->student)->postJson(route('attempts.answers.save', $this->attempt), [
            'answers' => [$this->question->id => 'b'],
        ])->assertOk();

        Carbon::setTestNow($this->attempt->started_at->copy()->addMinutes(11));

        $this->actingAs($this->student)
            ->post(route('attempts.submit', $this->attempt), [
                'answers' => [$this->question->id => 'a'],
            ])
            ->assertRedirect(route('attempts.show', $this->attempt));

        $this->assertDatabaseHas('quiz_answers', [
            'quiz_attempt_id' => $this->attempt->id,
            'question_id' => $this->question->id,
            'answer' => 'b',
            'score' => 10,
        ]);

        Carbon::setTestNow();
    }

    public function test_teacher_can_change_quiz_security_settings(): void
    {
        $this->actingAs($this->teacher)
            ->put(route('quizzes.security.update', $this->quiz), [
                'monitoring_enabled' => '1',
                'require_fullscreen' => '1',
                'shuffle_questions' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quizzes', [
            'id' => $this->quiz->id,
            'monitoring_enabled' => true,
            'require_fullscreen' => true,
        ]);
    }

    public function test_fullscreen_can_be_required_without_focus_monitoring(): void
    {
        $this->actingAs($this->teacher)
            ->put(route('quizzes.security.update', $this->quiz), [
                'require_fullscreen' => '1',
                'shuffle_questions' => '1',
            ])
            ->assertRedirect();

        $this->quiz->refresh();
        $this->assertFalse($this->quiz->monitoring_enabled);
        $this->assertTrue($this->quiz->require_fullscreen);

        $this->actingAs($this->student)
            ->get(route('attempts.take', $this->attempt))
            ->assertOk()
            ->assertSee('Mode layar penuh wajib')
            ->assertSee('pemantauan perpindahan tab lainnya tidak diaktifkan')
            ->assertSeeText('0 peringatan');

        $this->actingAs($this->student)
            ->postJson(route('attempts.security-events.store', $this->attempt), [
                'event_type' => 'fullscreen_exit',
            ])
            ->assertOk()
            ->assertJson(['recorded' => true, 'violation_count' => 1]);

        $this->assertDatabaseHas('quiz_security_events', [
            'quiz_attempt_id' => $this->attempt->id,
            'event_type' => 'fullscreen_exit',
        ]);

        $this->actingAs($this->student)
            ->postJson(route('attempts.security-events.store', $this->attempt), [
                'event_type' => 'tab_hidden',
            ])
            ->assertOk()
            ->assertJson(['recorded' => false, 'violation_count' => 1]);
    }

    public function test_focus_monitoring_does_not_enable_fullscreen_automatically(): void
    {
        $this->actingAs($this->teacher)
            ->put(route('quizzes.security.update', $this->quiz), [
                'monitoring_enabled' => '1',
                'shuffle_questions' => '1',
            ])
            ->assertRedirect();

        $this->quiz->refresh();
        $this->assertTrue($this->quiz->monitoring_enabled);
        $this->assertFalse($this->quiz->require_fullscreen);

        $this->actingAs($this->student)
            ->get(route('attempts.take', $this->attempt))
            ->assertOk()
            ->assertSee('Mode ujian terpantau')
            ->assertDontSee('Layar penuh diwajibkan selama kuis');
    }

    public function test_teacher_can_review_integrity_summary_and_event_timeline(): void
    {
        QuizSecurityEvent::create([
            'quiz_attempt_id' => $this->attempt->id,
            'event_type' => 'tab_hidden',
            'duration_seconds' => 8,
            'occurred_at' => now(),
        ]);
        $this->attempt->update([
            'finished_at' => now(),
            'score' => 0,
            'is_graded' => true,
        ]);

        $this->actingAs($this->teacher)
            ->get(route('quizzes.show', $this->quiz))
            ->assertOk()
            ->assertSee('Perlu ditinjau')
            ->assertSee('Integritas');

        $this->actingAs($this->teacher)
            ->get(route('attempts.show', $this->attempt))
            ->assertOk()
            ->assertSee('Ringkasan integritas ujian')
            ->assertSee('Keluar dari tab kuis')
            ->assertSee('durasi 8 detik');
    }

    public function test_teacher_can_enable_score_penalty_before_any_student_starts(): void
    {
        $this->attempt->delete();

        $this->actingAs($this->teacher)
            ->put(route('quizzes.security.update', $this->quiz), [
                'monitoring_enabled' => '1',
                'shuffle_questions' => '1',
                'penalty_enabled' => '1',
                'penalty_percent_per_violation' => '5',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('quizzes', [
            'id' => $this->quiz->id,
            'penalty_enabled' => true,
            'penalty_percent_per_violation' => 5,
        ]);
    }

    public function test_integrity_violation_reduces_final_score_and_preserves_raw_score(): void
    {
        $this->quiz->update([
            'penalty_enabled' => true,
            'penalty_percent_per_violation' => 5,
        ]);

        $this->actingAs($this->student)
            ->postJson(route('attempts.security-events.store', $this->attempt), [
                'event_type' => 'tab_hidden',
            ])
            ->assertOk();

        $this->actingAs($this->student)
            ->post(route('attempts.submit', $this->attempt), [
                'answers' => [$this->question->id => 'b'],
            ])
            ->assertRedirect(route('attempts.show', $this->attempt));

        $this->assertDatabaseHas('quiz_attempts', [
            'id' => $this->attempt->id,
            'raw_score' => 10,
            'penalty_score' => 0.5,
            'score' => 9.5,
        ]);

        $this->actingAs($this->student)
            ->get(route('attempts.show', $this->attempt))
            ->assertOk()
            ->assertSee('Nilai mentah 10 - penalti 0.5');

        $this->actingAs($this->teacher)
            ->get(route('transcripts.index', ['classroom' => $this->quiz->classroom_id]))
            ->assertOk()
            ->assertSee('9.5 / 10');
    }

    public function test_score_penalty_is_capped_at_twenty_percent_of_quiz_points(): void
    {
        $this->quiz->update([
            'penalty_enabled' => true,
            'penalty_percent_per_violation' => 10,
        ]);

        foreach (['tab_hidden', 'window_blur', 'fullscreen_exit'] as $eventType) {
            QuizSecurityEvent::create([
                'quiz_attempt_id' => $this->attempt->id,
                'event_type' => $eventType,
                'occurred_at' => now(),
            ]);
        }

        $this->actingAs($this->student)
            ->post(route('attempts.submit', $this->attempt), [
                'answers' => [$this->question->id => 'b'],
            ])
            ->assertRedirect(route('attempts.show', $this->attempt));

        $this->assertDatabaseHas('quiz_attempts', [
            'id' => $this->attempt->id,
            'raw_score' => 10,
            'penalty_score' => 2,
            'score' => 8,
        ]);
    }
}
