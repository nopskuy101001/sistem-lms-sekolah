<?php

namespace App\Services;

use App\Models\User;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class StudentBatchImportService
{
    private const REQUIRED_HEADERS = ['name', 'date_of_birth'];

    private const HEADER_MAP = [
        'no' => 'source_number',
        'nomor' => 'source_number',
        'nis' => 'legacy_nis',
        'nipd' => 'nipd',
        'nama' => 'name',
        'tanggallahir' => 'date_of_birth',
        'email' => 'email',
        'jk' => 'gender',
        'jeniskelamin' => 'gender',
        'nisn' => 'nisn',
        'tempatlahir' => 'birth_place',
        'nik' => 'nik',
        'rombel' => 'current_rombel',
        'rombelsaatini' => 'current_rombel',
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
                        $missing = collect(self::REQUIRED_HEADERS)
                            ->reject(fn ($header) => array_key_exists($header, $headers));
                        if (! array_key_exists('nipd', $headers) && ! array_key_exists('legacy_nis', $headers)) {
                            $missing->prepend('nipd');
                        }
                        if ($missing->isNotEmpty()) {
                            $labels = $missing->map(fn (string $header) => match ($header) {
                                'nipd' => 'NIPD',
                                'name' => 'Nama',
                                'date_of_birth' => 'Tanggal Lahir',
                                default => $header,
                            });
                            throw ValidationException::withMessages([
                                'student_file' => 'Kolom wajib tidak ditemukan: '.$labels->implode(', ').'. Gunakan template yang disediakan.',
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
                'student_file' => 'Import dibatalkan karena username, NIPD, NISN, atau email baru saja digunakan. Muat ulang halaman dan periksa kembali data.',
            ]);
        }

        return ['created' => count($rows)];
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

    /** @param array<int, mixed> $values @return array<string, int> */
    private function headerIndexes(array $values): array
    {
        $indexes = [];
        foreach ($values as $index => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            if (isset(self::HEADER_MAP[$normalized])) {
                $canonical = self::HEADER_MAP[$normalized];
                $indexes[$canonical] ??= $index;
            }
        }

        return $indexes;
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<string, int>  $headers
     * @param  array<int, string>  $errors
     * @return array<string, mixed>|null
     */
    private function parseRow(array $values, array $headers, int $rowNumber, array &$errors): ?array
    {
        $value = fn (string $header) => isset($headers[$header]) ? ($values[$headers[$header]] ?? null) : null;
        $usesNipd = array_key_exists('nipd', $headers);
        $rawIdentifier = trim((string) $value($usesNipd ? 'nipd' : 'legacy_nis'));
        $loginIdentifier = $usesNipd ? self::normalizeNipd($rawIdentifier) : $rawIdentifier;
        $name = trim((string) $value('name'));
        $dateOfBirth = $this->parseDate($value('date_of_birth'));
        $email = trim((string) $value('email'));
        $gender = $this->parseGender($value('gender'));
        $sourceNumber = $this->optionalText($value('source_number'));
        $nisn = $this->optionalText($value('nisn'));
        $birthPlace = $this->optionalText($value('birth_place'));
        $nik = $this->optionalText($value('nik'));
        $currentRombel = $this->optionalText($value('current_rombel'));
        $rowErrors = [];

        if ($usesNipd) {
            if ($rawIdentifier === '' || mb_strlen($rawIdentifier) > 50 || ! preg_match('/^(?=.*\d)[0-9.\-\/\s]+$/', $rawIdentifier)) {
                $rowErrors[] = 'NIPD wajib diisi (maks. 50 karakter; berisi angka serta boleh memakai titik, garis miring, tanda hubung, atau spasi)';
            } elseif ($loginIdentifier === '' || mb_strlen($loginIdentifier) > 50) {
                $rowErrors[] = 'NIPD tidak menghasilkan username angka yang valid';
            }
        } elseif ($rawIdentifier === '' || mb_strlen($rawIdentifier) > 50 || ! preg_match('/^(?=.*[A-Za-z0-9])[A-Za-z0-9.\-\/]+$/', $rawIdentifier)) {
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
        if ($value('gender') !== null && trim((string) $value('gender')) !== '' && $gender === null) {
            $rowErrors[] = 'Jenis Kelamin harus L atau P';
        }
        if ($sourceNumber !== null && mb_strlen($sourceNumber) > 30) {
            $rowErrors[] = 'No maksimal 30 karakter';
        }
        if ($nisn !== null && (! preg_match('/^\d{4,20}$/', $nisn))) {
            $rowErrors[] = 'NISN harus berisi 4 sampai 20 angka';
        }
        if ($birthPlace !== null && mb_strlen($birthPlace) > 100) {
            $rowErrors[] = 'Tempat Lahir maksimal 100 karakter';
        }
        if ($nik !== null && ! preg_match('/^\d{16}$/', $nik)) {
            $rowErrors[] = 'NIK harus berisi tepat 16 angka';
        }
        if ($currentRombel !== null && mb_strlen($currentRombel) > 100) {
            $rowErrors[] = 'Rombel Saat Ini maksimal 100 karakter';
        }

        if ($rowErrors !== []) {
            $errors[] = 'Baris '.$rowNumber.': '.implode('; ', $rowErrors).'.';

            return null;
        }

        return [
            'name' => $name,
            'email' => $email !== '' ? strtolower($email) : self::generatedEmailFor($loginIdentifier),
            'nomor_induk' => $loginIdentifier,
            'nipd' => $rawIdentifier,
            'nisn' => $nisn,
            'birth_place' => $birthPlace,
            'nik' => $nik,
            'current_rombel' => $currentRombel,
            'source_number' => $sourceNumber,
            'date_of_birth' => $dateOfBirth,
            'gender' => $gender,
            '_row' => $rowNumber,
        ];
    }

    /** @param array<int, array<string, mixed>> $rows @param array<int, string> $errors */
    private function validateDuplicates(array &$rows, array &$errors): void
    {
        $nisCounts = collect($rows)->countBy('nomor_induk');
        $nipdCounts = collect($rows)->pluck('nipd')->filter()->countBy();
        $nisnCounts = collect($rows)->pluck('nisn')->filter()->countBy();
        $nikCounts = collect($rows)->pluck('nik')->filter()->countBy();
        $emailCounts = collect($rows)->countBy('email');
        $existingNis = User::whereIn('nomor_induk', collect($rows)->pluck('nomor_induk'))->pluck('nomor_induk')->flip();
        $existingNipd = User::whereIn('nipd', collect($rows)->pluck('nipd')->filter())->pluck('nipd')->flip();
        $existingNisn = User::whereIn('nisn', collect($rows)->pluck('nisn')->filter())->pluck('nisn')->flip();
        $existingEmail = User::whereIn('email', collect($rows)->pluck('email'))->pluck('email')->map(fn ($email) => strtolower($email))->flip();

        foreach ($rows as &$row) {
            if (($nisCounts[$row['nomor_induk']] ?? 0) > 1) {
                $errors[] = 'Baris '.$row['_row'].': username '.$row['nomor_induk'].' muncul lebih dari sekali setelah normalisasi NIPD.';
            } elseif ($existingNis->has($row['nomor_induk'])) {
                $errors[] = 'Baris '.$row['_row'].': username '.$row['nomor_induk'].' sudah terdaftar.';
            }

            if (($nipdCounts[$row['nipd']] ?? 0) > 1) {
                $errors[] = 'Baris '.$row['_row'].': NIPD '.$row['nipd'].' muncul lebih dari sekali dalam file.';
            } elseif ($existingNipd->has($row['nipd'])) {
                $errors[] = 'Baris '.$row['_row'].': NIPD '.$row['nipd'].' sudah terdaftar.';
            }

            if ($row['nisn'] !== null && ($nisnCounts[$row['nisn']] ?? 0) > 1) {
                $errors[] = 'Baris '.$row['_row'].': NISN '.$row['nisn'].' muncul lebih dari sekali dalam file.';
            } elseif ($row['nisn'] !== null && $existingNisn->has($row['nisn'])) {
                $errors[] = 'Baris '.$row['_row'].': NISN '.$row['nisn'].' sudah terdaftar.';
            }

            if ($row['nik'] !== null && ($nikCounts[$row['nik']] ?? 0) > 1) {
                $errors[] = 'Baris '.$row['_row'].': NIK muncul lebih dari sekali dalam file.';
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

    public static function normalizeNipd(string $nipd): string
    {
        return (string) preg_replace('/\D+/u', '', $nipd);
    }

    private function optionalText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
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
