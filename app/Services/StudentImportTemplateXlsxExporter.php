<?php

namespace App\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class StudentImportTemplateXlsxExporter
{
    public const HEADERS = [
        'No',
        'Nama',
        'NIPD',
        'JK',
        'NISN',
        'Tempat Lahir',
        'Tanggal Lahir',
        'NIK',
        'Rombel Saat Ini',
        'Email',
    ];

    public function write(string $path): void
    {
        $options = new Options;
        $options->SHOULD_USE_INLINE_STRINGS = false;
        $options->setColumnWidth(8, 1);
        $options->setColumnWidth(30, 2);
        $options->setColumnWidth(20, 3);
        $options->setColumnWidth(8, 4);
        $options->setColumnWidth(18, 5);
        $options->setColumnWidth(20, 6);
        $options->setColumnWidth(18, 7);
        $options->setColumnWidth(22, 8);
        $options->setColumnWidth(20, 9);
        $options->setColumnWidth(30, 10);

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $headerStyle = (new Style)
                ->setFontName('Calibri')->setFontSize(11)->setFontBold()
                ->setFontColor(Color::WHITE)->setBackgroundColor(Color::rgb(31, 41, 55))
                ->setCellAlignment(CellAlignment::CENTER)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
            $instructionTitle = (new Style)
                ->setFontName('Calibri')->setFontSize(14)->setFontBold()
                ->setFontColor(Color::rgb(17, 24, 39));
            $instructionStyle = (new Style)
                ->setFontName('Calibri')->setFontSize(11)->setShouldWrapText();

            $dataSheet = $writer->getCurrentSheet();
            $dataSheet->setName('Data Siswa');
            $writer->addRow(Row::fromValues(self::HEADERS, $headerStyle)->setHeight(26));

            $guide = $writer->addNewSheetAndMakeItCurrent();
            $guide->setName('Petunjuk');
            $writer->addRow(Row::fromValues(['PETUNJUK IMPORT SISWA'], $instructionTitle)->setHeight(28));
            foreach ([
                'Isi data pada sheet "Data Siswa" tanpa mengubah nama kolom.',
                'Kolom wajib: NIPD, Nama, dan Tanggal Lahir. Kolom lain boleh dikosongkan.',
                'Format Tanggal Lahir dapat berupa dd/mm/yyyy atau yyyy-mm-dd.',
                'Username dibuat otomatis hanya dari angka NIPD. Contoh 13494/496.042 menjadi 13494496042.',
                'NIPD asli tetap disimpan. JK diisi L atau P. Email boleh dikosongkan.',
                'NISN dan NIK harus disimpan sebagai teks agar angka nol di depan tidak hilang.',
                'NIK disimpan terenkripsi di database.',
                'Password awal otomatis memakai tanggal lahir format ddmmyyyy. Contoh: 22032005.',
                'Siswa wajib mengganti password setelah login pertama.',
                'Maksimal 1.000 siswa dalam satu file import.',
            ] as $instruction) {
                $writer->addRow(Row::fromValues([$instruction], $instructionStyle)->setHeight(24));
            }
        } finally {
            $writer->close();
        }
    }
}
