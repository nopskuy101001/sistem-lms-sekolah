<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\User;
use App\Services\QuestionImportTemplateXlsxExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_exports_bank_as_template_compatible_excel_with_duration_and_private_access(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $otherTeacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Kelas Tujuan', 'color' => 'indigo']);
        $bank = QuestionBank::create([
            'teacher_id' => $teacher->id,
            'title' => 'Paket TKA',
            'question_count' => 4,
            'total_points' => 50,
            'payload' => [
                'version' => 1,
                'quiz' => ['title' => 'TKA 2026', 'description' => 'Paket latihan', 'duration_minutes' => 75],
                'groups' => [[
                    'key' => 'group-1', 'title' => 'Data nilai', 'stimulus' => 'Perhatikan tabel.',
                    'table_data' => [['Nama', 'Nilai'], ['Ayu', '20']], 'media_path' => 'question-bank-stimuli/grafik.png',
                ]],
                'questions' => [
                    ['type' => 'pilihan_ganda', 'body' => 'Berapa nilai Ayu?', 'option_a' => '=2+2', 'option_b' => '20', 'correct_option' => 'b', 'points' => 10, 'group_key' => 'group-1'],
                    ['type' => 'pilihan_ganda_kompleks', 'body' => 'Pilih dua jawaban.', 'option_a' => 'Satu', 'option_b' => 'Dua', 'option_c' => 'Tiga', 'correct_options' => ['a', 'c'], 'points' => 15, 'group_key' => 'group-1'],
                    ['type' => 'kategori', 'body' => 'Tentukan benar salah.', 'category_items' => [['statement' => 'Pernyataan satu', 'answer' => 'benar'], ['statement' => 'Pernyataan dua', 'answer' => 'salah']], 'points' => 20, 'group_key' => null],
                    ['type' => 'esai', 'body' => 'Jelaskan jawaban.', 'points' => 5, 'group_key' => null],
                ],
            ],
        ]);

        $this->actingAs($otherTeacher)->get(route('question-banks.excel', $bank))->assertForbidden();
        $this->actingAs($student)->get(route('question-banks.excel', $bank))->assertForbidden();
        $this->actingAs($teacher)->get(route('question-banks.index'))
            ->assertOk()->assertSee(route('question-banks.excel', $bank), false)->assertDontSee('Impor ke Bank Soal');
        $this->get(route('question-banks.show', $bank))
            ->assertOk()->assertSee(route('question-banks.excel', $bank), false);

        $content = $this->get(route('question-banks.excel', $bank))
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'bank-export-test-');
        file_put_contents($path, $content);
        $reader = new Reader;

        try {
            $reader->open($path);
            $sheets = array_values(iterator_to_array($reader->getSheetIterator()));
            $this->assertSame('Import Soal', $sheets[0]->getName());
            $rows = array_values(iterator_to_array($sheets[0]->getRowIterator()));
            $this->assertSame(QuestionImportTemplateXlsxExporter::HEADERS, $rows[0]->toArray());
            $this->assertCount(5, $rows);
            $this->assertSame('=2+2', $rows[1]->toArray()[2]);
            $this->assertSame('group-1', $rows[2]->toArray()[13]);
            $this->assertSame('', $rows[2]->toArray()[15]);
            $this->assertSame('Benar|Salah', $rows[3]->toArray()[8]);
            $this->assertSame('Informasi Paket', $sheets[1]->getName());
            $info = array_map(fn ($row) => $row->toArray(), iterator_to_array($sheets[1]->getRowIterator()));
            $this->assertContains(['Durasi (menit)', '75'], $info);
            $this->assertStringContainsString('Gambar tidak disertakan', collect($info)->firstWhere(0, 'Gambar stimulus')[1]);
        } finally {
            $reader->close();
            @unlink($path);
        }

        $quiz = Quiz::create(['classroom_id' => $classroom->id, 'title' => 'Hasil impor']);
        $this->post(route('questions.import', $quiz), [
            'question_file' => UploadedFile::fake()->createWithContent('hasil.xlsx', $content),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(4, $quiz->questions);
        $this->assertCount(1, $quiz->questionGroups);
        $this->assertSame('=2+2', $quiz->questions->first()->option_a);
    }

    public function test_teacher_can_save_complete_tka_package_and_reuse_it_as_a_draft_quiz(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('quiz-stimuli/source-chart.png', 'image-content');

        $teacher = User::factory()->create(['role' => 'guru']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'name' => 'Matematika XI',
            'color' => 'indigo',
        ]);
        $quiz = Quiz::create([
            'classroom_id' => $classroom->id,
            'title' => 'TKA Aljabar 2026',
            'description' => 'Paket asesmen akhir tahun',
            'duration_minutes' => 60,
            'monitoring_enabled' => true,
            'require_fullscreen' => false,
            'penalty_enabled' => true,
            'penalty_percent_per_violation' => 3,
            'shuffle_questions' => true,
            'is_published' => true,
            'start_at' => now(),
            'end_at' => now()->addDay(),
        ]);
        $group = $quiz->questionGroups()->create([
            'title' => 'Grafik Penjualan',
            'stimulus' => 'Perhatikan grafik dan tabel berikut.',
            'media_path' => 'quiz-stimuli/source-chart.png',
            'media_name' => 'grafik.png',
            'media_alt' => 'Grafik penjualan bulanan',
            'table_data' => [['Bulan', 'Nilai'], ['Januari', '20']],
        ]);
        $quiz->questions()->create([
            'question_group_id' => $group->id,
            'type' => Question::TYPE_PGK_MCMA,
            'body' => 'Pilih dua pernyataan yang benar.',
            'option_a' => 'Pernyataan A',
            'option_b' => 'Pernyataan B',
            'option_c' => 'Pernyataan C',
            'option_d' => 'Pernyataan D',
            'correct_options' => ['a', 'c'],
            'competency' => 'Aljabar',
            'cognitive_level' => 'penalaran',
            'points' => 20,
        ]);
        $quiz->questions()->create([
            'type' => Question::TYPE_PGK_KATEGORI,
            'body' => 'Tentukan benar atau salah.',
            'category_items' => [
                ['statement' => 'Pernyataan satu', 'answer' => 'benar'],
                ['statement' => 'Pernyataan dua', 'answer' => 'salah'],
            ],
            'points' => 30,
        ]);

        $this->actingAs($teacher)->post(route('question-banks.store', $quiz), [
            'title' => 'Bank TKA Aljabar',
        ])->assertRedirect(route('question-banks.index'));

        $bank = QuestionBank::firstOrFail();
        $this->assertSame(2, $bank->question_count);
        $this->assertSame(50, $bank->total_points);
        $this->assertSame('Matematika XI', $bank->source_classroom);
        $bankMedia = $bank->payload['groups'][0]['media_path'];
        $this->assertNotSame($group->media_path, $bankMedia);
        Storage::disk('local')->assertExists($bankMedia);

        $this->get(route('question-banks.index'))
            ->assertOk()
            ->assertSee('Lihat isi soal')
            ->assertSee(route('question-banks.show', $bank), false);
        $this->get(route('question-banks.show', $bank))
            ->assertOk()
            ->assertSee('Grafik Penjualan')
            ->assertSee('Perhatikan grafik dan tabel berikut.')
            ->assertSee('Pilih dua pernyataan yang benar.')
            ->assertSee('Pernyataan A')
            ->assertSee('✓ kunci')
            ->assertSee('Tentukan benar atau salah.')
            ->assertSee('Januari')
            ->assertSee('20')
            ->assertSee(route('question-banks.media', ['questionBank' => $bank, 'groupKey' => 'group-1']), false);
        $this->get(route('question-banks.media', ['questionBank' => $bank, 'groupKey' => 'group-1']))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=grafik.png');

        // Paket bank harus tetap utuh meskipun kuis sumber dihapus.
        $this->delete(route('quizzes.destroy', $quiz))->assertRedirect();
        Storage::disk('local')->assertMissing('quiz-stimuli/source-chart.png');
        Storage::disk('local')->assertExists($bankMedia);

        $this->post(route('question-banks.quizzes.store', $bank), [
            'classroom_id' => $classroom->id,
            'title' => 'TKA Aljabar 2027',
        ])->assertRedirect();

        $newQuiz = Quiz::where('title', 'TKA Aljabar 2027')->firstOrFail();
        $newQuiz->load(['questions.group', 'questionGroups']);
        $this->assertFalse($newQuiz->is_published);
        $this->assertNull($newQuiz->start_at);
        $this->assertNull($newQuiz->end_at);
        $this->assertSame(60, $newQuiz->duration_minutes);
        $this->assertTrue($newQuiz->shuffle_questions);
        $this->assertCount(2, $newQuiz->questions);
        $this->assertCount(1, $newQuiz->questionGroups);
        $this->assertSame(['a', 'c'], $newQuiz->questions->first()->correct_options);
        $this->assertSame($newQuiz->questionGroups->first()->id, $newQuiz->questions->first()->question_group_id);
        $this->assertSame([['Bulan', 'Nilai'], ['Januari', '20']], $newQuiz->questionGroups->first()->table_data);
        $importedMedia = $newQuiz->questionGroups->first()->media_path;
        $this->assertNotSame($bankMedia, $importedMedia);
        Storage::disk('local')->assertExists($importedMedia);

        $this->delete(route('question-banks.destroy', $bank))->assertRedirect();
        Storage::disk('local')->assertMissing($bankMedia);
        Storage::disk('local')->assertExists($importedMedia);
    }

    public function test_question_bank_is_private_to_its_teacher(): void
    {
        $owner = User::factory()->create(['role' => 'guru']);
        $otherTeacher = User::factory()->create(['role' => 'guru']);
        $student = User::factory()->create(['role' => 'siswa']);
        $classroom = Classroom::create(['teacher_id' => $owner->id, 'name' => 'Kelas Pemilik', 'color' => 'indigo']);
        $otherClassroom = Classroom::create(['teacher_id' => $otherTeacher->id, 'name' => 'Kelas Lain', 'color' => 'sky']);
        $quiz = Quiz::create(['classroom_id' => $classroom->id, 'title' => 'Kuis Rahasia']);
        $quiz->questions()->create([
            'type' => Question::TYPE_PG,
            'body' => 'Soal pemilik',
            'option_a' => 'A',
            'option_b' => 'B',
            'correct_option' => 'a',
            'points' => 10,
        ]);
        $bank = QuestionBank::create([
            'teacher_id' => $owner->id,
            'title' => 'Paket Privat',
            'question_count' => 1,
            'total_points' => 10,
            'payload' => ['version' => 1, 'quiz' => ['title' => 'Privat'], 'groups' => [], 'questions' => []],
        ]);

        $this->actingAs($otherTeacher)->get(route('question-banks.index'))
            ->assertOk()->assertDontSee('Paket Privat');
        $this->get(route('question-banks.show', $bank))->assertForbidden();
        $this->get(route('question-banks.media', ['questionBank' => $bank, 'groupKey' => 'group-1']))->assertForbidden();
        $this->delete(route('question-banks.destroy', $bank))->assertForbidden();
        $this->post(route('question-banks.quizzes.store', $bank), [
            'classroom_id' => $otherClassroom->id,
        ])->assertForbidden();
        $this->post(route('question-banks.store', $quiz), ['title' => 'Salinan'])->assertForbidden();
        $this->actingAs($student)->get(route('question-banks.index'))->assertForbidden();
    }

    public function test_empty_quiz_cannot_be_saved_to_question_bank(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Kelas Kosong', 'color' => 'indigo']);
        $quiz = Quiz::create(['classroom_id' => $classroom->id, 'title' => 'Kuis Kosong']);

        $this->actingAs($teacher)->post(route('question-banks.store', $quiz), [
            'title' => 'Tidak Boleh Tersimpan',
        ])->assertSessionHasErrors('question_bank');

        $this->assertDatabaseCount('question_banks', 0);
    }
}
