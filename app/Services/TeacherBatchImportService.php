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
use OpenSpout\Reader\XLSX\Reader;

class TeacherBatchImportService
{
    private const HEADER_MAP = [
        'nourut' => 'source_sequence',
        'nama' => 'name',
        'nip' => 'nip',
        'pangkatgolruang' => 'rank_grade',
        'pangkattmt' => 'rank_effective_date',
        'jabatannama' => 'position_name',
        'jabatantmt' => 'position_effective_date',
        'masakerjath' => 'service_years',
        'masakerjatahun' => 'service_years',
        'masakerjabln' => 'service_months',
        'masakerjabulan' => 'service_months',
        'latihanjabatannama' => 'training_name',
        'latihanjabatanblnth' => 'training_period',
        'latihanjabatanbulantahun' => 'training_period',
        'latihanjabatanjumlahjamharian' => 'training_hours',
        'latihanjabatanjumlahjam' => 'training_hours',
        'pendidikannama' => 'education_field',
        'pendidikanlulustahun' => 'education_graduation_year',
        'pendidikantingkatijazah' => 'education_degree_level',
        'tglblnthn' => 'date_of_birth',
        'tanggallahir' => 'date_of_birth',
        'catatanmutasikepegawaian' => 'employment_mutation_notes',
        'keterangan' => 'remarks',
    ];

    /** @return array{created:int} */
    public function import(UploadedFile $file): array
    {
        $reader = new Reader;
        $rows = [];
        $errors = [];
        $topHeader = null;
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
                    if ($topHeader === null) {
                        if ($this->looksLikeTopHeader($values)) {
                            $topHeader = $values;
                        }

                        continue;
                    }
                    if ($headers === null) {
                        $headers = $this->headerIndexes($topHeader, $values);
                        $missing = collect(['name', 'nip', 'date_of_birth'])
                            ->reject(fn (string $key) => array_key_exists($key, $headers));
                        if ($missing->isNotEmpty()) {
                            throw ValidationException::withMessages([
                                'teacher_file' => 'Kolom wajib tidak ditemukan: '.$missing->map(fn (string $key) => match ($key) {
                                    'name' => 'Nama', 'nip' => 'NIP', 'date_of_birth' => 'Tgl-Bln-Thn',
                                })->implode(', ').'.',
                            ]);
                        }

                        continue;
                    }
                    if ($this->isColumnNumberRow($values)) {
                        continue;
                    }
                    if (count($rows) >= 1000) {
                        $errors[] = 'File dibatasi maksimal 1.000 guru per import.';
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
            report($exception);
            throw ValidationException::withMessages([
                'teacher_file' => 'File guru tidak dapat dibaca. Pastikan format XLSX dan dua baris header tidak berubah.',
            ]);
        } finally {
            if ($opened) {
                $reader->close();
            }
        }

        if ($headers === null || $rows === []) {
            throw ValidationException::withMessages(['teacher_file' => 'File belum berisi data guru yang dapat diimport.']);
        }

        $this->validateDuplicates($rows, $errors);
        if ($errors !== []) {
            throw ValidationException::withMessages(['teacher_file' => array_slice($errors, 0, 25)]);
        }

        try {
            DB::transaction(function () use ($rows): void {
                foreach ($rows as $row) {
                    $user = User::create($row['user'] + [
                        'role' => 'guru',
                        'password' => $row['user']['date_of_birth']->format('dmY'),
                        'must_change_password' => true,
                    ]);
                    $profile = $user->teacherProfile()->create($row['profile']);
                    if ($this->hasValues($row['rank'])) {
                        $profile->rankHistories()->create($row['rank'] + ['is_current' => true]);
                    }
                    if ($this->hasValues($row['position'])) {
                        $profile->positionHistories()->create($row['position'] + ['is_current' => true]);
                    }
                    if ($this->hasValues($row['training'])) {
                        $profile->trainings()->create($row['training']);
                    }
                    if ($this->hasValues($row['education'])) {
                        $profile->educations()->create($row['education'] + ['is_primary' => true]);
                    }
                }
            });
        } catch (QueryException $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'teacher_file' => 'Import gagal disimpan. Periksa duplikasi NIP/email dan pastikan migrasi database sudah dijalankan.',
            ]);
        }

        return ['created' => count($rows)];
    }

    /** @param array<int, mixed> $values */
    private function looksLikeTopHeader(array $values): bool
    {
        return $this->normalizeHeader((string) ($values[0] ?? '')) === 'nourut'
            && $this->normalizeHeader((string) ($values[1] ?? '')) === 'nama'
            && $this->normalizeHeader((string) ($values[2] ?? '')) === 'nip';
    }

    /** @param array<int, mixed> $top @param array<int, mixed> $sub @return array<string, int> */
    private function headerIndexes(array $top, array $sub): array
    {
        $indexes = [];
        $parent = '';
        $count = max(count($top), count($sub));
        for ($index = 0; $index < $count; $index++) {
            $topValue = trim((string) ($top[$index] ?? ''));
            if ($topValue !== '') {
                $parent = $topValue;
            }
            $child = trim((string) ($sub[$index] ?? ''));
            $combined = $this->normalizeHeader($parent.' '.$child);
            if (isset(self::HEADER_MAP[$combined])) {
                $indexes[self::HEADER_MAP[$combined]] = $index;
            }
        }

        return $indexes;
    }

    /** @param array<int, mixed> $values */
    private function isColumnNumberRow(array $values): bool
    {
        return trim((string) ($values[0] ?? '')) === '1'
            && trim((string) ($values[1] ?? '')) === '2'
            && trim((string) ($values[2] ?? '')) === '3';
    }

    /** @param array<int, mixed> $values @param array<string, int> $headers @param array<int, string> $errors */
    private function parseRow(array $values, array $headers, int $rowNumber, array &$errors): ?array
    {
        $value = fn (string $key) => isset($headers[$key]) ? ($values[$headers[$key]] ?? null) : null;
        $name = trim((string) $value('name'));
        $nipOriginal = trim((string) $value('nip'));
        $nip = self::normalizeNip($nipOriginal);
        $dateOfBirth = $this->parseDate($value('date_of_birth'));
        $rankDate = $this->parseOptionalDate($value('rank_effective_date'));
        $positionDate = $this->parseOptionalDate($value('position_effective_date'));
        [$trainingMonth, $trainingYear, $trainingPeriodValid] = $this->parseMonthYear($value('training_period'));
        $serviceYears = $this->parseInteger($value('service_years'));
        $serviceMonths = $this->parseInteger($value('service_months'));
        $trainingHours = $this->parseInteger($value('training_hours'));
        $graduationYear = $this->parseInteger($value('education_graduation_year'));
        $rowErrors = [];

        if ($name === '' || mb_strlen($name) > 255) {
            $rowErrors[] = 'Nama wajib diisi dan maksimal 255 karakter';
        }
        if ($nip === '' || strlen($nip) !== 18) {
            $rowErrors[] = 'NIP harus menghasilkan tepat 18 angka';
        }
        if ($dateOfBirth === null) {
            $rowErrors[] = 'Tanggal lahir tidak valid';
        }
        if ($this->hasText($value('rank_effective_date')) && $rankDate === null) {
            $rowErrors[] = 'TMT Pangkat tidak valid';
        }
        if ($this->hasText($value('position_effective_date')) && $positionDate === null) {
            $rowErrors[] = 'TMT Jabatan tidak valid';
        }
        if (! $trainingPeriodValid) {
            $rowErrors[] = 'Bulan & Tahun Latihan Jabatan tidak valid';
        }
        if ($this->hasText($value('service_years')) && $serviceYears === null) {
            $rowErrors[] = 'Masa kerja tahun harus berupa angka';
        }
        if ($serviceMonths !== null && ($serviceMonths < 0 || $serviceMonths > 11)) {
            $rowErrors[] = 'Masa kerja bulan harus 0 sampai 11';
        }
        if ($this->hasText($value('training_hours')) && $trainingHours === null) {
            $rowErrors[] = 'Jumlah jam latihan harus berupa angka';
        }
        if ($graduationYear !== null && ($graduationYear < 1900 || $graduationYear > (int) date('Y') + 1)) {
            $rowErrors[] = 'Tahun lulus tidak valid';
        }

        if ($rowErrors !== []) {
            $errors[] = 'Baris '.$rowNumber.': '.implode('; ', $rowErrors).'.';

            return null;
        }

        return [
            '_row' => $rowNumber,
            'user' => [
                'name' => $name,
                'email' => self::generatedEmailFor($nip),
                'username' => null,
                'nomor_induk' => $nip,
                'date_of_birth' => $dateOfBirth,
            ],
            'profile' => [
                'nip_original' => $nipOriginal,
                'source_sequence' => $this->optionalText($value('source_sequence')),
                'service_years' => $serviceYears,
                'service_months' => $serviceMonths,
                'employment_mutation_notes' => $this->optionalText($value('employment_mutation_notes')),
                'remarks' => $this->optionalText($value('remarks')),
            ],
            'rank' => ['rank_grade' => $this->optionalText($value('rank_grade')), 'effective_date' => $rankDate],
            'position' => ['position_name' => $this->optionalText($value('position_name')), 'effective_date' => $positionDate],
            'training' => [
                'training_name' => $this->optionalText($value('training_name')),
                'training_month' => $trainingMonth,
                'training_year' => $trainingYear,
                'duration_hours' => $trainingHours,
            ],
            'education' => [
                'education_field' => $this->optionalText($value('education_field')),
                'graduation_year' => $graduationYear,
                'degree_level' => $this->optionalText($value('education_degree_level')),
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $rows @param array<int, string> $errors */
    private function validateDuplicates(array $rows, array &$errors): void
    {
        $nips = collect($rows)->pluck('user.nomor_induk');
        $counts = $nips->countBy();
        $existing = User::query()->whereIn('nomor_induk', $nips)->pluck('nomor_induk')->flip();
        foreach ($rows as $row) {
            $nip = $row['user']['nomor_induk'];
            if (($counts[$nip] ?? 0) > 1) {
                $errors[] = 'Baris '.$row['_row'].': NIP '.$nip.' muncul lebih dari sekali.';
            } elseif ($existing->has($nip)) {
                $errors[] = 'Baris '.$row['_row'].': NIP '.$nip.' sudah terdaftar.';
            }
        }
    }

    public static function normalizeNip(string $nip): string
    {
        return (string) preg_replace('/\D+/u', '', $nip);
    }

    public static function generatedEmailFor(string $nip): string
    {
        return 'guru.'.$nip.'@akun.ruangkelas.local';
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return new DateTimeImmutable($value->format('Y-m-d'));
        }
        if (is_numeric($value) && (float) $value >= 1 && (float) $value <= 80000) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+'.((int) $value).' days');
        }
        $text = trim((string) $value);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $text);
            if ($date && $date->format($format) === $text) {
                return $date;
            }
        }

        return null;
    }

    private function parseOptionalDate(mixed $value): ?DateTimeImmutable
    {
        return $this->hasText($value) ? $this->parseDate($value) : null;
    }

    /** @return array{?int, ?int, bool} */
    private function parseMonthYear(mixed $value): array
    {
        if (! $this->hasText($value)) {
            return [null, null, true];
        }
        if ($value instanceof DateTimeInterface) {
            return [(int) $value->format('n'), (int) $value->format('Y'), true];
        }
        if (preg_match('/^(\d{1,2})[\-\/]\s*(\d{4})$/', trim((string) $value), $match)) {
            $month = (int) $match[1];

            return [$month, (int) $match[2], $month >= 1 && $month <= 12];
        }

        return [null, null, false];
    }

    private function parseInteger(mixed $value): ?int
    {
        if (! $this->hasText($value)) {
            return null;
        }
        $text = trim((string) $value);

        return preg_match('/^\d+$/', $text) ? (int) $text : null;
    }

    /** @param array<int, mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        return collect($values)->every(fn (mixed $value) => ! $this->hasText($value));
    }

    private function hasText(mixed $value): bool
    {
        return $value instanceof DateTimeInterface || trim((string) $value) !== '';
    }

    private function optionalText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /** @param array<string, mixed> $values */
    private function hasValues(array $values): bool
    {
        return collect($values)->contains(fn (mixed $value) => $value !== null && $value !== '');
    }

    private function normalizeHeader(string $header): string
    {
        return (string) Str::of($header)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '');
    }
}
