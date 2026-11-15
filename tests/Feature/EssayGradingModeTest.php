<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EssayGradingModeTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $firstStudent;

    private User $secondStudent;

    private Classroom $classroom;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'guru']);
        $this->firstStudent = User::factory()->create(['role' => 'siswa', 'name' => 'Siswa Pertama']);
        $this->secondStudent = User::factory()->create(['role' => 'siswa', 'name' => 'Siswa Kedua']);
        $this->classroom = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Bahasa Indonesia XI',
            'color' => 'indigo',
        ]);
        $this->classroom->students()->attach([$this->firstStudent->id, $this->secondStudent->id]);
        $this->quiz = Quiz::create([
            'classroom_id' => $this->classroom->id,
            'title' => 'Analisis Teks',
            'is_published' => true,
        ]);
    }

    public function test_essay_grading_entry_is_only_shown_when_quiz_has_an_essay(): void
    {
        Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => Question::TYPE_PG,
            'body' => 'Pilih jawaban yang benar.',
            'option_a' => 'Benar',
            'option_b' => 'Salah',
            'correct_option' => 'a',
            'points' => 5,
        ]);

        $this->actingAs($this->teacher)
            ->get(route('quizzes.show', $this->quiz))
            ->assertOk()
            ->assertDontSee('Mode Koreksi Esai')
            ->assertDontSee(route('quizzes.essay-grading', $this->quiz), false);

        Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => Question::TYPE_ESAI,
            'body' => 'Jelaskan gagasan utama teks.',
            'points' => 10,
        ]);

        $this->get(route('quizzes.show', $this->quiz))
            ->assertOk()
            ->assertSee('Mode Koreksi Esai')
            ->assertSee('Belum ada jawaban')
            ->assertDontSee('href="'.route('quizzes.essay-grading', $this->quiz).'"', false);
    }

    public function test_teacher_grades_in_split_workspace_and_moves_to_next_pending_student(): void
    {
        $question = Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => Question::TYPE_ESAI,
            'body' => 'Jelaskan makna paragraf tersebut.',
            'points' => 10,
        ]);
        [$firstAttempt, $firstAnswer] = $this->finishedAttempt($this->firstStudent, $question, 'Jawaban siswa pertama.');
        [$secondAttempt] = $this->finishedAttempt($this->secondStudent, $question, 'Jawaban siswa kedua.');

        $this->actingAs($this->teacher)
            ->get(route('quizzes.show', $this->quiz))
            ->assertOk()
            ->assertSee('2 dari 2 siswa belum selesai dinilai.')
            ->assertSee(route('quizzes.essay-grading', $this->quiz), false);

        $this->get(route('quizzes.essay-grading', $this->quiz))
            ->assertOk()
            ->assertSee('essay-grading-workspace', false)
            ->assertSee('Siswa Pertama')
            ->assertSee('Jawaban siswa pertama.')
            ->assertSee('Simpan & siswa berikutnya', false);

        $this->put(route('attempts.grade', $firstAttempt), [
            'scores' => [$firstAnswer->id => 8],
            'grading_mode' => 1,
            'navigation' => 'next',
        ])->assertRedirect(route('quizzes.essay-grading', [
            'quiz' => $this->quiz,
            'attempt' => $secondAttempt,
            'status' => 'pending',
        ]));

        $this->assertDatabaseHas('quiz_answers', ['id' => $firstAnswer->id, 'score' => 8]);
        $this->assertTrue($firstAttempt->fresh()->is_graded);

        $this->get(route('quizzes.essay-grading', [
            'quiz' => $this->quiz,
            'attempt' => $secondAttempt,
            'status' => 'pending',
        ]))->assertOk()
            ->assertSee('Siswa Kedua')
            ->assertSee('Jawaban siswa kedua.');
    }

    public function test_students_cannot_access_essay_grading_and_non_essay_quiz_redirects_teacher(): void
    {
        $this->actingAs($this->firstStudent)
            ->get(route('quizzes.essay-grading', $this->quiz))
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->get(route('quizzes.essay-grading', $this->quiz))
            ->assertRedirect(route('quizzes.show', $this->quiz))
            ->assertSessionHasErrors('grading');
    }

    /** @return array{QuizAttempt, QuizAnswer} */
    private function finishedAttempt(User $student, Question $question, string $body): array
    {
        $attempt = QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'user_id' => $student->id,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now(),
        ]);
        $answer = QuizAnswer::create([
            'quiz_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answer' => $body,
            'score' => null,
        ]);
        $attempt->recalculate();

        return [$attempt->fresh(), $answer];
    }
}
