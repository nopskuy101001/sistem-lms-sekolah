<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_reset_form_for_teacher_and_student_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'guru', 'name' => 'Guru Target']);
        $student = User::factory()->create(['role' => 'siswa', 'name' => 'Siswa Target']);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(route('admin.users.index', ['reset_user' => $teacher->id]), false)
            ->assertSee(route('admin.users.index', ['reset_user' => $student->id]), false);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['reset_user' => $teacher->id]))
            ->assertOk()
            ->assertSee('Reset password oleh admin')
            ->assertSee('Guru Target')
            ->assertSee(route('admin.users.password.reset', $teacher), false);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['reset_user' => $admin->id]))
            ->assertNotFound();
    }

    public function test_admin_can_reset_teacher_password_with_audit_and_forced_change(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => 'PasswordAdmin123!',
        ]);
        $teacher = User::factory()->create([
            'role' => 'guru',
            'password' => 'PasswordLama123!',
            'must_change_password' => false,
            'remember_token' => 'remember-token-lama',
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $teacher->email,
            'token' => 'token-lama',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.password.reset', $teacher), [
                'admin_password' => 'PasswordAdmin123!',
                'password' => 'PasswordSementara456!',
                'password_confirmation' => 'PasswordSementara456!',
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $teacher->refresh();
        $this->assertTrue(Hash::check('PasswordSementara456!', $teacher->password));
        $this->assertTrue($teacher->must_change_password);
        $this->assertNotSame('remember-token-lama', $teacher->remember_token);
        $this->assertDatabaseHas('admin_password_reset_audits', [
            'admin_id' => $admin->id,
            'user_id' => $teacher->id,
        ]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $teacher->email]);
    }

    public function test_wrong_admin_password_does_not_change_target(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => 'PasswordAdmin123!',
        ]);
        $student = User::factory()->create([
            'role' => 'siswa',
            'password' => 'PasswordLama123!',
            'must_change_password' => false,
        ]);
        $oldHash = $student->password;

        $this->actingAs($admin)
            ->from(route('admin.users.index', ['reset_user' => $student->id]))
            ->put(route('admin.users.password.reset', $student), [
                'admin_password' => 'PasswordAdminSalah!',
                'password' => 'PasswordSementara456!',
                'password_confirmation' => 'PasswordSementara456!',
            ])
            ->assertRedirect(route('admin.users.index', ['reset_user' => $student->id]))
            ->assertSessionHasErrors(['admin_password'], null, 'passwordReset');

        $this->assertSame($oldHash, $student->fresh()->password);
        $this->assertFalse($student->fresh()->must_change_password);
        $this->assertDatabaseCount('admin_password_reset_audits', 0);
    }

    public function test_reset_revokes_active_database_sessions_of_target_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'PasswordAdmin123!']);
        $student = User::factory()->create(['role' => 'siswa']);
        DB::table('sessions')->insert([
            'id' => 'target-active-session',
            'user_id' => $student->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin);
        config()->set('session.driver', 'database');

        $this->put(route('admin.users.password.reset', $student), [
            'admin_password' => 'PasswordAdmin123!',
            'password' => 'PasswordSementara456!',
            'password_confirmation' => 'PasswordSementara456!',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('sessions', ['id' => 'target-active-session']);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_non_admin_cannot_reset_and_admin_accounts_are_not_valid_targets(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'PasswordAdmin123!']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'guru']);

        $payload = [
            'admin_password' => 'PasswordAdmin123!',
            'password' => 'PasswordSementara456!',
            'password_confirmation' => 'PasswordSementara456!',
        ];

        $this->actingAs($teacher)
            ->put(route('admin.users.password.reset', $teacher), $payload)
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.users.password.reset', $otherAdmin), $payload)
            ->assertForbidden();
    }

    public function test_reset_password_is_requested_again_on_target_next_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'PasswordAdmin123!']);
        $student = User::factory()->create([
            'role' => 'siswa',
            'nomor_induk' => 'SISWA-RESET-01',
            'password' => 'PasswordLama123!',
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.password.reset', $student), [
                'admin_password' => 'PasswordAdmin123!',
                'password' => 'PasswordSementara456!',
                'password_confirmation' => 'PasswordSementara456!',
            ]);

        $this->post(route('logout'));
        $this->post(route('login.attempt'), [
            'login' => 'SISWA-RESET-01',
            'password' => 'PasswordSementara456!',
        ])->assertRedirect(route('password.change'));
    }
}
