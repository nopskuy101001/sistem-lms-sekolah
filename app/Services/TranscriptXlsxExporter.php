<?php

namespace App\Services;

use App\Models\User;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer;

class TranscriptXlsxExporter
{
    public function write(string $path, array $report): void
    {
        $columnCount = 4 + $report['activities']->count();
        $lastColumnIndex = $columnCount - 1;

        $options = new Options();
        $options->SHOULD_USE_INLINE_STRINGS = false;
        $options->DEFAULT_ROW_STYLE = (new Style())
            ->setFontName('Calibri')
            ->setFontSize(11)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
        $options->setProperties(new Properties(
            title: 'Transkrip Nilai '.$report['classroom']->name,
            subject: 'Laporan nilai tugas, kuis, dan video interaktif',
            application: 'RuangKelas',
            creator: $report['classroom']->teacher->name,
            lastModifiedBy: $report['classroom']->teacher->name,
            description: 'Transkrip nilai siswa yang diekspor dari RuangKelas',
        ));
        $options->setColumnWidth(7, 1);
        $options->setColumnWidth(18, 2);
        $options->setColumnWidth(28, 3);
        $options->setColumnWidthForRange(20, 4, $columnCount);
        $options->mergeCells(0, 1, $lastColumnIndex, 1);
        $options->mergeCells(0, 2, $lastColumnIndex, 2);
        $options->mergeCells(0, 3, $lastColumnIndex, 3);

        $writer = new Writer($options);
        $writer->openToFile($path);

        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Transkrip Nilai');
            $sheet->setSheetView(
                (new SheetView())->setFreezeRow(6)->setFreezeColumn('D')->setZoomScale(90)
            );

            $titleStyle = (new Style())
                ->setFontName('Calibri')->setFontSize(16)->setFontBold()
                ->setFontColor(Color::rgb(17, 24, 39));
            $metaStyle = (new Style())
                ->setFontName('Calibri')->setFontSize(10)
                ->setFontColor(Color::rgb(71, 85, 105));
            $headerStyle = (new Style())
                ->setFontName('Calibri')->setFontSize(10)->setFontBold()
                ->setFontColor(Color::WHITE)->setBackgroundColor(Color::rgb(31, 41, 55))
                ->setCellAlignment(CellAlignment::CENTER)
                ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
                ->setShouldWrapText();
            $integerStyle = (new Style())
                ->setFormat('0')->setCellAlignment(CellAlignment::CENTER);
            $numberStyle = (new Style())
                ->setFormat('0.00')->setCellAlignment(CellAlignment::CENTER);
            $averageStyle = (new Style())
                ->setFontBold()->setFontColor(Color::rgb(17, 24, 39))
                ->setBackgroundColor(Color::rgb(229, 231, 235))
                ->setFormat('0.00')->setCellAlignment(CellAlignment::CENTER);

            $writer->addRow(Row::fromValues(['TRANSKRIP NILAI SISWA'], $titleStyle)->setHeight(26));
            $writer->addRow(Row::fromValues([
                'Kelas: '.$report['classroom']->name
                .($report['classroom']->subject ? ' | Mata pelajaran: '.$report['classroom']->subject : ''),
            ], $metaStyle));
            $writer->addRow(Row::fromValues([
                'Guru: '.$report['classroom']->teacher->name.' | Diekspor: '.now()->format('d-m-Y H:i'),
            ], $metaStyle));
            $writer->addRow(Row::fromValues([]));

            $headers = ['No.', 'Nomor Induk', 'Nama Siswa'];
            foreach ($report['activities'] as $activity) {
                $headers[] = $activity['type']."\n".$activity['title']."\nMaks. ".$this->number($activity['maximum']);
            }
            $headers[] = "Rata-rata\n(%)";
            $writer->addRow(Row::fromValues($headers, $headerStyle)->setHeight(48));

            $rowNumber = 6;
            foreach ($report['rows'] as $index => $reportRow) {
                $values = [$index + 1, (string) ($reportRow['student']->nomor_induk ?? ''), $reportRow['student']->name];
                $columnStyles = [0 => $integerStyle, 3 + $report['activities']->count() => $averageStyle];

                foreach ($report['activities'] as $activity) {
                    $percentage = $reportRow['scores'][$activity['key']]['percentage'];
                    $values[] = $percentage !== null ? (float) $percentage : null;
                    $columnStyles[count($values) - 1] = $numberStyle;
                }
                $values[] = $reportRow['average'] !== null ? (float) $reportRow['average'] : null;

                $writer->addRow(Row::fromValuesWithStyles($values, null, $columnStyles)->setHeight(22));
                $rowNumber++;
            }

            $sheet->setAutoFilter(new AutoFilter(0, 5, $lastColumnIndex, max(5, $rowNumber - 1)));
        } finally {
            $writer->close();
        }
    }

    /** Export the same class-by-class rows shown on the student's combined transcript. */
    public function writeStudentCombined(string $path, array $overview, User $student): void
    {
        $options = new Options();
        $options->SHOULD_USE_INLINE_STRINGS = false;
        $options->setProperties(new Properties(
            title: 'Transkrip Nilai Seluruh Kelas',
            subject: 'Rekap nilai final seluruh kelas siswa',
            application: 'RuangKelas',
            creator: 'RuangKelas',
        ));
        foreach ([7, 25, 13, 30, 38, 19, 25, 13, 18] as $index => $width) {
            $options->setColumnWidth($width, $index + 1);
        }
        $options->mergeCells(0, 1, 8, 1);

        $writer = new Writer($options);
        $writer->openToFile($path);
        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Seluruh Kelas');
            $sheet->setSheetView((new SheetView())->setFreezeRow(7)->setZoomScale(85));

            $titleStyle = (new Style())->setFontName('Calibri')->setFontSize(16)->setFontBold()
                ->setFontColor(Color::rgb(17, 24, 39));
            $metaStyle = (new Style())->setFontName('Calibri')->setFontSize(10)
                ->setFontColor(Color::rgb(71, 85, 105));
            $headerStyle = (new Style())->setFontName('Calibri')->setFontSize(10)->setFontBold()
                ->setFontColor(Color::WHITE)->setBackgroundColor(Color::rgb(31, 41, 55))
                ->setCellAlignment(CellAlignment::CENTER)->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
                ->setShouldWrapText();
            $integerStyle = (new Style())->setFontName('Calibri')->setFormat('0')
                ->setCellAlignment(CellAlignment::CENTER);
            $numberStyle = (new Style())->setFontName('Calibri')->setFormat('0.00')
                ->setCellAlignment(CellAlignment::CENTER);
            $averageStyle = (new Style())->setFontName('Calibri')->setFontBold()
                ->setBackgroundColor(Color::rgb(229, 231, 235))
                ->setFontColor(Color::rgb(17, 24, 39))->setFormat('0.00');

            $writer->addRow(Row::fromValues(['TRANSKRIP NILAI SELURUH KELAS'], $titleStyle)->setHeight(27));
            $writer->addRow(Row::fromValues(['Nama', $student->name, 'NIS', (string) ($student->nomor_induk ?? '')], $metaStyle));
            $writer->addRow(Row::fromValues([
                'Kelas tervalidasi', $overview['validatedCount'], 'Total kelas', $overview['classrooms']->count(),
            ], $metaStyle));
            $writer->addRow(Row::fromValuesWithStyles([
                'Rata-rata nilai final (0-100)', $overview['average'] !== null ? (float) $overview['average'] : null,
            ], null, [1 => $averageStyle]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([
                'No.', 'Kelas', 'Nilai', 'Status', 'Aktivitas', 'Jenis', 'Guru', 'Maksimum', 'Persentase (%)',
            ], $headerStyle)->setHeight(30));

            foreach ($overview['entries'] as $index => $entry) {
                $score = $entry['score'];
                $writer->addRow(Row::fromValuesWithStyles([
                    $index + 1,
                    $entry['classroom']->name,
                    ($score['score'] ?? null) !== null ? (float) $score['score'] : null,
                    $entry['status'],
                    $entry['activity']['title'] ?? '',
                    $entry['activity']['type'] ?? '',
                    $entry['classroom']->teacher->name,
                    ($score['maximum'] ?? null) !== null ? (float) $score['maximum'] : null,
                    ($score['percentage'] ?? null) !== null ? (float) $score['percentage'] : null,
                ], null, [0 => $integerStyle, 2 => $numberStyle, 7 => $numberStyle, 8 => $numberStyle])->setHeight(36));
            }
            $sheet->setAutoFilter(new AutoFilter(0, 6, 8, max(6, 6 + $overview['entries']->count())));
        } finally {
            $writer->close();
        }
    }

    private function worksheet(array $report): string
    {
        $activities = $report['activities'];
        $columnCount = 4 + $activities->count();
        $lastColumn = $this->columnName($columnCount);
        $rows = [];

        $rows[] = $this->row(1, [$this->textCell('A1', 'TRANSKRIP NILAI SISWA', 3)]);
        $rows[] = $this->row(2, [$this->textCell('A2', 'Kelas: '.$report['classroom']->name, 2)]);
        $rows[] = $this->row(3, [$this->textCell('A3', 'Guru: '.$report['classroom']->teacher->name.' | Diekspor: '.now()->format('d-m-Y H:i'), 2)]);

        $headers = ['No.', 'Nomor Induk', 'Nama Siswa'];
        foreach ($activities as $activity) {
            $headers[] = $activity['type'].': '.$activity['title'].' (/'.$this->number($activity['maximum']).')';
        }
        $headers[] = 'Rata-rata (%)';
        $headerCells = [];
        foreach ($headers as $index => $header) {
            $headerCells[] = $this->textCell($this->columnName($index + 1).'5', $header, 1);
        }
        $rows[] = $this->row(5, $headerCells);

        $rowNumber = 6;
        foreach ($report['rows'] as $index => $reportRow) {
            $cells = [
                $this->numberCell('A'.$rowNumber, $index + 1),
                $this->textCell('B'.$rowNumber, $reportRow['student']->nomor_induk ?? ''),
                $this->textCell('C'.$rowNumber, $reportRow['student']->name),
            ];
            $column = 4;
            foreach ($activities as $activity) {
                $item = $reportRow['scores'][$activity['key']];
                $reference = $this->columnName($column).$rowNumber;
                $cells[] = $item['percentage'] !== null
                    ? $this->numberCell($reference, $item['percentage'], 4)
                    : $this->textCell($reference, '—');
                $column++;
            }
            $reference = $this->columnName($column).$rowNumber;
            $cells[] = $reportRow['average'] !== null
                ? $this->numberCell($reference, $reportRow['average'], 4)
                : $this->textCell($reference, '—');
            $rows[] = $this->row($rowNumber, $cells);
            $rowNumber++;
        }

        $widths = '<col min="1" max="1" width="7" customWidth="1"/>'
            .'<col min="2" max="2" width="18" customWidth="1"/>'
            .'<col min="3" max="3" width="28" customWidth="1"/>';
        if ($columnCount > 3) {
            $widths .= '<col min="4" max="'.$columnCount.'" width="22" customWidth="1"/>';
        }
        $lastDataRow = $report['rows']->isNotEmpty() ? $rowNumber - 1 : 5;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$widths.'</cols><sheetData>'.implode('', $rows).'</sheetData>'
            .'<mergeCells count="3"><mergeCell ref="A1:'.$lastColumn.'1"/><mergeCell ref="A2:'.$lastColumn.'2"/><mergeCell ref="A3:'.$lastColumn.'3"/></mergeCells>'
            .'<autoFilter ref="A5:'.$lastColumn.$lastDataRow.'"/></worksheet>';
    }

    private function row(int $number, array $cells): string
    {
        return '<row r="'.$number.'">'.implode('', $cells).'</row>';
    }

    private function textCell(string $reference, string $value, int $style = 0): string
    {
        return '<c r="'.$reference.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
    }

    private function numberCell(string $reference, int|float $value, int $style = 0): string
    {
        return '<c r="'.$reference.'" s="'.$style.'"><v>'.$value.'</v></c>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }
        return $name;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Transkrip" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="0.00"/></numFmts><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="15"/><color rgb="FF312E81"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF4338CA"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFill="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
