<?php

namespace Tests\Feature;

use App\Mail\ProfileEmailChangeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfileEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('mail.default', 'smtp');
        Mail::fake();
    }

    public function test_all_roles_see_email_button(): void
    {
        foreach (['admin', 'guru', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('profile.edit'))
                ->assertOk()->assertSee('Edit / Ganti Email');
        }
    }

    public function test_login_is_required_and_password_and_email_must_be_valid(): void
    {
        $this->post(route('profile.email.request'), ['email' => 'baru@example.com'])->assertRedirect(route('login'));
        $user = User::factory()->create(['role' => 'siswa', 'password' => 'sekolah-rahasia-1']);
        $other = User::factory()->create(['email' => 'dipakai@example.com']);
        $this->actingAs($user)->post(route('profile.email.request'), [
            'email' => $other->email, 'current_password' => 'sekolah-rahasia-1',
        ])->assertSessionHasErrors('email');
        $this->post(route('profile.email.request'), [
            'email' => 'baru@example.com', 'current_password' => 'salah',
        ])->assertSessionHasErrors('current_password');
        Mail::assertNothingSent();
        $this->assertNotSame('baru@example.com', $user->refresh()->email);
    }

    public function test_email_only_changes_after_valid_signed_link(): void
    {
        $user = User::factory()->create(['role' => 'guru', 'password' => 'rahasia-guru-1', 'email_verified_at' => null]);
        $before = $user->email;
        $nis = $user->nomor_induk;
        $this->actingAs($user)->post(route('profile.email.request'), [
            'email' => 'BARU@Example.com', 'current_password' => 'rahasia-guru-1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($before, $user->refresh()->email);
        Mail::assertSent(ProfileEmailChangeMail::class, function ($mail) {
            return $mail->newEmail === 'baru@example.com';
        });
        $message = Mail::sent(ProfileEmailChangeMail::class)->first();
        $this->get($message->verificationUrl)->assertRedirect(route('profile.edit'));
        $this->assertSame('baru@example.com', $user->refresh()->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('guru', $user->role);
        $this->assertSame($nis, $user->nomor_induk);
        $this->get($message->verificationUrl)->assertSessionHasErrors('email');
    }

    public function test_link_cannot_be_used_by_another_authenticated_account(): void
    {
        $user = User::factory()->create(['role' => 'siswa', 'password' => 'rahasia-1']);
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->post(route('profile.email.request'), [
            'email' => 'siswa-baru@example.com', 'current_password' => 'rahasia-1',
        ])->assertRedirect();
        Mail::assertSent(ProfileEmailChangeMail::class);
        $message = Mail::sent(ProfileEmailChangeMail::class)->first();
        $this->actingAs($other)->get($message->verificationUrl)->assertForbidden();
        $this->assertNotSame('siswa-baru@example.com', $user->refresh()->email);
    }

    public function test_expired_link_cannot_change_email(): void
    {
        $user = User::factory()->create(['role' => 'siswa', 'password' => 'rahasia-aman-1']);
        $before = $user->email;
        $this->actingAs($user)->post(route('profile.email.request'), [
            'email' => 'siswa-terbaru@example.com', 'current_password' => 'rahasia-aman-1',
        ])->assertRedirect();
        $mail = Mail::sent(ProfileEmailChangeMail::class)->first();
        $this->travel(31)->minutes();
        $this->get($mail->verificationUrl)->assertForbidden();
        $this->assertSame($before, $user->refresh()->email);
    }

    public function test_email_change_is_rejected_if_mail_delivery_is_not_configured(): void
    {
        config()->set('mail.default', 'log');
        $user = User::factory()->create(['password' => 'rahasia-siswa-1']);
        $this->actingAs($user)->post(route('profile.email.request'), [
            'email' => 'baru@example.com', 'current_password' => 'rahasia-siswa-1',
        ])->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }
}
