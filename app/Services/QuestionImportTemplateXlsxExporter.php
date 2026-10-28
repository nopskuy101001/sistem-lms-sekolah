<?php

namespace App\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class QuestionImportTemplateXlsxExporter
{
    public const HEADERS = [
        'Tipe', 'Pertanyaan', 'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D',
        'Kunci Jawaban', 'Pernyataan Kategori', 'Jawaban Kategori', 'Poin',
        'Level Kognitif', 'Kompetensi', 'Subkompetensi', 'Kode Stimulus',
        'Judul Stimulus', 'Teks Stimulus', 'Tabel Stimulus',
    ];

    public function write(string $path): void
    {
        $options = new Options;
        $options->SHOULD_USE_INLINE_STRINGS = false;
        foreach ([16, 45, 24, 24, 24, 24, 18, 42, 24, 10, 20, 28, 28, 18, 28, 45, 45] as $index => $width) {
            $options->setColumnWidth($width, $index + 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $headerStyle = (new Style)
                ->setFontName('Calibri')->setFontSize(11)->setFontBold()
                ->setFontColor(Color::WHITE)->setBackgroundColor(Color::rgb(31, 41, 55))
                ->setCellAlignment(CellAlignment::CENTER)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
                ->setShouldWrapText();
            $titleStyle = (new Style)->setFontName('Calibri')->setFontSize(14)->setFontBold()
                ->setFontColor(Color::rgb(17, 24, 39));
            $textStyle = (new Style)->setFontName('Calibri')->setFontSize(11)->setShouldWrapText()
                ->setCellVerticalAlignment(CellVerticalAlignment::TOP);

            $dataSheet = $writer->getCurrentSheet();
            $dataSheet->setName('Import Soal');
            $writer->addRow(Row::fromValues(self::HEADERS, $headerStyle)->setHeight(35));

            $guide = $writer->addNewSheetAndMakeItCurrent();
            $guide->setName('Petunjuk');
            $writer->addRow(Row::fromValues(['PETUNJUK IMPORT SOAL MASSAL'], $titleStyle)->setHeight(28));
            foreach ($this->instructions() as $instruction) {
                $writer->addRow(Row::fromValues([$instruction], $textStyle)->setHeight(30));
            }

            $examples = $writer->addNewSheetAndMakeItCurrent();
            $examples->setName('Contoh');
            $writer->addRow(Row::fromValues(self::HEADERS, $headerStyle)->setHeight(35));
            foreach ($this->examples() as $example) {
                $writer->addRow(Row::fromValues($example, $textStyle)->setHeight(60));
            }
        } finally {
            $writer->close();
        }
    }

    /** @return array<int, string> */
    private function instructions(): array
    {
        return [
            'Isi soal hanya pada sheet "Import Soal" dan jangan mengubah nama kolom. Sheet "Contoh" tidak ikut diimport.',
            'Kolom wajib untuk semua soal: Tipe, Pertanyaan, dan Poin. Maksimal 500 soal per file.',
            'Tipe yang diterima: PG, PG Kompleks, Kategori, atau Esai.',
            'Untuk PG isi minimal Pilihan A dan B, lalu Kunci Jawaban satu huruf. Contoh: A.',
            'Untuk PG Kompleks, pisahkan beberapa kunci dengan tanda |. Contoh: A|C.',
            'Untuk Kategori, pisahkan pernyataan dengan baris baru atau tanda ||. Jawaban memakai Benar|Salah dalam urutan yang sama.',
            'Level Kognitif bersifat opsional: Pemahaman, Penerapan, atau Penalaran.',
            'Kode Stimulus menghubungkan beberapa soal ke stimulus yang sama. Isi detail stimulus pada kemunculan pertama, lalu cukup ulangi kodenya.',
            'Tabel Stimulus: satu baris tabel per baris baru dan pisahkan kolom dengan tanda |. Minimal 2x2, maksimal 30x12.',
            'Template import tidak memasukkan gambar. Untuk stimulus bergambar/grafik, gunakan form stimulus dan soal biasa.',
            'Jika ada satu baris tidak valid, seluruh import dibatalkan agar isi kuis tetap konsisten.',
        ];
    }

    /** @return array<int, array<int, string|int>> */
    private function examples(): array
    {
        return [
            ['PG', 'Ibu kota Indonesia adalah ...', 'Jakarta', 'Bandung', 'Surabaya', 'Medan', 'A', '', '', 10, 'Pemahaman', 'Pengetahuan umum', '', '', '', '', ''],
            ['PG Kompleks', 'Pilih dua simpulan yang benar.', 'Simpulan A', 'Simpulan B', 'Simpulan C', 'Simpulan D', 'A|C', '', '', 15, 'Penalaran', 'Literasi data', 'Interpretasi tabel', 'STIM-01', 'Data Penjualan', 'Perhatikan data penjualan berikut.', "Bulan|Jumlah\nJanuari|120\nFebruari|150"],
            ['Kategori', 'Tentukan benar atau salah.', '', '', '', '', '', 'Nilai Februari lebih tinggi dari Januari||Selisihnya 20', 'Benar|Salah', 15, 'Penerapan', 'Literasi data', '', 'STIM-01', '', '', ''],
            ['Esai', 'Jelaskan alasan jawaban Anda.', '', '', '', '', '', '', '', 20, 'Penalaran', '', '', '', '', '', ''],
        ];
    }
}
