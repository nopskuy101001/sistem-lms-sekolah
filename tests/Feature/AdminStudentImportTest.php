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
        $this->assertStringContainsString('NIS,Nama,"Tanggal Lahir",Email,"Jenis Kelamin","No. Telepon",Alamat', $csv);

        $xlsx = $this->actingAs($this->admin)
            ->get(route('admin.users.import.template.xlsx'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->streamedContent();

        $path = tempnam(sys_get_temp_dir(), 'student-template-test-');
        file_put_contents($path, $xlsx);
        $reader = new Reader();

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
            .'NIS,Nama,Tanggal Lahir,Email,Jenis Kelamin,No. Telepon,Alamat'."\r\n"
            .'000123,Siti Aisyah,22/03/2005,,P,08123456789,Jalan Pendidikan'."\r\n";

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
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(StudentImportTemplateXlsxExporter::HEADERS));
        $writer->addRow(Row::fromValues([
            'XLSX-001', 'Budi Santoso', new \DateTimeImmutable('2006-07-15'),
            'budi@example.test', 'L', '081200000001', 'Jalan Sekolah',
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

            $student = User::where('nomor_induk', 'XLSX-001')->firstOrFail();
            $this->assertSame('2006-07-15', $student->date_of_birth->format('Y-m-d'));
            $this->assertTrue(Hash::check('15072006', $student->password));
        } finally {
            @unlink($path);
        }
    }

    public function test_invalid_batch_is_rejected_without_partial_import(): void
    {
        User::factory()->create(['role' => 'siswa', 'nomor_induk' => 'DUPLIKAT']);
        $csv = "NIS,Nama,Tanggal Lahir,Email,Jenis Kelamin,No. Telepon,Alamat\r\n"
            ."BARU-001,Siswa Baru,01/01/2007,,,,\r\n"
            ."DUPLIKAT,Siswa Lama,02/02/2007,,,,\r\n";

        $this->actingAs($this->admin)
            ->post(route('admin.users.import'), [
                'student_file' => UploadedFile::fake()->createWithContent('siswa.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('student_file');

        $this->assertDatabaseMissing('users', ['nomor_induk' => 'BARU-001']);
    }
}
