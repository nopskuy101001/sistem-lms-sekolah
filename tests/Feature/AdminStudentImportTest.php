<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StudentImportTemplateXlsxExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class AdminStudentImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_download_csv_and_excel_import_templates(): void
    {
        $csv = $this->actingAs($this->admin)
            ->get(route('admin.users.import.template.csv'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBFsep=,\r\n", $csv);
        $this->assertStringContainsString('No,Nama,NIPD,JK,NISN,"Tempat Lahir","Tanggal Lahir",NIK,"Rombel Saat Ini",Email', $csv);
        $this->assertStringNotContainsString('Telepon', $csv);
        $this->assertStringNotContainsString('Alamat', $csv);

        $xlsx = $this->actingAs($this->admin)
            ->get(route('admin.users.import.template.xlsx'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->streamedContent();

        $path = tempnam(sys_get_temp_dir(), 'student-template-test-');
        file_put_contents($path, $xlsx);
        $reader = new Reader;

        try {
            $reader->open($path);
            $sheets = array_values(iterator_to_array($reader->getSheetIterator()));
            $this->assertSame('Data Siswa', $sheets[0]->getName());
            $rows = array_values(iterator_to_array($sheets[0]->getRowIterator()));
            $this->assertSame(StudentImportTemplateXlsxExporter::HEADERS, $rows[0]->toArray());
            $this->assertSame('Petunjuk', $sheets[1]->getName());
        } finally {
            $reader->close();
            @unlink($path);
        }
    }

    public function test_admin_can_import_csv_and_student_must_replace_birth_date_password(): void
    {
        $csv = "\xEF\xBB\xBFsep=,\r\n"
            .'NIS,Nama,Tanggal Lahir,Email,Jenis Kelamin'."\r\n"
            .'000123,Siti Aisyah,22/03/2005,,P'."\r\n";

        $this->actingAs($this->admin)
            ->post(route('admin.users.import'), [
                'student_file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $student = User::where('nomor_induk', '000123')->firstOrFail();
        $this->assertSame('siswa', $student->role);
        $this->assertSame('2005-03-22', $student->date_of_birth->format('Y-m-d'));
        $this->assertSame('P', $student->gender);
        $this->assertSame('siswa.000123@akun.ruangkelas.local', $student->email);
        $this->assertTrue($student->must_change_password);
        $this->assertTrue(Hash::check('22032005', $student->password));

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.attempt'), [
            'login' => '000123',
            'password' => '22032005',
        ])->assertRedirect(route('password.change'));

        $this->get(route('dashboard'))->assertRedirect(route('password.change'));

        $this->put(route('password.update'), [
            'current_password' => '22032005',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertRedirect(route('dashboard'));

        $student->refresh();
        $this->assertFalse($student->must_change_password);
        $this->assertTrue(Hash::check('PasswordBaru123', $student->password));
    }

    public function test_admin_can_import_excel_students(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'student-import-test-');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(StudentImportTemplateXlsxExporter::HEADERS));
        $writer->addRow(Row::fromValues([
            '1', 'Budi Santoso', '13494/496.042', 'L', '0067890123', 'Gresik',
            new \DateTimeImmutable('2006-07-15'), '3525011507060001', 'X RPL 1', 'budi@example.test',
        ]));
        $writer->close();

        try {
            $file = new UploadedFile(
                $path,
                'data-siswa.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $this->actingAs($this->admin)
                ->post(route('admin.users.import'), ['student_file' => $file])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $student = User::where('nomor_induk', '13494496042')->firstOrFail();
            $this->assertSame('13494/496.042', $student->nipd);
            $this->assertSame('0067890123', $student->nisn);
            $this->assertSame('Gresik', $student->birth_place);
            $this->assertSame('3525011507060001', $student->nik);
            $this->assertSame('X RPL 1', $student->current_rombel);
            $this->assertSame('1', $student->source_number);
            $this->assertSame('2006-07-15', $student->date_of_birth->format('Y-m-d'));
            $this->assertTrue(Hash::check('15072006', $student->password));

            $this->post(route('logout'));
            $this->post(route('login.attempt'), [
                'login' => '13494496042',
                'password' => '15072006',
            ])->assertRedirect(route('password.change'));
        } finally {
            @unlink($path);
        }
    }

    public function test_admin_can_export_students_with_original_nipd_and_optional_fields(): void
    {
        User::factory()->create([
            'role' => 'siswa',
            'name' => 'Adinda Ramadhani',
            'nomor_induk' => '13494496042',
            'nipd' => '13494/496.042',
            'nisn' => '0102083824',
            'birth_place' => 'Lamongan',
            'date_of_birth' => '2010-08-13',
            'gender' => 'P',
            'nik' => '3524125308100001',
            'current_rombel' => 'XAPL1',
            'source_number' => '1',
        ]);

        $xlsx = $this->actingAs($this->admin)
            ->get(route('admin.users.export.students.xlsx'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->streamedContent();

        $path = tempnam(sys_get_temp_dir(), 'student-export-test-');
        file_put_contents($path, $xlsx);
        $reader = new Reader;

        try {
            $reader->open($path);
            $sheet = array_values(iterator_to_array($reader->getSheetIterator()))[0];
            $rows = array_values(iterator_to_array($sheet->getRowIterator()));
            $this->assertSame(StudentImportTemplateXlsxExporter::HEADERS, $rows[0]->toArray());
            $this->assertSame([
                '1', 'Adinda Ramadhani', '13494/496.042', 'P', '0102083824',
                'Lamongan', '2010-08-13', '3524125308100001', 'XAPL1',
                $rows[1]->toArray()[9],
            ], $rows[1]->toArray());
        } finally {
            $reader->close();
            @unlink($path);
        }
    }

    public function test_student_can_defer_initial_password_change_for_current_session_only(): void
    {
        $student = User::factory()->create([
            'role' => 'siswa',
            'nomor_induk' => 'DEFER-001',
            'password' => '22032005',
            'must_change_password' => true,
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.attempt'), [
            'login' => 'DEFER-001',
            'password' => '22032005',
        ])->assertRedirect(route('password.change'));

        $this->get(route('password.change'))
            ->assertOk()
            ->assertSee('Nanti saja')
            ->assertSee(route('password.defer'));

        $this->post(route('password.defer'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertTrue($student->fresh()->must_change_password);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('profile.edit'))->assertOk();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.attempt'), [
            'login' => 'DEFER-001',
            'password' => '22032005',
        ])->assertRedirect(route('password.change'));
        $this->get(route('dashboard'))->assertRedirect(route('password.change'));
    }

    public function test_invalid_batch_is_rejected_without_partial_import(): void
    {
        User::factory()->create(['role' => 'siswa', 'nomor_induk' => 'DUPLIKAT']);
        $csv = "NIS,Nama,Tanggal Lahir,Email,Jenis Kelamin\r\n"
            ."BARU-001,Siswa Baru,01/01/2007,,\r\n"
            ."DUPLIKAT,Siswa Lama,02/02/2007,,\r\n";

        $this->actingAs($this->admin)
            ->post(route('admin.users.import'), [
                'student_file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('student_file');

        $this->assertDatabaseMissing('users', ['nomor_induk' => 'BARU-001']);
    }

    public function test_import_rejects_future_birth_date_and_corrupt_spreadsheet_cleanly(): void
    {
        $futureCsv = "NIS,Nama,Tanggal Lahir,Email,Jenis Kelamin\r\n"
            .'MASA-DEPAN,Siswa Masa Depan,'.now()->addDay()->format('d/m/Y').",,\r\n";

        $this->actingAs($this->admin)
            ->post(route('admin.users.import'), [
                'student_file' => UploadedFile::fake()->createWithContent('masa-depan.csv', $futureCsv),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('student_file');

        $this->actingAs($this->admin)
            ->post(route('admin.users.import'), [
                'student_file' => UploadedFile::fake()->createWithContent('rusak.xlsx', 'bukan berkas xlsx'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('student_file');

        $this->assertDatabaseMissing('users', ['nomor_induk' => 'MASA-DEPAN']);
    }
}
