<?php

namespace Tests\Feature;

use App\Mail\StudentRegistrationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'mail.default' => 'smtp']);
        Cache::flush();
        Mail::fake();
    }

    private function registration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Siswa Baru',
            'nomor_induk' => 'NIS-2026001',
            'email' => 'Siswa.Baru+test@gmail.com',
            'password' => 'SandiKuat2026',
            'password_confirmation' => 'SandiKuat2026',
        ], $overrides);
    }

    private function lastSentOtp(): string
    {
        $code = '';
        Mail::assertSent(StudentRegistrationCodeMail::class, function (StudentRegistrationCodeMail $mail) use (&$code) {
            $code = $mail->verificationCode;
            return true;
        });
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        return $code;
    }

    public function test_registration_creates_pending_account_then_otp_activates_it(): void
    {
        $this->post(route('register.store'), $this->registration(['role' => 'admin']))
            ->assertRedirect(route('register.verification'));
        $user = User::where('nomor_induk', 'NIS-2026001')->firstOrFail();
        $this->assertSame('siswa_pending', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('siswabaru@gmail.com', $user->email);
        $this->get(route('register.verification'))->assertOk()->assertSee('Masukkan Kode Gmail');
        $this->post(route('register.verify.code'), ['code' => $this->lastSentOtp()])
            ->assertRedirect(route('login'));
        $user->refresh();
        $this->assertSame('siswa', $user->role);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_pending_account_cannot_login_but_can_resume_verification(): void
    {
        $this->post(route('register.store'), $this->registration());
        $this->post(route('login.attempt'), ['login'=>'NIS-2026001','password'=>'SandiKuat2026'])
            ->assertRedirect(route('register.verification'));
        $this->assertGuest();
        $this->get(route('register.verification'))->assertOk();
    }

    public function test_invalid_code_is_rejected_and_resend_invalidates_old_code(): void
    {
        $this->post(route('register.store'), $this->registration());
        $old = $this->lastSentOtp();
        $bad = $old === '000000' ? '000001' : '000000';
        $this->post(route('register.verify.code'), ['code' => $bad])->assertSessionHasErrors('code');
        $this->assertSame('siswa_pending', User::where('nomor_induk','NIS-2026001')->firstOrFail()->role);
        $this->travel(61)->seconds();
        $this->post(route('register.resend'))->assertRedirect(route('register.verification'));
        Mail::assertSentCount(2);
        $this->post(route('register.verify.code'), ['code' => $old])->assertSessionHasErrors('code');
        $this->assertNull(User::where('nomor_induk','NIS-2026001')->firstOrFail()->email_verified_at);
    }

    public function test_expired_otp_cannot_activate_account(): void
    {
        $this->post(route('register.store'), $this->registration());
        $code = $this->lastSentOtp();
        $this->travel(11)->minutes();
        $this->post(route('register.verify.code'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertSame('siswa_pending', User::where('nomor_induk', 'NIS-2026001')->firstOrFail()->role);
    }

    public function test_registration_page_has_no_school_invitation_field(): void
    {
        $this->get(route('register'))->assertOk()->assertDontSee('name="invite_code"', false)
            ->assertSee('Buat Akun &amp; Kirim Kode Gmail', false);
    }

    public function test_rejects_non_gmail_and_duplicate_nis(): void
    {
        $this->post(route('register.store'), $this->registration(['email'=>'siswa@yahoo.com']))->assertSessionHasErrors('email');
        User::factory()->create(['role'=>'siswa', 'nomor_induk'=>'NIS-2026001']);
        $this->post(route('register.store'), $this->registration())->assertSessionHasErrors('email');
    }

    public function test_legacy_admin_created_student_does_not_require_new_otp(): void
    {
        $student = User::factory()->create(['role'=>'siswa','nomor_induk'=>'LEGACY-2026', 'email_verified_at'=>null, 'must_change_password'=>true]);
        $this->assertSame('siswa', $student->role);
        $this->post(route('login.attempt'), ['login'=>'LEGACY-2026', 'password'=>'password'])
            ->assertRedirect(route('password.change'));
        $this->assertAuthenticatedAs($student);
    }
}
