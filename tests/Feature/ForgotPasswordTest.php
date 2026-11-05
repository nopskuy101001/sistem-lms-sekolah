<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LmsPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('mail.default', 'smtp');
        Notification::fake();
    }

    public function test_forgot_password_routes_and_login_link_coexist_with_admin_reset(): void
    {
        $this->assertTrue(Route::has('password.request'));
        $this->assertTrue(Route::has('password.email'));
        $this->assertTrue(Route::has('password.reset'));
        $this->assertTrue(Route::has('password.store'));
        $this->assertTrue(Route::has('admin.users.password.reset'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Lupa password?')
            ->assertSee(route('password.request'), false);
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('NIS atau Email');
    }

    public function test_student_can_request_reset_using_nis_and_registered_email_receives_it(): void
    {
        $student = User::factory()->create([
            'role' => 'siswa',
            'nomor_induk' => '20260001',
            'email' => 'siswa@example.com',
        ]);

        $this->post(route('password.email'), ['login' => $student->nomor_induk])
            ->assertSessionHas('status');

        Notification::assertSentTo($student, LmsPasswordResetNotification::class);
    }

    public function test_unknown_or_placeholder_account_does_not_receive_email(): void
    {
        $this->post(route('password.email'), ['login' => 'NIS-TIDAK-ADA'])
            ->assertSessionHas('status');

        $student = User::factory()->create([
            'role' => 'siswa',
            'nomor_induk' => '20260002',
            'email' => 'siswa.20260002@akun.ruangkelas.local',
        ]);
        $this->post(route('password.email'), ['login' => $student->nomor_induk])
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_valid_email_token_resets_password_without_changing_school_identity(): void
    {
        $student = User::factory()->create([
            'role' => 'siswa',
            'nomor_induk' => '20260003',
            'email' => 'siswa3@example.com',
            'password' => 'PasswordLama123!',
            'must_change_password' => true,
        ]);
        $token = Password::broker()->createToken($student);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $student->email,
            'password' => 'PasswordBaru456!',
            'password_confirmation' => 'PasswordBaru456!',
        ])->assertRedirect(route('login'));

        $student->refresh();
        $this->assertTrue(Hash::check('PasswordBaru456!', $student->password));
        $this->assertFalse($student->must_change_password);
        $this->assertSame('20260003', $student->nomor_induk);
        $this->assertSame('siswa', $student->role);
        $this->assertSame('siswa3@example.com', $student->email);
    }

    public function test_invalid_token_cannot_change_password(): void
    {
        $teacher = User::factory()->create([
            'role' => 'guru',
            'email' => 'guru@example.com',
            'password' => 'PasswordLama123!',
        ]);

        $this->post(route('password.store'), [
            'token' => 'token-tidak-valid',
            'email' => $teacher->email,
            'password' => 'PasswordBaru456!',
            'password_confirmation' => 'PasswordBaru456!',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('PasswordLama123!', $teacher->fresh()->password));
    }

    public function test_public_registration_remains_closed(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
