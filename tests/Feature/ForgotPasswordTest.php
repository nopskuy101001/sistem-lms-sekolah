<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LmsPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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

    public function test_login_shows_hoverable_link_and_request_form(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Lupa password?');
        $this->get(route('password.request'))->assertOk()->assertSee('NIS atau Email');
    }

    public function test_student_nis_sends_email_to_registered_address_not_input(): void
    {
        $student = User::factory()->create([
            'role' => 'siswa', 'nomor_induk' => '20260001', 'email' => 'siswa@example.com',
            'email_verified_at' => null,
        ]);
        $this->post(route('password.email'), ['login' => '20260001'])
            ->assertSessionHas('status');
        Notification::assertSentTo($student, LmsPasswordResetNotification::class);
    }

    public function test_unknown_login_and_placeholder_email_do_not_disclose_or_send(): void
    {
        $this->post(route('password.email'), ['login' => 'NIS-TIDAK-ADA'])
            ->assertSessionHas('status');
        $student = User::factory()->create([
            'role' => 'siswa', 'nomor_induk' => '20260002',
            'email' => 'siswa.20260002@akun.ruangkelas.local',
        ]);
        $this->post(route('password.email'), ['login' => $student->nomor_induk])
            ->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_valid_token_resets_password_without_changing_school_data(): void
    {
        $student = User::factory()->create([
            'role' => 'siswa', 'nomor_induk' => '20260003', 'email' => 'siswa3@example.com',
            'password' => 'password-awal-1', 'must_change_password' => true,
        ]);
        $token = Password::broker()->createToken($student);
        $this->get(route('password.reset', ['token' => $token, 'email' => $student->email]))
            ->assertOk()->assertSee('Konfirmasi Password Baru');
        $this->post(route('password.store'), [
            'token' => $token, 'email' => $student->email,
            'password' => 'password-baru-aman', 'password_confirmation' => 'password-baru-aman',
        ])->assertRedirect(route('login'));
        $student->refresh();
        $this->assertTrue(Hash::check('password-baru-aman', $student->password));
        $this->assertFalse($student->must_change_password);
        $this->assertSame('20260003', $student->nomor_induk);
        $this->assertSame('siswa', $student->role);
        $this->assertSame('siswa3@example.com', $student->email);
        $this->post(route('password.store'), [
            'token' => $token, 'email' => $student->email,
            'password' => 'password-kedua-1', 'password_confirmation' => 'password-kedua-1',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password-baru-aman', $student->fresh()->password));
    }

    public function test_invalid_token_and_confirmation_mismatch_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'guru']);
        $this->post(route('password.store'), [
            'token' => 'invalid-token', 'email' => $user->email,
            'password' => 'password-baru-aman', 'password_confirmation' => 'password-baru-aman',
        ])->assertSessionHasErrors('email');
        $token = Password::broker()->createToken($user);
        $this->post(route('password.store'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'password-baru-aman', 'password_confirmation' => 'berbeda1234',
        ])->assertSessionHasErrors('password');
    }

    public function test_pending_public_registration_account_cannot_receive_reset(): void
    {
        $pending = User::factory()->create([
            'role' => 'siswa_pending', 'nomor_induk' => '20269999',
            'email' => 'pending@example.com',
        ]);
        $this->post(route('password.email'), ['login' => $pending->nomor_induk])
            ->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_reset_link_expires_after_thirty_minutes(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $before = $user->password;
        $token = Password::broker()->createToken($user);
        $this->travel(31)->minutes();
        $this->post(route('password.store'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'password-baru-aman', 'password_confirmation' => 'password-baru-aman',
        ])->assertSessionHasErrors('email');
        $this->assertSame($before, $user->fresh()->password);
    }


    public function test_logged_in_student_can_open_email_reset_link_and_is_logged_out_after_reset(): void
    {
        $student = User::factory()->create([
            'role' => 'siswa', 'nomor_induk' => '20267771',
            'email' => 'reset-session@example.com',
        ]);
        $token = Password::broker()->createToken($student);

        $this->actingAs($student);
        $this->get(route('password.reset', ['token' => $token, 'email' => $student->email]))
            ->assertOk()
            ->assertSee('Konfirmasi Password Baru');

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $student->email,
            'password' => 'password-baru-dari-email',
            'password_confirmation' => 'password-baru-dari-email',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertTrue(Hash::check('password-baru-dari-email', $student->fresh()->password));
        $this->get(route('login'))->assertOk();
    }

    public function test_forced_password_change_middleware_does_not_block_reset_email(): void
    {
        $student = User::factory()->create([
            'role' => 'siswa',
            'email' => 'required-change@example.com',
            'must_change_password' => true,
        ]);
        $token = Password::broker()->createToken($student);

        $this->actingAs($student);
        $this->get(route('password.reset', ['token' => $token, 'email' => $student->email]))
            ->assertOk()->assertSee('Konfirmasi Password Baru');
        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $student->email,
            'password' => 'reset-paksa-aman',
            'password_confirmation' => 'reset-paksa-aman',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertFalse($student->fresh()->must_change_password);
    }

    public function test_other_logged_in_user_still_needs_valid_token(): void
    {
        $current = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'guru', 'email' => 'other-reset@example.com']);
        $previousPassword = $target->password;

        $this->actingAs($current);
        $this->get(route('password.request'))->assertOk();
        $this->get(route('password.reset', ['token' => 'not-valid', 'email' => $target->email]))
            ->assertOk();
        $this->post(route('password.store'), [
            'token' => 'not-valid',
            'email' => $target->email,
            'password' => 'cannot-change-123',
            'password_confirmation' => 'cannot-change-123',
        ])->assertSessionHasErrors('email');

        $this->assertAuthenticatedAs($current);
        $this->assertSame($previousPassword, $target->fresh()->password);
    }

    public function test_public_registration_remains_closed(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
