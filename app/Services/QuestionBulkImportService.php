<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Quiz;
use App\Support\VideoSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;
use ZipArchive;

class QuestionBulkImportService
{
    private const MAX_QUESTIONS = 500;

    private const MAX_PACKAGE_BYTES = 125_829_120;

    private const MAX_PACKAGE_ENTRIES = 1000;

    private const REQUIRED_HEADERS = ['type', 'body', 'points'];

    private const HEADER_MAP = [
        'tipe' => 'type',
        'bentuksoal' => 'type',
        'pertanyaan' => 'body',
        'soal' => 'body',
        'pilihana' => 'option_a',
        'pilihanb' => 'option_b',
        'pilihanc' => 'option_c',
        'pilihand' => 'option_d',
        'pilihane' => 'option_e',
        'kuncijawaban' => 'answer_key',
        'kunci' => 'answer_key',
        'pernyataankategori' => 'category_statements',
        'jawabankategori' => 'category_answers',
        'poin' => 'points',
        'levelkognitif' => 'cognitive_level',
        'kompetensi' => 'competency',
        'subkompetensi' => 'subcompetency',
        'kodestimulus' => 'stimulus_code',
        'judulstimulus' => 'stimulus_title',
        'teksstimulus' => 'stimulus_text',
        'tabelstimulus' => 'stimulus_table',
        'filegambarstimulus' => 'stimulus_image_file',
        'altgambarstimulus' => 'stimulus_image_alt',
        'urlvideostimulus' => 'stimulus_video_url',
        'filevideostimulus' => 'stimulus_video_file',
        'keteranganvideostimulus' => 'stimulus_video_caption',
    ];

    /** @return array{questions:int,groups:int} */
    public function import(Quiz $quiz, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension === 'zip') {
            return $this->importPackage($quiz, $file);
        }

        return $this->importSpreadsheet($quiz, $file->getRealPath(), $extension);
    }

    /** @return array{questions:int,groups:int} */
    private function importSpreadsheet(Quiz $quiz, string $path, string $extension, ?string $packageRoot = null): array
    {
        $reader = $this->readerFor($extension);
        $questions = [];
        $groups = [];
        $errors = [];
        $headers = null;

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    $values = $row->toArray();
                    if ($this->isEmptyRow($values)) {
                        continue;
                    }
                    if ($headers === null && str_starts_with(strtolower(trim((string) ($values[0] ?? ''))), 'sep=')) {
                        continue;
                    }
                    if ($headers === null) {
                        $headers = $this->headerIndexes($values);
                        $missing = collect(self::REQUIRED_HEADERS)
                            ->reject(fn (string $header) => in_array($header, $headers, true));

                        if ($missing->isNotEmpty()) {
                            throw ValidationException::withMessages([
                                'question_file' => 'Kolom wajib tidak ditemukan: '.$missing->map(fn ($header) => match ($header) {
                                    'type' => 'Tipe',
                                    'body' => 'Pertanyaan',
                                    default => 'Poin',
                                })->implode(', ').'. Gunakan template yang disediakan.',
                            ]);
                        }

                        continue;
                    }

                    if (count($questions) >= self::MAX_QUESTIONS) {
                        $errors[] = 'File dibatasi maksimal '.self::MAX_QUESTIONS.' soal per import.';
                        break 2;
                    }

                    $parsed = $this->parseRow($values, $headers, (int) $rowNumber, $groups, $errors, $packageRoot);
                    if ($parsed !== null) {
                        $questions[] = $parsed;
                    }
                }
                break;
            }
        } finally {
            $reader->close();
        }

        if ($headers === null) {
            throw ValidationException::withMessages(['question_file' => 'File tidak berisi header data soal.']);
        }
        if ($questions === []) {
            throw ValidationException::withMessages(['question_file' => $errors ?: 'File belum berisi data soal.']);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['question_file' => array_slice($errors, 0, 25)]);
        }

        $storedMedia = [];
        try {
            foreach ($groups as &$group) {
                $this->storeGroupMedia($group, $storedMedia);
            }
            unset($group);

            DB::transaction(function () use ($quiz, $groups, $questions): void {
                $groupIds = [];
                foreach ($groups as $code => $group) {
                    unset($group['_image_source'], $group['_video_source']);
                    $groupIds[$code] = $quiz->questionGroups()->create($group)->id;
                }

                foreach ($questions as $question) {
                    $code = $question['_stimulus_code'];
                    unset($question['_stimulus_code']);
                    $question['question_group_id'] = $code !== null ? $groupIds[$code] : null;
                    $quiz->questions()->create($question);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedMedia);
            throw $exception;
        }

        return ['questions' => count($questions), 'groups' => count($groups)];
    }

    private function readerFor(string $extension): CsvReader|XlsxReader
    {
        if ($extension === 'xlsx') {
            return new XlsxReader;
        }

        $options = new CsvOptions;
        $options->FIELD_DELIMITER = ',';

        return new CsvReader($options);
    }

    /** @param array<int, mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        return collect($values)->every(fn ($value) => $value === null || trim((string) $value) === '');
    }

    /** @param array<int, mixed> $values @return array<int, string> */
    private function headerIndexes(array $values): array
    {
        $headers = [];
        foreach ($values as $index => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            if (isset(self::HEADER_MAP[$normalized])) {
                $headers[$index] = self::HEADER_MAP[$normalized];
            }
        }

        return $headers;
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<int, string>  $headers
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function parseRow(
        array $values,
        array $headers,
        int $rowNumber,
        array &$groups,
        array &$errors,
        ?string $packageRoot = null,
    ): ?array {
        $data = [];
        foreach ($headers as $index => $field) {
            $data[$field] = trim((string) ($values[$index] ?? ''));
        }

        $type = $this->normalizeType($data['type'] ?? '');
        $body = $data['body'] ?? '';
        $points = filter_var($data['points'] ?? null, FILTER_VALIDATE_INT);
        $cognitiveLevel = Str::lower($data['cognitive_level'] ?? '');
        $rowErrors = [];

        if ($type === null) {
            $rowErrors[] = 'Tipe harus PG, PG Kompleks, Kategori, atau Esai';
        }
        if ($body === '' || mb_strlen($body) > 5000) {
            $rowErrors[] = 'Pertanyaan wajib diisi dan maksimal 5.000 karakter';
        }
        if ($points === false || $points < 1 || $points > 1000) {
            $rowErrors[] = 'Poin harus berupa bilangan bulat 1 sampai 1.000';
        }
        if ($cognitiveLevel !== '' && ! in_array($cognitiveLevel, ['pemahaman', 'penerapan', 'penalaran'], true)) {
            $rowErrors[] = 'Level Kognitif harus Pemahaman, Penerapan, atau Penalaran';
        }
        foreach (['competency' => 'Kompetensi', 'subcompetency' => 'Subkompetensi'] as $field => $label) {
            if (mb_strlen($data[$field] ?? '') > 255) {
                $rowErrors[] = $label.' maksimal 255 karakter';
            }
        }

        $question = [
            'type' => $type,
            'body' => $body,
            'option_a' => null,
            'option_b' => null,
            'option_c' => null,
            'option_d' => null,
            'option_e' => null,
            'correct_option' => null,
            'correct_options' => null,
            'category_items' => null,
            'points' => $points === false ? 0 : $points,
            'cognitive_level' => $cognitiveLevel !== '' ? $cognitiveLevel : null,
            'competency' => filled($data['competency'] ?? null) ? $data['competency'] : null,
            'subcompetency' => filled($data['subcompetency'] ?? null) ? $data['subcompetency'] : null,
        ];

        if (in_array($type, [Question::TYPE_PG, Question::TYPE_PGK_MCMA], true)) {
            foreach (Question::OPTION_KEYS as $key) {
                $option = $data['option_'.$key] ?? '';
                if (mb_strlen($option) > 500) {
                    $rowErrors[] = 'Pilihan '.strtoupper($key).' maksimal 500 karakter';
                }
                $question['option_'.$key] = $option !== '' ? $option : null;
            }
            if ($question['option_a'] === null || $question['option_b'] === null) {
                $rowErrors[] = 'Pilihan A dan B wajib diisi';
            }

            $answerKeys = $this->parseAnswerKeys($data['answer_key'] ?? '');
            if ($type === Question::TYPE_PG) {
                if (count($answerKeys) !== 1 || blank($question['option_'.$answerKeys[0]] ?? null)) {
                    $rowErrors[] = 'Kunci Jawaban PG harus satu huruf A-E yang pilihannya terisi';
                } else {
                    $question['correct_option'] = $answerKeys[0];
                }
            } elseif (count($answerKeys) < 2 || collect($answerKeys)->contains(fn ($key) => blank($question['option_'.$key] ?? null))) {
                $rowErrors[] = 'Kunci PG Kompleks harus minimal dua huruf A-E yang pilihannya terisi, contoh A|C';
            } else {
                $question['correct_options'] = $answerKeys;
            }
        } elseif ($type === Question::TYPE_PGK_KATEGORI) {
            $statements = $this->parseStatements($data['category_statements'] ?? '');
            $answers = collect(preg_split('/[|;,]+/', Str::lower($data['category_answers'] ?? '')))
                ->map(fn ($value) => trim((string) $value))->filter()->values()->all();

            if (count($statements) < 2 || count($statements) > 10) {
                $rowErrors[] = 'Pernyataan Kategori harus berisi 2-10 pernyataan, dipisahkan baris baru atau tanda ||';
            } elseif (count($statements) !== count($answers) || collect($answers)->contains(fn ($answer) => ! in_array($answer, ['benar', 'salah'], true))) {
                $rowErrors[] = 'Setiap pernyataan harus memiliki jawaban Benar/Salah dengan urutan yang sama';
            } else {
                $question['category_items'] = collect($statements)->map(fn ($statement, $index) => [
                    'statement' => $statement,
                    'answer' => $answers[$index],
                ])->all();
            }
        }

        $code = trim($data['stimulus_code'] ?? '');
        $hasStimulusData = collect([
            'stimulus_title', 'stimulus_text', 'stimulus_table', 'stimulus_image_file',
            'stimulus_image_alt', 'stimulus_video_url', 'stimulus_video_file', 'stimulus_video_caption',
        ])
            ->contains(fn ($field) => filled($data[$field] ?? null));
        $normalizedCode = $code !== '' ? Str::lower($code) : null;

        if ($code === '' && $hasStimulusData) {
            $rowErrors[] = 'Kode Stimulus wajib diisi jika data stimulus digunakan';
        } elseif ($code !== '') {
            if (mb_strlen($code) > 50) {
                $rowErrors[] = 'Kode Stimulus maksimal 50 karakter';
            }
            if (mb_strlen($data['stimulus_title'] ?? '') > 200) {
                $rowErrors[] = 'Judul Stimulus maksimal 200 karakter';
            }
            if (mb_strlen($data['stimulus_text'] ?? '') > 20000) {
                $rowErrors[] = 'Teks Stimulus maksimal 20.000 karakter';
            }
            if (mb_strlen($data['stimulus_image_alt'] ?? '') > 255) {
                $rowErrors[] = 'Alt Gambar Stimulus maksimal 255 karakter';
            }
            if (mb_strlen($data['stimulus_video_caption'] ?? '') > 255) {
                $rowErrors[] = 'Keterangan Video Stimulus maksimal 255 karakter';
            }

            $tableData = null;
            if (filled($data['stimulus_table'] ?? null)) {
                $tableData = $this->parseTable($data['stimulus_table'], $rowErrors);
            }

            $imageSource = $this->packageMedia(
                $data['stimulus_image_file'] ?? '',
                $packageRoot,
                ['jpg', 'jpeg', 'png', 'webp'],
                4_194_304,
                'gambar',
                $rowErrors,
            );
            $videoSource = $this->packageMedia(
                $data['stimulus_video_file'] ?? '',
                $packageRoot,
                ['mp4'],
                104_857_600,
                'video',
                $rowErrors,
            );
            $videoUrl = trim($data['stimulus_video_url'] ?? '');
            $videoType = null;
            if ($videoUrl !== '') {
                $videoType = VideoSource::youtubeId($videoUrl) !== null
                    ? 'youtube'
                    : (VideoSource::isMp4Url($videoUrl) ? 'mp4_url' : null);
                if ($videoType === null) {
                    $rowErrors[] = 'URL Video Stimulus harus berupa URL YouTube atau tautan langsung .mp4';
                }
            }
            if ($videoSource && $videoUrl !== '') {
                $rowErrors[] = 'Pilih salah satu sumber video stimulus: URL atau file MP4 dalam paket';
            }

            $candidate = [
                'title' => filled($data['stimulus_title'] ?? null) ? $data['stimulus_title'] : null,
                'stimulus' => trim($data['stimulus_text'] ?? ''),
                'table_data' => $tableData,
                'media_path' => null,
                'media_name' => $imageSource ? basename((string) ($data['stimulus_image_file'] ?? '')) : null,
                'media_alt' => filled($data['stimulus_image_alt'] ?? null) ? $data['stimulus_image_alt'] : null,
                'video_type' => $videoSource ? 'upload' : $videoType,
                'video_url' => $videoUrl !== '' ? $videoUrl : null,
                'video_path' => null,
                'video_name' => $videoSource ? basename((string) ($data['stimulus_video_file'] ?? '')) : null,
                'video_caption' => filled($data['stimulus_video_caption'] ?? null) ? $data['stimulus_video_caption'] : null,
                '_image_source' => $imageSource,
                '_video_source' => $videoSource,
            ];
            if (! isset($groups[$normalizedCode])) {
                if (! $hasStimulusData || (
                    blank($candidate['stimulus'])
                    && $candidate['table_data'] === null
                    && $imageSource === null
                    && $videoSource === null
                    && $videoUrl === ''
                )) {
                    $rowErrors[] = 'Kemunculan pertama Kode Stimulus harus memiliki teks, tabel, gambar, atau video stimulus';
                } else {
                    $groups[$normalizedCode] = $candidate;
                }
            } elseif ($hasStimulusData && $candidate !== $groups[$normalizedCode]) {
                $rowErrors[] = 'Data untuk Kode Stimulus '.$code.' berbeda dari baris sebelumnya; kosongkan data stimulus pada pemakaian berikutnya';
            }
        }

        if ($rowErrors !== []) {
            $errors[] = 'Baris '.$rowNumber.': '.implode('; ', array_unique($rowErrors)).'.';

            return null;
        }

        $question['_stimulus_code'] = $normalizedCode;

        return $question;
    }

    /** @return array{questions:int,groups:int} */
    private function importPackage(Quiz $quiz, UploadedFile $file): array
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ruangkelas-question-package-'.Str::uuid();
        File::ensureDirectoryExists($directory);
        $zip = new ZipArchive;
        $zipOpen = false;

        try {
            if ($zip->open($file->getRealPath()) !== true) {
                throw ValidationException::withMessages(['question_file' => 'Paket ZIP tidak dapat dibuka atau rusak.']);
            }
            $zipOpen = true;
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_PACKAGE_ENTRIES) {
                throw ValidationException::withMessages(['question_file' => 'Jumlah file di dalam Paket ZIP tidak valid.']);
            }

            $totalBytes = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
                $totalBytes += (int) ($stat['size'] ?? 0);
                if (! $this->safeArchiveEntry($name)
                    || (! str_ends_with($name, '/')
                        && $name !== QuestionBankPackageService::WORKBOOK
                        && $name !== QuestionBankPackageService::MANIFEST
                        && ! str_starts_with($name, 'media/'))) {
                    throw ValidationException::withMessages(['question_file' => 'Paket ZIP memuat path atau file yang tidak diizinkan: '.$name]);
                }
                if ($totalBytes > self::MAX_PACKAGE_BYTES) {
                    throw ValidationException::withMessages(['question_file' => 'Isi Paket ZIP melebihi batas 120 MB.']);
                }
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));
                if (str_ends_with($name, '/')) {
                    continue;
                }
                $destination = $directory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
                File::ensureDirectoryExists(dirname($destination));
                $input = $zip->getStream($name);
                $output = fopen($destination, 'wb');
                if (! is_resource($input) || ! is_resource($output)) {
                    if (is_resource($input)) {
                        fclose($input);
                    }
                    if (is_resource($output)) {
                        fclose($output);
                    }
                    throw ValidationException::withMessages(['question_file' => 'Salah satu file dalam Paket ZIP tidak dapat dibaca.']);
                }
                stream_copy_to_stream($input, $output);
                fclose($input);
                fclose($output);
            }
            $zip->close();
            $zipOpen = false;

            $manifestPath = $directory.DIRECTORY_SEPARATOR.QuestionBankPackageService::MANIFEST;
            if (! is_file($manifestPath)) {
                throw ValidationException::withMessages(['question_file' => 'manifest.json tidak ditemukan dalam Paket ZIP.']);
            }
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (! is_array($manifest)
                || ($manifest['format'] ?? null) !== 'ruangkelas-question-package'
                || ($manifest['version'] ?? null) !== 1
                || ($manifest['workbook'] ?? null) !== QuestionBankPackageService::WORKBOOK) {
                throw ValidationException::withMessages(['question_file' => 'Format atau versi Paket ZIP tidak didukung.']);
            }
            $this->verifyManifestMedia($manifest, $directory);

            $workbook = $directory.DIRECTORY_SEPARATOR.QuestionBankPackageService::WORKBOOK;
            if (! is_file($workbook)) {
                throw ValidationException::withMessages(['question_file' => 'soal.xlsx tidak ditemukan dalam Paket ZIP.']);
            }

            return $this->importSpreadsheet($quiz, $workbook, 'xlsx', $directory);
        } finally {
            if ($zipOpen) {
                $zip->close();
            }
            File::deleteDirectory($directory);
        }
    }

    /** @param array<int, string> $extensions @param array<int, string> $errors */
    private function packageMedia(
        string $reference,
        ?string $packageRoot,
        array $extensions,
        int $maxBytes,
        string $label,
        array &$errors,
    ): ?string {
        $reference = str_replace('\\', '/', trim($reference));
        if ($reference === '') {
            return null;
        }
        if ($packageRoot === null) {
            $errors[] = 'File '.ucfirst($label).' Stimulus hanya dapat digunakan melalui Paket ZIP';

            return null;
        }
        if (! $this->safeArchiveEntry($reference) || ! str_starts_with($reference, 'media/')) {
            $errors[] = 'Path file '.$label.' stimulus tidak aman atau berada di luar folder media';

            return null;
        }

        $path = realpath($packageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $reference));
        $root = realpath($packageRoot);
        if ($path === false || $root === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            $errors[] = 'File '.$label.' stimulus tidak ditemukan di dalam Paket ZIP: '.$reference;

            return null;
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, $extensions, true) || filesize($path) > $maxBytes) {
            $errors[] = 'File '.$label.' stimulus memiliki tipe atau ukuran yang tidak diizinkan';

            return null;
        }
        if ($label === 'gambar') {
            $mime = (string) mime_content_type($path);
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $errors[] = 'File gambar stimulus tidak dikenali sebagai JPG, PNG, atau WebP';

                return null;
            }
        } elseif (! $this->looksLikeMp4($path)) {
            $errors[] = 'File video stimulus tidak dikenali sebagai MP4 yang valid';

            return null;
        }

        return $path;
    }

    /** @param array<string, mixed> $group @param array<int, string> $storedMedia */
    private function storeGroupMedia(array &$group, array &$storedMedia): void
    {
        foreach ([
            ['source' => '_image_source', 'path' => 'media_path', 'directory' => 'quiz-stimuli'],
            ['source' => '_video_source', 'path' => 'video_path', 'directory' => 'quiz-stimulus-videos'],
        ] as $media) {
            $source = $group[$media['source']] ?? null;
            if (! is_string($source) || $source === '') {
                continue;
            }

            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            $target = $media['directory'].'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');
            $stream = fopen($source, 'rb');
            $stored = is_resource($stream) && Storage::disk('local')->put($target, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (! $stored) {
                throw ValidationException::withMessages(['question_file' => 'Media stimulus gagal disimpan ke LMS.']);
            }

            $group[$media['path']] = $target;
            $storedMedia[] = $target;
        }
    }

    /** @param array<string, mixed> $manifest */
    private function verifyManifestMedia(array $manifest, string $packageRoot): void
    {
        foreach ($manifest['media'] ?? [] as $groupMedia) {
            foreach (['image', 'video'] as $kind) {
                $item = $groupMedia[$kind] ?? null;
                if (! is_array($item) || blank($item['path'] ?? null)) {
                    continue;
                }
                $reference = str_replace('\\', '/', (string) $item['path']);
                if (! $this->safeArchiveEntry($reference) || ! str_starts_with($reference, 'media/')) {
                    throw ValidationException::withMessages(['question_file' => 'Manifest memuat path media yang tidak aman.']);
                }
                $path = $packageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $reference);
                if (! is_file($path) || ! hash_equals((string) ($item['sha256'] ?? ''), hash_file('sha256', $path))) {
                    throw ValidationException::withMessages(['question_file' => 'Media Paket ZIP hilang atau berubah: '.$reference]);
                }
            }
        }
    }

    private function safeArchiveEntry(string $name): bool
    {
        return $name !== ''
            && ! str_starts_with($name, '/')
            && ! preg_match('/^[A-Za-z]:\//', $name)
            && ! str_contains($name, "\0")
            && ! preg_match('#(^|/)\.\.(/|$)#', $name);
    }

    private function looksLikeMp4(string $path): bool
    {
        $stream = fopen($path, 'rb');
        if (! is_resource($stream)) {
            return false;
        }
        $header = fread($stream, 4096);
        fclose($stream);

        return is_string($header) && strpos($header, 'ftyp') !== false;
    }

    private function normalizeType(string $type): ?string
    {
        $type = $this->normalizeHeader($type);

        return match ($type) {
            'pg', 'pilihanganda', 'pilihangandasederhana' => Question::TYPE_PG,
            'pgkompleks', 'pgk', 'mcma', 'pilihangandakompleks', 'multijawaban' => Question::TYPE_PGK_MCMA,
            'kategori', 'benarsalah', 'pgkategori', 'pgkomplekskategori' => Question::TYPE_PGK_KATEGORI,
            'esai', 'essay' => Question::TYPE_ESAI,
            default => null,
        };
    }

    /** @return array<int, string> */
    private function parseAnswerKeys(string $value): array
    {
        return collect(preg_split('/[|,;\s]+/', Str::lower($value)))
            ->map(fn ($key) => trim((string) $key))
            ->filter()
            ->unique()->sort()->values()->all();
    }

    /** @return array<int, string> */
    private function parseStatements(string $value): array
    {
        $parts = preg_match('/\r\n|\r|\n/', $value)
            ? preg_split('/\r\n|\r|\n/', $value)
            : preg_split('/\s*\|\|\s*/', $value);

        return collect($parts)->map(fn ($part) => trim((string) $part))->filter()->values()->all();
    }

    /** @param array<int, string> $errors @return array<int, array<int, string>>|null */
    private function parseTable(string $input, array &$errors): ?array
    {
        if (mb_strlen($input) > 20000) {
            $errors[] = 'Tabel Stimulus maksimal 20.000 karakter';

            return null;
        }

        $lines = collect(preg_split('/\r\n|\r|\n/', trim($input)))
            ->filter(fn ($line) => trim((string) $line) !== '')->values();
        $first = (string) $lines->first();
        $delimiter = str_contains($first, "\t") ? "\t" : (str_contains($first, '|') ? '|' : (str_contains($first, ';') ? ';' : ','));
        $rows = $lines->map(function ($line) use ($delimiter) {
            $cells = $delimiter === "\t" ? explode("\t", $line) : str_getcsv($line, $delimiter);

            return collect($cells)->map(fn ($cell) => trim((string) $cell))->values()->all();
        })->all();
        $columnCount = collect($rows)->max(fn ($row) => count($row)) ?? 0;

        if (count($rows) < 2 || $columnCount < 2) {
            $errors[] = 'Tabel Stimulus harus memiliki minimal dua baris dan dua kolom';

            return null;
        }
        if (count($rows) > 30 || $columnCount > 12) {
            $errors[] = 'Tabel Stimulus dibatasi maksimal 30 baris dan 12 kolom';

            return null;
        }

        return collect($rows)->map(fn ($row) => array_pad($row, $columnCount, ''))->all();
    }

    private function normalizeHeader(string $header): string
    {
        return (string) Str::of($header)
            ->replace("\xEF\xBB\xBF", '')
            ->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '');
    }
}
