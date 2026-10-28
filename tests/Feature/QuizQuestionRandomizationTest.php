<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizQuestionRandomizationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $firstStudent;
    private User $secondStudent;
    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'guru']);
        $this->firstStudent = User::factory()->create(['role' => 'siswa']);
        $this->secondStudent = User::factory()->create(['role' => 'siswa']);

        $classroom = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Kelas Acak',
            'color' => 'sky',
        ]);
        $classroom->students()->attach([$this->firstStudent->id, $this->secondStudent->id]);

        $this->quiz = Quiz::create([
            'classroom_id' => $classroom->id,
            'title' => 'Kuis Urutan Acak',
            'duration_minutes' => 30,
            'is_published' => true,
            'monitoring_enabled' => false,
            'shuffle_questions' => true,
        ]);

        foreach (range(1, 4) as $number) {
            Question::create([
                'quiz_id' => $this->quiz->id,
                'type' => Question::TYPE_PG,
                'body' => 'Pertanyaan unik '.$number,
                'option_a' => 'Pilihan A',
                'option_b' => 'Pilihan B',
                'correct_option' => 'a',
                'points' => 10,
            ]);
        }
    }

    public function test_each_student_receives_a_different_persisted_question_order(): void
    {
        $this->actingAs($this->firstStudent)
            ->post(route('attempts.start', $this->quiz))
            ->assertRedirect();

        $this->actingAs($this->secondStudent)
            ->post(route('attempts.start', $this->quiz))
            ->assertRedirect();

        $firstAttempt = QuizAttempt::where('user_id', $this->firstStudent->id)->firstOrFail();
        $secondAttempt = QuizAttempt::where('user_id', $this->secondStudent->id)->firstOrFail();
        $expectedIds = $this->quiz->questions()->pluck('id')->sort()->values()->all();

        $this->assertSame($expectedIds, collect($firstAttempt->question_order)->sort()->values()->all());
        $this->assertSame($expectedIds, collect($secondAttempt->question_order)->sort()->values()->all());
        $this->assertNotSame($firstAttempt->question_order, $secondAttempt->question_order);

        $originalOrder = $firstAttempt->question_order;
        $this->actingAs($this->firstStudent)
            ->get(route('attempts.take', $firstAttempt))
            ->assertOk();
        $this->assertSame($originalOrder, $firstAttempt->fresh()->question_order);
    }

    public function test_quiz_page_renders_questions_in_the_attempt_order(): void
    {
        $questionIds = $this->quiz->questions()->pluck('id')->all();
        $customOrder = array_reverse($questionIds);
        $attempt = QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'user_id' => $this->firstStudent->id,
            'started_at' => now(),
            'question_order' => $customOrder,
        ]);
        $expectedBodies = Question::whereIn('id', $customOrder)
            ->get()
            ->keyBy('id');

        $this->actingAs($this->firstStudent)
            ->get(route('attempts.take', $attempt))
            ->assertOk()
            ->assertSeeInOrder(collect($customOrder)->map(fn ($id) => $expectedBodies[$id]->body)->all());
    }

    public function test_randomization_setting_is_locked_after_a_student_starts(): void
    {
        $this->actingAs($this->firstStudent)
            ->post(route('attempts.start', $this->quiz));

        $this->actingAs($this->teacher)
            ->put(route('quizzes.security.update', $this->quiz), [
                'monitoring_enabled' => '1',
            ])
            ->assertSessionHasErrors('shuffle_questions');

        $this->assertTrue($this->quiz->fresh()->shuffle_questions);
    }
}
