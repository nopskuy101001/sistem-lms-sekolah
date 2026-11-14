<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Enumerable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class TeacherDataXlsxExporter
{
    public const TOP_HEADERS = [
        'NO URUT', 'NAMA', 'NIP', 'PANGKAT', '', 'JABATAN', '', 'MASA KERJA', '',
        'LATIHAN JABATAN', '', '', 'PENDIDIKAN', '', '', 'TGL-BLN-THN',
        'CATATAN MUTASI KEPEGAWAIAN', 'KETERANGAN',
    ];

    public const SUB_HEADERS = [
        '', '', '', 'GOL. RUANG', 'TMT', 'NAMA', 'TMT', 'TH', 'BLN', 'NAMA',
        'BLN & TH', 'JUMLAH JAM (HARIAN)', 'NAMA', 'LULUS TAHUN', 'TINGKAT IJAZAH', '', '', '',
    ];

    /** @param Enumerable<int, User> $teachers */
    public function write(string $path, Enumerable $teachers, bool $withGuide = false): void
    {
        $options = new Options;
        $options->SHOULD_USE_INLINE_STRINGS = false;
        foreach ([10, 32, 25, 14, 16, 24, 16, 10, 10, 18, 16, 22, 24, 14, 16, 16, 32, 24] as $index => $width) {
            $options->setColumnWidth($width, $index + 1);
        }
        foreach ([[3, 1, 4, 1], [5, 1, 6, 1], [7, 1, 8, 1], [9, 1, 11, 1], [12, 1, 14, 1]] as $merge) {
            $options->mergeCells(...$merge);
        }
        foreach ([0, 1, 2, 15, 16, 17] as $column) {
            $options->mergeCells($column, 1, $column, 2);
        }

        $headerStyle = (new Style)
            ->setFontName('Calibri')->setFontSize(10)->setFontBold()
            ->setFontColor(Color::WHITE)->setBackgroundColor(Color::rgb(31, 41, 55))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText();
        $numberStyle = (new Style)
            ->setFontName('Calibri')->setFontSize(9)
            ->setBackgroundColor(Color::rgb(226, 232, 240))
            ->setCellAlignment(CellAlignment::CENTER);

        $writer = new Writer($options);
        $writer->openToFile($path);
        try {
            $writer->getCurrentSheet()->setName('Data Guru');
            $writer->addRow(Row::fromValues(self::TOP_HEADERS, $headerStyle)->setHeight(28));
            $writer->addRow(Row::fromValues(self::SUB_HEADERS, $headerStyle)->setHeight(28));
            $writer->addRow(Row::fromValues(range(1, 18), $numberStyle)->setHeight(20));

            foreach ($teachers->values() as $index => $teacher) {
                $profile = $teacher->teacherProfile;
                $rank = $profile?->rankHistories->firstWhere('is_current', true) ?: $profile?->rankHistories->first();
                $position = $profile?->positionHistories->firstWhere('is_current', true) ?: $profile?->positionHistories->first();
                $training = $profile?->trainings->first();
                $education = $profile?->educations->firstWhere('is_primary', true) ?: $profile?->educations->first();
                $trainingPeriod = $training?->training_month && $training?->training_year
                    ? sprintf('%02d-%04d', $training->training_month, $training->training_year)
                    : null;

                $writer->addRow(Row::fromValues([
                    $profile?->source_sequence ?: (string) ($index + 1),
                    $teacher->name,
                    $profile?->nip_original ?: $teacher->nomor_induk,
                    $rank?->rank_grade,
                    $rank?->effective_date?->format('d/m/Y'),
                    $position?->position_name,
                    $position?->effective_date?->format('d/m/Y'),
                    $profile?->service_years,
                    $profile?->service_months,
                    $training?->training_name,
                    $trainingPeriod,
                    $training?->duration_hours,
                    $education?->education_field,
                    $education?->graduation_year,
                    $education?->degree_level,
                    $teacher->date_of_birth?->format('d-m-Y'),
                    $profile?->employment_mutation_notes,
                    $profile?->remarks,
                ]));
            }

            if ($withGuide) {
                $guide = $writer->addNewSheetAndMakeItCurrent();
                $guide->setName('Petunjuk');
                foreach ([
                    'PETUNJUK IMPORT GURU',
                    'Jangan mengubah dua baris kategori dan subkategori pada sheet Data Guru.',
                    'Kolom wajib: Nama, NIP, dan Tgl-Bln-Thn. Kolom lain boleh dikosongkan.',
                    'NIP asli disimpan; login awal menggunakan NIP yang hanya berisi angka.',
                    'Password awal menggunakan tanggal lahir format ddmmyyyy dan wajib diganti.',
                    'Baris nomor kolom 1–18 dipertahankan dan otomatis dilewati saat import.',
                    'Format tanggal boleh berupa dd/mm/yyyy, dd-mm-yyyy, yyyy-mm-dd, atau tanggal Excel.',
                    'Maksimal 1.000 guru dalam satu file import.',
                ] as $line) {
                    $writer->addRow(Row::fromValues([$line]));
                }
            }
        } finally {
            $writer->close();
        }
    }
}
