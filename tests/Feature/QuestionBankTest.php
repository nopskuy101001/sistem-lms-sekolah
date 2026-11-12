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
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;
use ZipArchive;

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
        $this->actingAs($otherTeacher)->get(route('question-banks.package', $bank))->assertForbidden();
        $this->actingAs($student)->get(route('question-banks.excel', $bank))->assertForbidden();
        $this->actingAs($student)->get(route('question-banks.package', $bank))->assertForbidden();
        $this->actingAs($teacher)->get(route('question-banks.index'))
            ->assertOk()
            ->assertSee(route('question-banks.excel', $bank), false)
            ->assertSee(route('question-banks.package', $bank), false)
            ->assertDontSee('Impor ke Bank Soal');
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

    public function test_question_package_round_trip_restores_images_uploaded_video_and_youtube_url(): void
    {
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isomvideo-data";
        Storage::disk('local')->put('question-bank-stimuli/chart.png', $png);
        Storage::disk('local')->put('question-bank-stimulus-videos/explanation.mp4', $mp4);

        $teacher = User::factory()->create(['role' => 'guru']);
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Kelas Paket', 'color' => 'indigo']);
        $quiz = Quiz::create(['classroom_id' => $classroom->id, 'title' => 'Tujuan Paket']);
        $bank = QuestionBank::create([
            'teacher_id' => $teacher->id,
            'title' => 'Paket Multimedia',
            'question_count' => 3,
            'total_points' => 30,
            'payload' => [
                'version' => 1,
                'quiz' => ['title' => 'Kuis Multimedia'],
                'groups' => [
                    [
                        'key' => 'group-1', 'title' => 'Media lokal', 'stimulus' => '',
                        'media_path' => 'question-bank-stimuli/chart.png', 'media_name' => 'grafik.png',
                        'media_alt' => 'Grafik nilai', 'video_type' => 'upload',
                        'video_path' => 'question-bank-stimulus-videos/explanation.mp4',
                        'video_name' => 'penjelasan.mp4', 'video_caption' => 'Penjelasan grafik',
                    ],
                    [
                        'key' => 'group-2', 'title' => 'Video YouTube', 'stimulus' => '',
                        'video_type' => 'youtube',
                        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'video_caption' => 'Video referensi',
                    ],
                    [
                        'key' => 'group-3', 'title' => 'Video MP4 URL', 'stimulus' => '',
                        'video_type' => 'mp4_url',
                        'video_url' => 'https://cdn.example.test/stimulus/informasi.mp4',
                        'video_caption' => 'Video dari CDN',
                    ],
                ],
                'questions' => [
                    ['type' => 'pilihan_ganda', 'body' => 'Soal media lokal', 'option_a' => 'Benar', 'option_b' => 'Salah', 'correct_option' => 'a', 'points' => 10, 'group_key' => 'group-1'],
                    ['type' => 'pilihan_ganda', 'body' => 'Soal YouTube', 'option_a' => 'Benar', 'option_b' => 'Salah', 'correct_option' => 'a', 'points' => 10, 'group_key' => 'group-2'],
                    ['type' => 'pilihan_ganda', 'body' => 'Soal MP4 URL', 'option_a' => 'Benar', 'option_b' => 'Salah', 'correct_option' => 'a', 'points' => 10, 'group_key' => 'group-3'],
                ],
            ],
        ]);

        $content = $this->actingAs($teacher)->get(route('question-banks.package', $bank))
            ->assertOk()
            ->assertHeader('content-type', 'application/zip')
            ->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'question-package-test-');
        file_put_contents($path, $content);
        $archive = new ZipArchive;
        $archiveOpen = false;

        try {
            $this->assertTrue($archive->open($path) === true);
            $archiveOpen = true;
            $this->assertNotFalse($archive->locateName('soal.xlsx'));
            $this->assertNotFalse($archive->locateName('manifest.json'));
            $this->assertNotFalse($archive->locateName('media/group-1/image.png'));
            $this->assertNotFalse($archive->locateName('media/group-1/video.mp4'));
            $manifest = json_decode($archive->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('ruangkelas-question-package', $manifest['format']);
            $archive->close();
            $archiveOpen = false;

            $file = new UploadedFile($path, 'paket-multimedia.zip', 'application/zip', null, true);
            $this->post(route('questions.import', $quiz), ['question_file' => $file])
                ->assertRedirect()
                ->assertSessionHasNoErrors()
                ->assertSessionHas('success');

            $quiz->refresh()->load(['questions', 'questionGroups']);
            $this->assertCount(3, $quiz->questions);
            $this->assertCount(3, $quiz->questionGroups);
            $local = $quiz->questionGroups->firstWhere('title', 'Media lokal');
            $youtube = $quiz->questionGroups->firstWhere('title', 'Video YouTube');
            $mp4Url = $quiz->questionGroups->firstWhere('title', 'Video MP4 URL');
            $this->assertNotNull($local);
            $this->assertSame('Grafik nilai', $local->media_alt);
            $this->assertSame('upload', $local->video_type);
            $this->assertSame('Penjelasan grafik', $local->video_caption);
            Storage::disk('local')->assertExists($local->media_path);
            Storage::disk('local')->assertExists($local->video_path);
            $this->assertSame($png, Storage::disk('local')->get($local->media_path));
            $this->assertSame($mp4, Storage::disk('local')->get($local->video_path));
            $this->assertNotNull($youtube);
            $this->assertSame('youtube', $youtube->video_type);
            $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $youtube->video_url);
            $this->assertNotNull($mp4Url);
            $this->assertSame('mp4_url', $mp4Url->video_type);
            $this->assertSame('https://cdn.example.test/stimulus/informasi.mp4', $mp4Url->video_url);

            $chunkQuiz = Quiz::create(['classroom_id' => $classroom->id, 'title' => 'Tujuan Paket Bertahap']);
            $uploadId = (string) Str::uuid();
            $chunkResponse = $this->postJson(route('question-package-uploads.chunks'), [
                'upload_id' => $uploadId,
                'chunk_index' => 0,
                'total_chunks' => 1,
                'original_name' => 'paket-multimedia.zip',
                'total_size' => strlen($content),
                'chunk' => UploadedFile::fake()->createWithContent('chunk.part', $content),
            ])->assertOk()->assertJson(['complete' => true]);

            $this->post(route('questions.import', $chunkQuiz), [
                'question_package_upload_token' => $chunkResponse->json('token'),
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertCount(3, $chunkQuiz->refresh()->questions);
            $this->assertCount(3, $chunkQuiz->questionGroups);
            Storage::disk('local')->assertMissing('temporary/question-package-staging/'.$teacher->id.'/'.$uploadId.'.zip');
        } finally {
            if ($archiveOpen) {
                $archive->close();
            }
            @unlink($path);
        }
    }

    public function test_teacher_can_save_complete_tka_package_and_reuse_it_as_a_draft_quiz(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('quiz-stimuli/source-chart.png', 'image-content');
        Storage::disk('local')->put('quiz-stimulus-videos/source-video.mp4', 'video-content');

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
            'video_type' => 'upload',
            'video_path' => 'quiz-stimulus-videos/source-video.mp4',
            'video_name' => 'penjelasan.mp4',
            'video_caption' => 'Video penjelasan grafik',
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
        $bankVideo = $bank->payload['groups'][0]['video_path'];
        $this->assertNotSame($group->media_path, $bankMedia);
        $this->assertNotSame($group->video_path, $bankVideo);
        Storage::disk('local')->assertExists($bankMedia);
        Storage::disk('local')->assertExists($bankVideo);

        $this->get(route('question-banks.index'))
            ->assertOk()
            ->assertSee('Lihat isi soal')
            ->assertSee('name="start_at"', false)
            ->assertSee('name="end_at"', false)
            ->assertSee(route('question-banks.show', $bank), false);
        $this->get(route('question-banks.show', $bank))
            ->assertOk()
            ->assertSee('Jadwal kuis baru')
            ->assertSee('name="start_at"', false)
            ->assertSee('name="end_at"', false)
            ->assertSee('Grafik Penjualan')
            ->assertSee('Perhatikan grafik dan tabel berikut.')
            ->assertSee('Pilih dua pernyataan yang benar.')
            ->assertSee('Pernyataan A')
            ->assertSee('✓ kunci')
            ->assertSee('Tentukan benar atau salah.')
            ->assertSee('Januari')
            ->assertSee('20')
            ->assertSee('Video penjelasan grafik')
            ->assertSee(route('question-banks.media', ['questionBank' => $bank, 'groupKey' => 'group-1']), false)
            ->assertSee(route('question-banks.video', ['questionBank' => $bank, 'groupKey' => 'group-1']), false);
        $this->get(route('question-banks.media', ['questionBank' => $bank, 'groupKey' => 'group-1']))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=grafik.png');
        $this->get(route('question-banks.video', ['questionBank' => $bank, 'groupKey' => 'group-1']))
            ->assertOk()
            ->assertHeader('content-type', 'video/mp4');

        // Paket bank harus tetap utuh meskipun kuis sumber dihapus.
        $this->delete(route('quizzes.destroy', $quiz))->assertRedirect();
        Storage::disk('local')->assertMissing('quiz-stimuli/source-chart.png');
        Storage::disk('local')->assertMissing('quiz-stimulus-videos/source-video.mp4');
        Storage::disk('local')->assertExists($bankMedia);
        Storage::disk('local')->assertExists($bankVideo);

        $newStart = now()->addWeek()->startOfHour();
        $newEnd = $newStart->copy()->addHours(2);
        $this->post(route('question-banks.quizzes.store', $bank), [
            'classroom_id' => $classroom->id,
            'title' => 'TKA Aljabar 2027',
            'start_at' => $newStart->format('Y-m-d H:i:s'),
            'end_at' => $newEnd->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $newQuiz = Quiz::where('title', 'TKA Aljabar 2027')->firstOrFail();
        $newQuiz->load(['questions.group', 'questionGroups']);
        $this->assertFalse($newQuiz->is_published);
        $this->assertTrue($newQuiz->start_at->equalTo($newStart));
        $this->assertTrue($newQuiz->end_at->equalTo($newEnd));
        $this->assertSame(60, $newQuiz->duration_minutes);
        $this->assertTrue($newQuiz->shuffle_questions);
        $this->assertCount(2, $newQuiz->questions);
        $this->assertCount(1, $newQuiz->questionGroups);
        $this->assertSame(['a', 'c'], $newQuiz->questions->first()->correct_options);
        $this->assertSame($newQuiz->questionGroups->first()->id, $newQuiz->questions->first()->question_group_id);
        $this->assertSame([['Bulan', 'Nilai'], ['Januari', '20']], $newQuiz->questionGroups->first()->table_data);
        $importedMedia = $newQuiz->questionGroups->first()->media_path;
        $importedVideo = $newQuiz->questionGroups->first()->video_path;
        $this->assertNotSame($bankMedia, $importedMedia);
        $this->assertNotSame($bankVideo, $importedVideo);
        $this->assertSame('Video penjelasan grafik', $newQuiz->questionGroups->first()->video_caption);
        Storage::disk('local')->assertExists($importedMedia);
        Storage::disk('local')->assertExists($importedVideo);

        $this->delete(route('question-banks.destroy', $bank))->assertRedirect();
        Storage::disk('local')->assertMissing($bankMedia);
        Storage::disk('local')->assertMissing($bankVideo);
        Storage::disk('local')->assertExists($importedMedia);
        Storage::disk('local')->assertExists($importedVideo);
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

    public function test_direct_question_bank_schedule_rejects_closing_before_opening(): void
    {
        $teacher = User::factory()->create(['role' => 'guru']);
        $classroom = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'Kelas Jadwal', 'color' => 'indigo']);
        $bank = QuestionBank::create([
            'teacher_id' => $teacher->id,
            'title' => 'Paket Jadwal',
            'question_count' => 1,
            'total_points' => 10,
            'payload' => [
                'version' => 1,
                'quiz' => ['title' => 'Kuis Jadwal', 'duration_minutes' => 30],
                'groups' => [],
                'questions' => [[
                    'type' => Question::TYPE_ESAI,
                    'body' => 'Jelaskan jawabanmu.',
                    'points' => 10,
                ]],
            ],
        ]);

        $this->actingAs($teacher)->post(route('question-banks.quizzes.store', $bank), [
            'classroom_id' => $classroom->id,
            'title' => 'Jadwal tidak valid',
            'start_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'end_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('end_at');

        $this->assertDatabaseMissing('quizzes', ['title' => 'Jadwal tidak valid']);
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
