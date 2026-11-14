<?php

namespace Tests\Unit;

use App\Services\TeacherBatchImportService;
use App\Services\TeacherDataXlsxExporter;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class TeacherImportExportTest extends TestCase
{
    public function test_nip_is_normalized_to_digits_only(): void
    {
        $this->assertSame(
            '197112061997022003',
            TeacherBatchImportService::normalizeNip('19711206 199702 2 003'),
        );
    }

    public function test_teacher_template_keeps_two_level_headers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'teacher-template-test-');
        $this->assertNotFalse($path);
        app(TeacherDataXlsxExporter::class)->write($path, collect(), true);
        $reader = new Reader;

        try {
            $reader->open($path);
            $sheets = array_values(iterator_to_array($reader->getSheetIterator()));
            $rows = array_values(iterator_to_array($sheets[0]->getRowIterator()));
            $this->assertSame('Data Guru', $sheets[0]->getName());
            $this->assertSame('Petunjuk', $sheets[1]->getName());
            $this->assertSame(TeacherDataXlsxExporter::TOP_HEADERS, $rows[0]->toArray());
            $this->assertSame(TeacherDataXlsxExporter::SUB_HEADERS, $rows[1]->toArray());
            $this->assertSame(range(1, 18), $rows[2]->toArray());
        } finally {
            $reader->close();
            @unlink($path);
        }
    }
}
