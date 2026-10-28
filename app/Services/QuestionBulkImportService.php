<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class QuestionBulkImportService
{
    private const MAX_QUESTIONS = 500;

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
    ];

    /** @return array{questions:int,groups:int} */
    public function import(Quiz $quiz, UploadedFile $file): array
    {
        $reader = $this->readerFor($file);
        $questions = [];
        $groups = [];
        $errors = [];
        $headers = null;

        try {
            $reader->open($file->getRealPath());

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

                    $parsed = $this->parseRow($values, $headers, (int) $rowNumber, $groups, $errors);
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

        DB::transaction(function () use ($quiz, $groups, $questions): void {
            $groupIds = [];
            foreach ($groups as $code => $group) {
                $groupIds[$code] = $quiz->questionGroups()->create($group)->id;
            }

            foreach ($questions as $question) {
                $code = $question['_stimulus_code'];
                unset($question['_stimulus_code']);
                $question['question_group_id'] = $code !== null ? $groupIds[$code] : null;
                $quiz->questions()->create($question);
            }
        });

        return ['questions' => count($questions), 'groups' => count($groups)];
    }

    private function readerFor(UploadedFile $file): CsvReader|XlsxReader
    {
        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
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
    private function parseRow(array $values, array $headers, int $rowNumber, array &$groups, array &$errors): ?array
    {
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
            'correct_option' => null,
            'correct_options' => null,
            'category_items' => null,
            'points' => $points === false ? 0 : $points,
            'cognitive_level' => $cognitiveLevel !== '' ? $cognitiveLevel : null,
            'competency' => filled($data['competency'] ?? null) ? $data['competency'] : null,
            'subcompetency' => filled($data['subcompetency'] ?? null) ? $data['subcompetency'] : null,
        ];

        if (in_array($type, [Question::TYPE_PG, Question::TYPE_PGK_MCMA], true)) {
            foreach (['a', 'b', 'c', 'd'] as $key) {
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
                    $rowErrors[] = 'Kunci Jawaban PG harus satu huruf A-D yang pilihannya terisi';
                } else {
                    $question['correct_option'] = $answerKeys[0];
                }
            } elseif (count($answerKeys) < 2 || collect($answerKeys)->contains(fn ($key) => blank($question['option_'.$key] ?? null))) {
                $rowErrors[] = 'Kunci PG Kompleks harus minimal dua huruf A-D yang pilihannya terisi, contoh A|C';
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
        $hasStimulusData = collect(['stimulus_title', 'stimulus_text', 'stimulus_table'])
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

            $tableData = null;
            if (filled($data['stimulus_table'] ?? null)) {
                $tableData = $this->parseTable($data['stimulus_table'], $rowErrors);
            }

            $candidate = [
                'title' => filled($data['stimulus_title'] ?? null) ? $data['stimulus_title'] : null,
                'stimulus' => trim($data['stimulus_text'] ?? ''),
                'table_data' => $tableData,
            ];
            if (! isset($groups[$normalizedCode])) {
                if (! $hasStimulusData || (blank($candidate['stimulus']) && $candidate['table_data'] === null)) {
                    $rowErrors[] = 'Kemunculan pertama Kode Stimulus harus memiliki Teks Stimulus atau Tabel Stimulus';
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
