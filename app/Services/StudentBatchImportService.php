<?php

namespace App\Services;

use App\Models\User;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class StudentBatchImportService
{
    private const REQUIRED_HEADERS = ['nis', 'nama', 'tanggallahir'];

    private const HEADER_MAP = [
        'nis' => 'nis',
        'nama' => 'name',
        'tanggallahir' => 'date_of_birth',
        'email' => 'email',
        'jeniskelamin' => 'gender',
    ];

    /** @return array{created:int} */
    public function import(UploadedFile $file): array
    {
        $reader = $this->readerFor($file);
        $rows = [];
        $errors = [];
        $headers = null;
        $opened = false;

        try {
            $reader->open($file->getRealPath());
            $opened = true;

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
                        $missing = collect(self::REQUIRED_HEADERS)->reject(fn ($header) => array_key_exists($header, $headers));
                        if ($missing->isNotEmpty()) {
                            throw ValidationException::withMessages([
                                'student_file' => 'Kolom wajib tidak ditemukan: '.$missing->implode(', ').'. Gunakan template yang disediakan.',
                            ]);
                        }
                        continue;
                    }

                    if (count($rows) >= 1000) {
                        $errors[] = 'File dibatasi maksimal 1.000 siswa per import.';
                        break 2;
                    }

                    $parsed = $this->parseRow($values, $headers, (int) $rowNumber, $errors);
                    if ($parsed !== null) {
                        $rows[] = $parsed;
                    }
                }
                break;
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'student_file' => 'File tidak dapat dibaca. Pastikan file CSV/XLSX tidak rusak dan menggunakan template yang disediakan.',
            ]);
        } finally {
            if ($opened) {
                $reader->close();
            }
        }

        if ($headers === null) {
            throw ValidationException::withMessages(['student_file' => 'File tidak berisi header data siswa.']);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['student_file' => 'File belum berisi data siswa.']);
        }

        $this->validateDuplicates($rows, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'student_file' => array_slice($errors, 0, 25),
            ]);
        }

        try {
            DB::transaction(function () use ($rows) {
                foreach ($rows as $row) {
                    User::create($row + [
                        'role' => 'siswa',
                        'password' => $row['date_of_birth']->format('dmY'),
                        'must_change_password' => true,
                    ]);
                }
            });
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([
                'student_file' => 'Import dibatalkan karena NIS atau email baru saja digunakan. Muat ulang halaman dan periksa kembali data.',
            ]);
        }

        return ['created' => count($rows)];
    }

    private function readerFor(UploadedFile $file): CsvReader|XlsxReader
    {
        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            return new XlsxReader();
        }

        $options = new CsvOptions();
        $options->FIELD_DELIMITER = ',';

        return new CsvReader($options);
    }

    /** @param array<int, mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        return collect($values)->every(fn ($value) => $value === null || trim((string) $value) === '');
    }

    /** @param array<int, mixed> $values @return array<string, int> */
    private function headerIndexes(array $values): array
    {
        $indexes = [];
        foreach ($values as $index => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            if (isset(self::HEADER_MAP[$normalized])) {
                $indexes[$normalized] = $index;
            }
        }

        return $indexes;
    }

    /**
     * @param array<int, mixed> $values
     * @param array<string, int> $headers
     * @param array<int, string> $errors
     * @return array<string, mixed>|null
     */
    private function parseRow(array $values, array $headers, int $rowNumber, array &$errors): ?array
    {
        $value = fn (string $header) => isset($headers[$header]) ? ($values[$headers[$header]] ?? null) : null;
        $nis = trim((string) $value('nis'));
        $name = trim((string) $value('nama'));
        $dateOfBirth = $this->parseDate($value('tanggallahir'));
        $email = trim((string) $value('email'));
        $gender = $this->parseGender($value('jeniskelamin'));
        $rowErrors = [];

        if ($nis === '' || mb_strlen($nis) > 50 || ! preg_match('/^(?=.*[A-Za-z0-9])[A-Za-z0-9.\-\/]+$/', $nis)) {
            $rowErrors[] = 'NIS wajib diisi (maks. 50 karakter; minimal satu huruf/angka; boleh memakai titik, garis miring, atau tanda hubung)';
        }
        if ($name === '' || mb_strlen($name) > 255) {
            $rowErrors[] = 'Nama wajib diisi dan maksimal 255 karakter';
        }
        if ($dateOfBirth === null) {
            $rowErrors[] = 'Tanggal Lahir tidak valid; gunakan format dd/mm/yyyy';
        } elseif ($dateOfBirth < new DateTimeImmutable('1900-01-01') || $dateOfBirth > new DateTimeImmutable('today')) {
            $rowErrors[] = 'Tanggal Lahir harus berada antara 01/01/1900 dan hari ini';
        }
        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255)) {
            $rowErrors[] = 'Email tidak valid';
        }
        if ($value('jeniskelamin') !== null && trim((string) $value('jeniskelamin')) !== '' && $gender === null) {
            $rowErrors[] = 'Jenis Kelamin harus L atau P';
        }

        if ($rowErrors !== []) {
            $errors[] = 'Baris '.$rowNumber.': '.implode('; ', $rowErrors).'.';

            return null;
        }

        return [
            'name' => $name,
            'email' => $email !== '' ? strtolower($email) : self::generatedEmailFor($nis),
            'nomor_induk' => $nis,
            'date_of_birth' => $dateOfBirth,
            'gender' => $gender,
            '_row' => $rowNumber,
        ];
    }

    /** @param array<int, array<string, mixed>> $rows @param array<int, string> $errors */
    private function validateDuplicates(array &$rows, array &$errors): void
    {
        $nisCounts = collect($rows)->countBy('nomor_induk');
        $emailCounts = collect($rows)->countBy('email');
        $existingNis = User::whereIn('nomor_induk', collect($rows)->pluck('nomor_induk'))->pluck('nomor_induk')->flip();
        $existingEmail = User::whereIn('email', collect($rows)->pluck('email'))->pluck('email')->map(fn ($email) => strtolower($email))->flip();

        foreach ($rows as &$row) {
            if (($nisCounts[$row['nomor_induk']] ?? 0) > 1) {
                $errors[] = 'Baris '.$row['_row'].': NIS '.$row['nomor_induk'].' muncul lebih dari sekali dalam file.';
            } elseif ($existingNis->has($row['nomor_induk'])) {
                $errors[] = 'Baris '.$row['_row'].': NIS '.$row['nomor_induk'].' sudah terdaftar.';
            }

            if (($emailCounts[$row['email']] ?? 0) > 1) {
                $errors[] = 'Baris '.$row['_row'].': email '.$row['email'].' muncul lebih dari sekali dalam file.';
            } elseif ($existingEmail->has(strtolower($row['email']))) {
                $errors[] = 'Baris '.$row['_row'].': email '.$row['email'].' sudah terdaftar.';
            }

            unset($row['_row']);
        }
        unset($row);
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return new DateTimeImmutable($value->format('Y-m-d'));
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value >= 20000 && (float) $value <= 80000) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+'.((int) $value).' days');
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'dmY'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $text);
            if ($date && $date->format($format) === $text) {
                return $date;
            }
        }

        return null;
    }

    private function parseGender(mixed $value): ?string
    {
        $gender = Str::lower(trim((string) $value));

        return match ($gender) {
            '' => null,
            'l', 'laki-laki', 'laki laki', 'pria' => 'L',
            'p', 'perempuan', 'wanita' => 'P',
            default => null,
        };
    }

    public static function generatedEmailFor(string $nis): string
    {
        $local = trim((string) preg_replace('/[^A-Za-z0-9]+/', '.', $nis), '.');

        return 'siswa.'.strtolower($local).'@akun.ruangkelas.local';
    }

    private function normalizeHeader(string $header): string
    {
        return (string) Str::of($header)
            ->replace("\xEF\xBB\xBF", '')
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '');
    }
}
