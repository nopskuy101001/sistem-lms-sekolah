<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponsiveAcademicViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_options_preserve_answer_lines_without_preserving_template_indentation(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $answer = "Baris pertama\nBaris kedua <script>alert(1)</script>";
        $bank = QuestionBank::create([
            'teacher_id' => $teacher->id,
            'title' => 'Paket responsif',
            'question_count' => 1,
            'total_points' => 10,
            'payload' => ['questions' => [[
                'type' => 'pilihan_ganda',
                'body' => 'Soal panjang',
                'option_a' => $answer,
                'option_b' => 'Jawaban lain',
                'correct_option' => 'a',
                'points' => 10,
            ]]],
        ]);

        $response = $this->actingAs($teacher)->get(route('question-banks.show', $bank));
        $response->assertOk()
            ->assertSee('href="#bank-use-package"', false)
            ->assertSee('id="bank-use-package"', false)
            ->assertSee('href="#bank-questions"', false)
            ->assertSee($answer)
            ->assertDontSee('<script>alert(1)</script>', false);

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $options = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " bank-answer ")]');
        $this->assertCount(2, $options);
        foreach ($options as $option) {
            $this->assertStringNotContainsString('quiz-readable-text', $option->getAttribute('class'));
        }
        $text = $xpath->query('.//span[contains(@class, "quiz-readable-text")]', $options->item(0))->item(0);
        $this->assertSame($answer, $text->textContent);
    }

    public function test_grade_and_transcript_tables_show_full_titles_and_responsive_columns(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa', 'name' => str_repeat('Nama panjang ', 8)]);
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Kelas responsif', 'color' => 'indigo']);
        $classroom->students()->attach($student);
        $title = 'Evaluasi semester dengan judul panjang yang harus tetap terbaca secara utuh';
        Quiz::create(['classroom_id' => $classroom->id, 'title' => $title, 'is_published' => true]);

        foreach ([route('classrooms.grades', $classroom), route('transcripts.index', ['classroom' => $classroom->id])] as $url) {
            $this->actingAs($teacher)->get($url)->assertOk()
                ->assertSee($title)
                ->assertSee($student->name)
                ->assertSee('academic-score-table', false)
                ->assertSee('score-student', false)
                ->assertSee('role="region"', false)
                ->assertDontSee('sticky right-0', false);
        }
    }

    public function test_quiz_exposes_collapsible_navigation_and_width_aware_editor(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Kelas kuis', 'color' => 'indigo']);
        $classroom->students()->attach($student);
        $quiz = Quiz::create(['classroom_id' => $classroom->id, 'title' => 'Kuis responsif', 'is_published' => true]);
        $quiz->questions()->create(['type' => 'esai', 'body' => 'Jelaskan jawaban panjang.', 'points' => 10]);

        $this->actingAs($teacher)->get(route('quizzes.show', $quiz))->assertOk()
            ->assertSee('data-quiz-auto-grow', false)
            ->assertSee('observer.observe(field)', false)
            ->assertSee("window.addEventListener('resize', resizeVisibleFields)", false);

        $attempt = QuizAttempt::create(['quiz_id' => $quiz->id, 'user_id' => $student->id, 'started_at' => now()]);
        $this->actingAs($student)->get(route('attempts.take', $attempt))->assertOk()
            ->assertSee('<details class="exam-navigation" id="exam-navigation" open>', false)
            ->assertSee('id="answered-count"', false)
            ->assertSee('--exam-content-offset', false)
            ->assertSee('question.scrollIntoView', false);
    }
}
