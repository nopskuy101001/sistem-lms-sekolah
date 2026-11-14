<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Enumerable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class StudentDataXlsxExporter
{
    /** @param Enumerable<int, User> $students */
    public function write(string $path, Enumerable $students): void
    {
        $options = new Options;
        $options->SHOULD_USE_INLINE_STRINGS = false;
        foreach ([8, 30, 20, 8, 18, 20, 18, 22, 20, 30] as $index => $width) {
            $options->setColumnWidth($width, $index + 1);
        }

        $headerStyle = (new Style)
            ->setFontName('Calibri')->setFontSize(11)->setFontBold()
            ->setFontColor(Color::WHITE)->setBackgroundColor(Color::rgb(31, 41, 55))
            ->setCellAlignment(CellAlignment::CENTER);

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName('Data Siswa');
            $writer->addRow(Row::fromValues(StudentImportTemplateXlsxExporter::HEADERS, $headerStyle)->setHeight(26));

            foreach ($students->values() as $index => $student) {
                $writer->addRow(Row::fromValues([
                    $student->source_number ?: (string) ($index + 1),
                    $student->name,
                    $student->nipd ?: $student->nomor_induk,
                    $student->gender,
                    $student->nisn,
                    $student->birth_place,
                    $student->date_of_birth?->format('Y-m-d'),
                    $student->nik,
                    $student->current_rombel,
                    $student->email,
                ]));
            }
        } finally {
            $writer->close();
        }
    }
}
