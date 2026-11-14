<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\StudentDataXlsxExporter;
use App\Services\StudentImportTemplateXlsxExporter;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class StudentDataXlsxExporterTest extends TestCase
{
    public function test_it_exports_original_nipd_and_decrypted_optional_data(): void
    {
        $student = new User;
        $student->forceFill([
            'name' => 'Adinda Ramadhani',
            'email' => 'adinda@example.test',
            'nomor_induk' => '13494496042',
            'nipd' => '13494/496.042',
            'gender' => 'P',
            'nisn' => '0102083824',
            'birth_place' => 'Lamongan',
            'date_of_birth' => '2010-08-13',
            'nik' => '3524125308100001',
            'current_rombel' => 'XAPL1',
            'source_number' => '1',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'student-export-unit-');
        $this->assertNotFalse($path);
        app(StudentDataXlsxExporter::class)->write($path, collect([$student]));

        $reader = new Reader;
        try {
            $reader->open($path);
            $sheet = array_values(iterator_to_array($reader->getSheetIterator()))[0];
            $rows = array_values(iterator_to_array($sheet->getRowIterator()));

            $this->assertSame(StudentImportTemplateXlsxExporter::HEADERS, $rows[0]->toArray());
            $this->assertSame([
                '1', 'Adinda Ramadhani', '13494/496.042', 'P', '0102083824',
                'Lamongan', '2010-08-13', '3524125308100001', 'XAPL1', 'adinda@example.test',
            ], $rows[1]->toArray());
        } finally {
            $reader->close();
            @unlink($path);
        }
    }
}
