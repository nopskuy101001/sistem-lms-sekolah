<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\QuestionImportTemplateXlsxExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class QuestionBulkImportTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->create(['role' => 'guru']);
        $this->student = User::factory()->create(['role' => 'siswa']);
        $classroom = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Kelas Import',
            'color' => 'indigo',
        ]);
        $this->quiz = Quiz::create(['classroom_id' => $classroom->id, 'title' => 'Kuis Import']);
    }

    public function test_teacher_can_download_csv_and_excel_question_templates(): void
    {
        $csv = $this->actingAs($this->teacher)->get(route('questions.import.template.csv'))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFsep=,\r\n", $csv);
        $this->assertStringContainsString('Tipe,Pertanyaan,"Pilihan A"', $csv);

        $xlsx = $this->get(route('questions.import.template.xlsx'))
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'question-template-test-');
        file_put_contents($path, $xlsx);
        $reader = new Reader;

        try {
            $reader->open($path);
            $sheets = array_values(iterator_to_array($reader->getSheetIterator()));
            $this->assertSame('Import Soal', $sheets[0]->getName());
            $rows = array_values(iterator_to_array($sheets[0]->getRowIterator()));
            $this->assertSame(QuestionImportTemplateXlsxExporter::HEADERS, $rows[0]->toArray());
            $this->assertSame('Petunjuk', $sheets[1]->getName());
            $this->assertSame('Contoh', $sheets[2]->getName());
        } finally {
            $reader->close();
            @unlink($path);
        }
    }

    public function test_teacher_can_import_all_question_types_and_shared_stimulus_from_csv(): void
    {
        $csv = "\xEF\xBB\xBFsep=,\r\n";
        $rows = [
            QuestionImportTemplateXlsxExporter::HEADERS,
            ['PG', 'Ibu kota Indonesia?', 'Jakarta', 'Bandung', '', '', 'A', '', '', 10, 'Pemahaman', 'Pengetahuan', '', '', '', '', ''],
            ['PG Kompleks', 'Pilih simpulan benar.', 'Satu', 'Dua', 'Tiga', '', 'A|C', '', '', 15, 'Penalaran', '', '', 'DATA-1', 'Data Nilai', 'Baca tabel.', "Nama|Nilai\nAyu|90"],
            ['Kategori', 'Tentukan kategori.', '', '', '', '', '', 'Nilai Ayu di atas 80||Nilai Ayu 70', 'Benar|Salah', 15, 'Penerapan', '', '', 'DATA-1', '', '', ''],
            ['Esai', 'Jelaskan kesimpulan.', '', '', '', '', '', '', '', 20, '', '', '', '', '', '', ''],
        ];
        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '\\', "\r\n");
        }
        rewind($stream);
        $csv .= stream_get_contents($stream);
        fclose($stream);

        $this->actingAs($this->teacher)->post(route('questions.import', $this->quiz), [
            'question_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->quiz->refresh()->load(['questions', 'questionGroups']);
        $this->assertCount(4, $this->quiz->questions);
        $this->assertCount(1, $this->quiz->questionGroups);
        $this->assertSame(['a', 'c'], $this->quiz->questions->where('type', Question::TYPE_PGK_MCMA)->first()->correct_options);
        $this->assertCount(2, $this->quiz->questions->where('type', Question::TYPE_PGK_KATEGORI)->first()->category_items);
        $group = $this->quiz->questionGroups->first();
        $this->assertSame([['Nama', 'Nilai'], ['Ayu', '90']], $group->table_data);
        $this->assertSame(2, $this->quiz->questions->where('question_group_id', $group->id)->count());
    }

    public function test_teacher_can_import_questions_from_xlsx(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'question-import-test-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(QuestionImportTemplateXlsxExporter::HEADERS));
        $writer->addRow(Row::fromValues(['PG', 'Dua tambah dua?', '3', '4', '5', '', 'B', '', '', 10]));
        $writer->close();

        try {
            $file = new UploadedFile($path, 'soal.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $this->actingAs($this->teacher)->post(route('questions.import', $this->quiz), ['question_file' => $file])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('questions', [
                'quiz_id' => $this->quiz->id,
                'body' => 'Dua tambah dua?',
                'correct_option' => 'b',
            ]);
        } finally {
            @unlink($path);
        }
    }

    public function test_invalid_file_is_rejected_without_partial_import(): void
    {
        $csv = "Tipe,Pertanyaan,Pilihan A,Pilihan B,Kunci Jawaban,Poin\r\n"
            ."PG,Soal valid,A,B,A,10\r\n"
            ."PG,Soal rusak,A,B,A|Z,10\r\n";

        $this->actingAs($this->teacher)->post(route('questions.import', $this->quiz), [
            'question_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
        ])->assertRedirect()->assertSessionHasErrors('question_file');

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_import_is_private_and_locked_after_attempt_exists(): void
    {
        $otherTeacher = User::factory()->create(['role' => 'guru']);
        $csv = "Tipe,Pertanyaan,Pilihan A,Pilihan B,Kunci Jawaban,Poin\r\nPG,Soal,A,B,A,10\r\n";
        $payload = ['question_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv)];

        $this->actingAs($otherTeacher)->post(route('questions.import', $this->quiz), $payload)->assertForbidden();

        QuizAttempt::create([
            'quiz_id' => $this->quiz->id,
            'user_id' => $this->student->id,
            'started_at' => now(),
        ]);
        $this->actingAs($this->teacher)->post(route('questions.import', $this->quiz), [
            'question_file' => UploadedFile::fake()->createWithContent('soal.csv', $csv),
        ])->assertRedirect()->assertSessionHasErrors('question_file');
        $this->assertDatabaseCount('questions', 0);
    }
}
