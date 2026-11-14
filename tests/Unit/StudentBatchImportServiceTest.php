<?php

namespace Tests\Unit;

use App\Services\StudentBatchImportService;
use PHPUnit\Framework\TestCase;

class StudentBatchImportServiceTest extends TestCase
{
    public function test_nipd_is_normalized_to_digits_only_for_login(): void
    {
        $this->assertSame(
            '13494496042',
            StudentBatchImportService::normalizeNipd('13494/496.042'),
        );
        $this->assertSame(
            '00123456',
            StudentBatchImportService::normalizeNipd(' 00-12/34.56 '),
        );
    }
}
