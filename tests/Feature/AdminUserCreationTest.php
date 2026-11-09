<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_student_manually_with_the_same_account_policy_as_import(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('student_phone', false)
            ->assertDontSee('No. telepon')
            ->assertDontSee('student_address', false)
            ->assertSee('placeholder="dd/mm/yyyy"', false)
            ->assertDontSee('id="student_birth_date" type="date"', false);

        $this->post(route('admin.users.students.store'), [
            'account_form' => 'student',
            'name' => '  Siti Aisyah  ',
            'nomor_induk' => '  000456  ',
            'date_of_birth' => '03/04/2006',
            'email' => '',
            'gender' => 'P',
            'phone' => ' 08123456789 ',
            'address' => ' Jalan Pendidikan ',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

        $student = User::where('nomor_induk', '000456')->firstOrFail();
        $this->assertSame('Siti Aisyah', $student->name);
        $this->assertSame('siswa', $student->role);
        $this->assertSame('siswa.000456@akun.ruangkelas.local', $student->email);
        $this->assertSame('2006-04-03', $student->date_of_birth->format('Y-m-d'));
        $this->assertNull($student->phone);
        $this->assertNull($student->address);
        $this->assertTrue($student->must_change_password);
        $this->assertTrue(Hash::check('03042006', $student->password));
    }

    public function test_manual_student_rejects_invalid_nis_and_future_birth_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.users.students.store'), [
            'account_form' => 'student',
            'name' => 'Siswa Tidak Valid',
            'nomor_induk' => '///',
            'date_of_birth' => now()->addDay()->format('d/m/Y'),
        ])->assertRedirect()
            ->assertSessionHasErrors(['nomor_induk', 'date_of_birth'], errorBag: 'studentCreate');

        $this->post(route('admin.users.students.store'), [
            'account_form' => 'student',
            'name' => 'Siswa Format Salah',
            'nomor_induk' => 'FORMAT-SALAH',
            'date_of_birth' => '2006-04-03',
        ])->assertRedirect()
            ->assertSessionHasErrors('date_of_birth', errorBag: 'studentCreate');

        $this->assertDatabaseMissing('users', ['name' => 'Siswa Tidak Valid']);
        $this->assertDatabaseMissing('users', ['nomor_induk' => 'FORMAT-SALAH']);
    }

    public function test_manual_student_reports_generated_email_collision_without_server_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create([
            'role' => 'siswa',
            'nomor_induk' => 'NIS-LAIN',
            'email' => 'siswa.000777@akun.ruangkelas.local',
        ]);

        $this->actingAs($admin)->post(route('admin.users.students.store'), [
            'account_form' => 'student',
            'name' => 'Siswa Email Bentrok',
            'nomor_induk' => '000777',
            'date_of_birth' => '01/01/2007',
            'email' => '',
        ])->assertRedirect()
            ->assertSessionHasErrors('email', errorBag: 'studentCreate');

        $this->assertDatabaseMissing('users', ['nomor_induk' => '000777']);
    }

    public function test_admin_can_create_staff_with_confirmed_hidden_password_and_forced_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'account_form' => 'staff',
            'name' => '  Guru Baru ',
            'email' => ' GURU.BARU@example.test ',
            'role' => 'guru',
            'nomor_induk' => ' NIP-100 ',
            'password' => 'PasswordAwal123',
            'password_confirmation' => 'PasswordAwal123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $teacher = User::where('nomor_induk', 'NIP-100')->firstOrFail();
        $this->assertSame('Guru Baru', $teacher->name);
        $this->assertSame('guru.baru@example.test', $teacher->email);
        $this->assertSame('guru', $teacher->role);
        $this->assertTrue($teacher->must_change_password);
        $this->assertTrue(Hash::check('PasswordAwal123', $teacher->password));

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Tambah siswa manual')
            ->assertSee('Tambah guru atau admin')
            ->assertSee('type="password" name="password"', false)
            ->assertDontSee('type="text" name="password"', false);
    }

    public function test_staff_endpoint_rejects_student_role_and_non_admin_cannot_create_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'guru']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'account_form' => 'staff',
            'name' => 'Siswa Salah Jalur',
            'email' => 'salah@example.test',
            'role' => 'siswa',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertSessionHasErrors('role', errorBag: 'staffCreate');

        $this->actingAs($teacher)->post(route('admin.users.students.store'), [
            'name' => 'Tidak Berizin',
            'nomor_induk' => 'NO-AUTH',
            'date_of_birth' => '01/01/2007',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'salah@example.test']);
        $this->assertDatabaseMissing('users', ['nomor_induk' => 'NO-AUTH']);
    }
}
