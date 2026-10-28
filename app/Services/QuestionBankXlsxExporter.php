<?php

namespace App\Services;

use App\Models\QuestionBank;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class QuestionBankXlsxExporter
{
    public function write(string $path, QuestionBank $bank): void
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
            $textStyle = (new Style)->setFontName('Calibri')->setFontSize(11)->setShouldWrapText()
                ->setCellVerticalAlignment(CellVerticalAlignment::TOP);

            $writer->getCurrentSheet()->setName('Import Soal');
            $writer->addRow(Row::fromValues(QuestionImportTemplateXlsxExporter::HEADERS, $headerStyle)->setHeight(35));

            $groups = collect($bank->payload['groups'] ?? [])->keyBy('key');
            $writtenGroups = [];
            foreach ($bank->payload['questions'] ?? [] as $question) {
                $groupKey = $question['group_key'] ?? null;
                $group = $groupKey !== null ? $groups->get($groupKey) : null;
                $firstUse = $group && ! isset($writtenGroups[$groupKey]);
                if ($firstUse) {
                    $writtenGroups[$groupKey] = true;
                }

                $type = $question['type'] ?? 'esai';
                $items = $question['category_items'] ?? [];
                $keys = $type === 'pilihan_ganda'
                    ? strtoupper((string) ($question['correct_option'] ?? ''))
                    : implode('|', array_map('strtoupper', $question['correct_options'] ?? []));

                $values = [
                    match ($type) {
                        'pilihan_ganda' => 'PG',
                        'pilihan_ganda_kompleks' => 'PG Kompleks',
                        'kategori' => 'Kategori',
                        default => 'Esai',
                    },
                    $question['body'] ?? '',
                    $question['option_a'] ?? '',
                    $question['option_b'] ?? '',
                    $question['option_c'] ?? '',
                    $question['option_d'] ?? '',
                    $keys,
                    implode('||', array_column($items, 'statement')),
                    implode('|', array_map('ucfirst', array_column($items, 'answer'))),
                    (int) ($question['points'] ?? 0),
                    ucfirst((string) ($question['cognitive_level'] ?? '')),
                    $question['competency'] ?? '',
                    $question['subcompetency'] ?? '',
                    $groupKey ?? '',
                    $firstUse ? ($group['title'] ?? '') : '',
                    $firstUse ? ($group['stimulus'] ?? '') : '',
                    $firstUse ? $this->tableText($group['table_data'] ?? []) : '',
                ];

                // Keep text starting with '=' as text so question content cannot become a spreadsheet formula.
                $cells = array_map(
                    fn ($value, int $index) => $index === 9
                        ? Cell::fromValue($value)
                        : new StringCell((string) $value, null),
                    $values,
                    array_keys($values),
                );
                $writer->addRow((new Row($cells, $textStyle))->setHeight(48));
            }

            $writer->addNewSheetAndMakeItCurrent()->setName('Informasi Paket');
            $writer->addRow(Row::fromValues(['Informasi Paket Bank Soal'], $headerStyle)->setHeight(32));
            $quiz = $bank->payload['quiz'] ?? [];
            $hasImages = collect($bank->payload['groups'] ?? [])->contains(fn ($group) => filled($group['media_path'] ?? null));
            foreach ([
                ['Nama paket', $bank->title],
                ['Judul kuis', $quiz['title'] ?? $bank->title],
                ['Deskripsi', $quiz['description'] ?? $bank->description ?? ''],
                ['Durasi (menit)', $quiz['duration_minutes'] ?? 'Tanpa batas'],
                ['Jumlah soal', $bank->question_count],
                ['Total poin', $bank->total_points],
                ['Gambar stimulus', $hasImages ? 'Gambar tidak disertakan. Simpan gambar secara terpisah sebelum mengimpor ulang.' : 'Tidak ada'],
            ] as $item) {
                $writer->addRow(new Row([
                    new StringCell((string) $item[0], null),
                    new StringCell((string) $item[1], null),
                ], $textStyle));
            }
        } finally {
            $writer->close();
        }
    }

    /** @param array<int, array<int, string>> $rows */
    private function tableText(array $rows): string
    {
        return collect($rows)->map(function (array $cells): string {
            $stream = fopen('php://temp', 'r+');
            fputcsv($stream, $cells, '|', '"', '\\');
            rewind($stream);
            $line = trim((string) stream_get_contents($stream), "\r\n");
            fclose($stream);

            return $line;
        })->implode("\n");
    }
}
