<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TkaQuizFormatTest extends TestCase
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
            'name' => 'TKA Matematika',
            'color' => 'indigo',
        ]);
        $this->classroom->students()->attach($this->student);
        $this->quiz = Quiz::create([
            'classroom_id' => $this->classroom->id,
            'title' => 'Latihan TKA',
            'is_published' => true,
            'shuffle_questions' => true,
        ]);
    }

    public function test_teacher_can_create_tka_stimulus_and_complex_question_formats(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('quizzes.show', $this->quiz))
            ->assertOk()
            ->assertSee('Judul stimulus')
            ->assertSee('name="title"', false)
            ->assertSee('required', false)
            ->assertSee('name="option_e"', false)
            ->assertSee('value="e"', false);

        $this->actingAs($this->teacher)
            ->post(route('question-groups.store', $this->quiz), [
                'title' => 'Data Penjualan',
                'stimulus' => 'Perhatikan data penjualan selama empat bulan.',
            ])->assertRedirect();

        $group = QuestionGroup::firstOrFail();

        $this->actingAs($this->teacher)
            ->post(route('questions.store', $this->quiz), [
                'type' => Question::TYPE_PGK_MCMA,
                'question_group_id' => $group->id,
                'body' => 'Pilih dua simpulan yang benar.',
                'points' => 10,
                'option_a' => 'Simpulan A',
                'option_b' => 'Simpulan B',
                'option_c' => 'Simpulan C',
                'correct_options' => ['a', 'c'],
                'competency' => 'Penalaran kuantitatif',
                'cognitive_level' => 'penalaran',
            ])->assertRedirect();

        $this->actingAs($this->teacher)
            ->post(route('questions.store', $this->quiz), [
                'type' => Question::TYPE_PGK_KATEGORI,
                'question_group_id' => $group->id,
                'body' => 'Tentukan benar atau salah.',
                'points' => 8,
                'category_statements' => ['Pernyataan pertama', 'Pernyataan kedua'],
                'category_answers' => ['benar', 'salah'],
            ])->assertRedirect();

        $multi = Question::where('type', Question::TYPE_PGK_MCMA)->firstOrFail();
        $category = Question::where('type', Question::TYPE_PGK_KATEGORI)->firstOrFail();

        $this->assertSame(['a', 'c'], $multi->correct_options);
        $this->assertSame('penalaran', $multi->cognitive_level);
        $this->assertCount(2, $category->category_items);
        $this->assertSame('salah', $category->category_items[1]['answer']);

        $this->actingAs($this->teacher)
            ->get(route('quizzes.show', $this->quiz))
            ->assertOk()
            ->assertSee('Struktur soal TKA')
            ->assertSee('PG Kompleks - Multi Jawaban')
            ->assertSee('Data Penjualan');
    }

    public function test_stimulus_title_is_required(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('question-groups.store', $this->quiz), [
                'stimulus' => 'Isi stimulus tanpa judul.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('question_groups', 0);
    }

    public function test_student_sees_quiz_opening_and_closing_timeline_separately_from_duration(): void
    {
        $start = now()->addDay()->startOfHour();
        $end = $start->copy()->addHours(3);
        $this->quiz->update([
            'duration_minutes' => 45,
            'start_at' => $start,
            'end_at' => $end,
        ]);

        $classwork = $this->actingAs($this->student)
            ->get(route('classrooms.classwork', $this->classroom))
            ->assertOk()
            ->assertSee('Mulai:')
            ->assertSee('Tutup:')
            ->assertSee($start->translatedFormat('d M Y, H:i'))
            ->assertSee($end->translatedFormat('d M Y, H:i'));
        $this->assertStringContainsString($start->toIso8601String(), $classwork->getContent());

        $detail = $this->get(route('quizzes.show', $this->quiz))
            ->assertOk()
            ->assertSee('Timeline pelaksanaan kuis')
            ->assertSee('Durasi pengerjaan')
            ->assertSee('45 menit')
            ->assertSee('Mulai kuis')
            ->assertSee('Kuis ditutup')
            ->assertSee($start->translatedFormat('d F Y, H:i'))
            ->assertSee($end->translatedFormat('d F Y, H:i'))
            ->assertSee('Status: Belum dibuka');
        $this->assertStringContainsString($end->toIso8601String(), $detail->getContent());

        $this->quiz->update(['start_at' => null, 'end_at' => null]);
        $this->get(route('quizzes.show', $this->quiz))
            ->assertOk()
            ->assertSee('Sejak dipublikasikan')
            ->assertSee('Tanpa tanggal penutupan');

        $this->quiz->update([
            'start_at' => now()->subHours(2),
            'end_at' => now()->subHour(),
        ]);
        $this->get(route('quizzes.show', $this->quiz))
            ->assertOk()
            ->assertSee('Status: Ditutup')
            ->assertSee('quiz-schedule-card', false)
            ->assertSee('quiz-schedule-status', false);
        $stylesheet = file_get_contents(public_path('css/lms-ui.css'));
        $this->assertIsString($stylesheet);
        $this->assertStringContainsString("html[data-theme='dark'] .quiz-schedule-card", $stylesheet);
        $this->assertStringContainsString("html[data-theme='dark'] .quiz-schedule-status", $stylesheet);
    }

    public function test_tka_answers_are_autoscored_by_their_question_format(): void
    {
        $single = Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => Question::TYPE_PG,
            'body' => 'Pilih jawaban kelima.',
            'option_a' => 'Satu', 'option_b' => 'Dua', 'option_c' => 'Tiga',
            'option_d' => 'Empat', 'option_e' => 'Lima',
            'correct_option' => 'e',
            'points' => 10,
        ]);
        $multi = Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => Question::TYPE_PGK_MCMA,
            'body' => 'Pilih jawaban benar.',
            'option_a' => 'A', 'option_b' => 'B', 'option_c' => 'C',
            'correct_options' => ['a', 'c'],
            'points' => 10,
        ]);
        $category = Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => Question::TYPE_PGK_KATEGORI,
            'body' => 'Kategorikan pernyataan.',
            'category_items' => [
                ['statement' => 'Satu', 'answer' => 'benar'],
                ['statement' => 'Dua', 'answer' => 'salah'],
            ],
            'points' => 10,
        ]);
        $attempt = QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'user_id' => $this->student->id,
            'started_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->get(route('attempts.take', $attempt))
            ->assertOk()
            ->assertSee('Pilih jawaban kelima.')
            ->assertSee('Lima')
            ->assertSee('value="e"', false);

        $this->actingAs($this->student)
            ->post(route('attempts.submit', $attempt), [
                'answers' => [
                    $single->id => 'e',
                    $multi->id => ['c', 'a'],
                    $category->id => ['benar', 'benar'],
                ],
            ])->assertRedirect(route('attempts.show', $attempt));

        $this->assertDatabaseHas('quiz_answers', [
            'quiz_attempt_id' => $attempt->id,
            'question_id' => $single->id,
            'answer' => 'e',
            'is_correct' => true,
            'score' => 10,
        ]);
        $this->assertDatabaseHas('quiz_answers', [
            'quiz_attempt_id' => $attempt->id,
            'question_id' => $multi->id,
            'is_correct' => true,
            'score' => 10,
        ]);
        $this->assertDatabaseHas('quiz_answers', [
            'quiz_attempt_id' => $attempt->id,
            'question_id' => $category->id,
            'is_correct' => false,
            'score' => 5,
        ]);
        $this->assertEquals(25, $attempt->fresh()->score);
    }

    public function test_questions_that_share_a_stimulus_remain_adjacent_when_shuffled(): void
    {
        $group = QuestionGroup::create([
            'quiz_id' => $this->quiz->id,
            'title' => 'Bacaan',
            'stimulus' => 'Bacalah teks berikut.',
        ]);
        $first = $this->makeQuestion('Soal stimulus 1', $group->id);
        $second = $this->makeQuestion('Soal stimulus 2', $group->id);
        $this->makeQuestion('Soal mandiri 1');
        $this->makeQuestion('Soal mandiri 2');

        $this->actingAs($this->student)
            ->post(route('attempts.start', $this->quiz))
            ->assertRedirect();

        $order = QuizAttempt::firstOrFail()->question_order;
        $this->assertSame(1, abs(array_search($first->id, $order, true) - array_search($second->id, $order, true)));

        $this->actingAs($this->student)
            ->get(route('attempts.take', QuizAttempt::first()))
            ->assertOk()
            ->assertSee('Stimulus TKA')
            ->assertSee('Bacalah teks berikut.');
    }

    public function test_long_stimulus_and_question_are_rendered_without_visual_clamping(): void
    {
        $longStimulus = str_repeat('Stimulus panjang harus tetap dapat dibaca sampai selesai pada layar kecil. ', 20);
        $longQuestion = str_repeat('Pertanyaan panjang tidak boleh terpotong dan harus turun ke baris berikutnya. ', 12);
        $longOption = str_repeat('Pilihan jawaban yang panjang juga harus tampil secara utuh. ', 8);

        $group = QuestionGroup::create([
            'quiz_id' => $this->quiz->id,
            'title' => 'Stimulus panjang untuk pengujian tampilan',
            'stimulus' => $longStimulus,
        ]);
        $question = $this->makeQuestion($longQuestion, $group->id);
        $question->update(['option_a' => $longOption]);

        $this->actingAs($this->teacher)
            ->get(route('quizzes.show', $this->quiz))
            ->assertOk()
            ->assertSee($longStimulus)
            ->assertSee($longQuestion)
            ->assertSee($longOption)
            ->assertSee('quiz-readable-text', false)
            ->assertSee('quiz-teacher-layout', false)
            ->assertSee('data-quiz-auto-grow', false)
            ->assertSee('Stimulus untuk kelompok soal berikut')
            ->assertDontSee('line-clamp-3', false);

        $attempt = QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'user_id' => $this->student->id,
            'started_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->get(route('attempts.take', $attempt))
            ->assertOk()
            ->assertSee($longStimulus)
            ->assertSee($longQuestion)
            ->assertSee($longOption)
            ->assertSee('quiz-stimulus-card', false)
            ->assertSee('quiz-question-card', false);
    }

    public function test_stimulus_supports_private_images_and_structured_tables(): void
    {
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $image = UploadedFile::fake()->createWithContent('grafik-penjualan.png', $png);

        $this->actingAs($this->teacher)
            ->post(route('question-groups.store', $this->quiz), [
                'title' => 'Grafik dan Tabel Penjualan',
                'stimulus' => 'Amati visual dan data berikut.',
                'stimulus_image' => $image,
                'media_alt' => 'Grafik penjualan Januari sampai Maret',
                'table_text' => "Bulan\tPenjualan\nJanuari\t120\nFebruari\t150",
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $group = QuestionGroup::firstOrFail();
        Storage::disk('local')->assertExists($group->media_path);
        $this->assertSame('Grafik penjualan Januari sampai Maret', $group->media_alt);
        $this->assertSame([
            ['Bulan', 'Penjualan'],
            ['Januari', '120'],
            ['Februari', '150'],
        ], $group->table_data);

        $this->actingAs($this->student)
            ->get(route('question-groups.media', $group))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=grafik-penjualan.png');

        $outsider = User::factory()->create(['role' => 'siswa']);
        $this->actingAs($outsider)
            ->get(route('question-groups.media', $group))
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->delete(route('question-groups.destroy', $group))
            ->assertRedirect();
        Storage::disk('local')->assertMissing($group->media_path);
    }

    public function test_stimulus_table_and_image_are_rendered_in_student_quiz(): void
    {
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->actingAs($this->teacher)->post(route('question-groups.store', $this->quiz), [
            'title' => 'Data Penduduk',
            'stimulus_image' => UploadedFile::fake()->createWithContent('diagram.png', $png),
            'table_text' => "Tahun,Penduduk\n2025,1000",
        ])->assertRedirect()->assertSessionHasNoErrors();

        $group = QuestionGroup::firstOrFail();
        $question = $this->makeQuestion('Berapa jumlah penduduk?', $group->id);
        $attempt = QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'user_id' => $this->student->id,
            'started_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->get(route('attempts.take', $attempt))
            ->assertOk()
            ->assertSee('Data Penduduk')
            ->assertSee('2025')
            ->assertSee('1000')
            ->assertSee(route('question-groups.media', $group), false)
            ->assertSee('Berapa jumlah penduduk?');
    }

    public function test_video_url_can_be_used_as_information_for_multiple_questions(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('question-groups.store', $this->quiz), [
                'title' => 'Video perubahan iklim',
                'stimulus_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'video_caption' => 'Simak video sebelum menjawab dua soal berikut.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $group = QuestionGroup::firstOrFail();
        $this->assertSame('youtube', $group->video_type);
        $this->makeQuestion('Apa informasi utama video?', $group->id);
        $this->makeQuestion('Simpulan mana yang sesuai?', $group->id);

        $attempt = QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'user_id' => $this->student->id,
            'started_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->get(route('attempts.take', $attempt))
            ->assertOk()
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Simak video sebelum menjawab dua soal berikut.')
            ->assertSee('Apa informasi utama video?')
            ->assertSee('Simpulan mana yang sesuai?');
    }

    public function test_uploaded_stimulus_video_is_private_and_removed_with_its_group(): void
    {
        Storage::fake('local');

        $this->actingAs($this->teacher)
            ->post(route('question-groups.store', $this->quiz), [
                'title' => 'Video eksperimen',
                'stimulus_video' => UploadedFile::fake()->create('eksperimen.mp4', 512, 'video/mp4'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $group = QuestionGroup::firstOrFail();
        $videoPath = $group->video_path;
        $this->assertSame('upload', $group->video_type);
        Storage::disk('local')->assertExists($videoPath);

        $this->actingAs($this->student)
            ->get(route('question-groups.video', $group))
            ->assertOk()
            ->assertHeader('content-type', 'video/mp4');

        $outsider = User::factory()->create(['role' => 'siswa']);
        $this->actingAs($outsider)
            ->get(route('question-groups.video', $group))
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->delete(route('question-groups.destroy', $group))
            ->assertRedirect();
        Storage::disk('local')->assertMissing($videoPath);
    }

    public function test_stimulus_rejects_unsupported_or_duplicate_video_sources(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('question-groups.store', $this->quiz), [
                'title' => 'Video tidak didukung',
                'stimulus_video_url' => 'https://example.com/watch/video',
            ])
            ->assertSessionHasErrors('stimulus_video_url');

        $this->actingAs($this->teacher)
            ->post(route('question-groups.store', $this->quiz), [
                'title' => 'Sumber video ganda',
                'stimulus_video_url' => 'https://example.com/video.mp4',
                'stimulus_video' => UploadedFile::fake()->create('video.mp4', 20, 'video/mp4'),
            ])
            ->assertSessionHasErrors('stimulus_video_url');

        $this->assertDatabaseCount('question_groups', 0);
    }

    private function makeQuestion(string $body, ?int $groupId = null): Question
    {
        return Question::create([
            'quiz_id' => $this->quiz->id,
            'question_group_id' => $groupId,
            'type' => Question::TYPE_PG,
            'body' => $body,
            'option_a' => 'Benar',
            'option_b' => 'Salah',
            'correct_option' => 'a',
            'points' => 5,
        ]);
    }
}
