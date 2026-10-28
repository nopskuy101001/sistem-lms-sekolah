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
    public const HEADERS = ['NIS', 'Nama', 'Tanggal Lahir', 'Email', 'Jenis Kelamin', 'No. Telepon', 'Alamat'];

    public function write(string $path): void
    {
        $options = new Options();
        $options->SHOULD_USE_INLINE_STRINGS = false;
        $options->setColumnWidth(18, 1);
        $options->setColumnWidth(28, 2);
        $options->setColumnWidth(18, 3);
        $options->setColumnWidth(30, 4);
        $options->setColumnWidth(16, 5);
        $options->setColumnWidth(18, 6);
        $options->setColumnWidth(42, 7);

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $headerStyle = (new Style())
                ->setFontName('Calibri')->setFontSize(11)->setFontBold()
                ->setFontColor(Color::WHITE)->setBackgroundColor(Color::rgb(31, 41, 55))
                ->setCellAlignment(CellAlignment::CENTER)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
            $instructionTitle = (new Style())
                ->setFontName('Calibri')->setFontSize(14)->setFontBold()
                ->setFontColor(Color::rgb(17, 24, 39));
            $instructionStyle = (new Style())
                ->setFontName('Calibri')->setFontSize(11)->setShouldWrapText();

            $dataSheet = $writer->getCurrentSheet();
            $dataSheet->setName('Data Siswa');
            $writer->addRow(Row::fromValues(self::HEADERS, $headerStyle)->setHeight(26));

            $guide = $writer->addNewSheetAndMakeItCurrent();
            $guide->setName('Petunjuk');
            $writer->addRow(Row::fromValues(['PETUNJUK IMPORT SISWA'], $instructionTitle)->setHeight(28));
            foreach ([
                'Isi data pada sheet "Data Siswa" tanpa mengubah nama kolom.',
                'Kolom wajib: NIS, Nama, dan Tanggal Lahir.',
                'Format Tanggal Lahir: dd/mm/yyyy. Contoh: 22/03/2005.',
                'Jenis Kelamin diisi L atau P. Email, No. Telepon, dan Alamat boleh dikosongkan.',
                'Password awal otomatis memakai tanggal lahir format ddmmyyyy. Contoh: 22032005.',
                'Siswa wajib mengganti password setelah login pertama.',
                'Simpan NIS dan No. Telepon sebagai teks agar angka nol di depan tidak hilang.',
                'Maksimal 1.000 siswa dalam satu file import.',
            ] as $instruction) {
                $writer->addRow(Row::fromValues([$instruction], $instructionStyle)->setHeight(24));
            }
        } finally {
            $writer->close();
        }
    }
}
