<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\LmsPasswordResetNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Pemulihan mandiri melalui email TERDAFTAR. Pengenal dapat berupa NIS/email,
 * tetapi alamat tujuan selalu diambil dari akun yang ditemukan di database.
 */
class ForgotPasswordController extends Controller
{
    public function showRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
        ]);
        $input = trim($data['login']);
        $notice = 'Jika NIS/email cocok dengan akun aktif yang memiliki email asli, tautan reset akan dikirim. Periksa kotak masuk atau spam. Jika email belum tersedia, hubungi admin sekolah.';

        // Don't pretend that log/array transports actually deliver email.
        $mailer = (string) config('mail.default');
        if (! in_array($mailer, ['smtp', 'ses', 'postmark', 'mailgun', 'resend', 'sendmail'], true)) {
            return back()->withErrors(['login' => 'Layanan pengiriman email belum dikonfigurasi oleh admin sekolah.']);
        }

        $user = filter_var($input, FILTER_VALIDATE_EMAIL)
            ? User::query()->where('email', mb_strtolower($input))->first()
            : User::query()->where('nomor_induk', $input)->first();

        // Don't reveal existence of account, email, NIS, or pending registration.
        if (! $user || ! $this->canRecover($user)) {
            return back()->with('status', $notice);
        }

        try {
            Password::broker()->sendResetLink(
                ['email' => $user->email],
                static function (User $recipient, string $token): void {
                    $recipient->notify(new LmsPasswordResetNotification($token));
                },
            );
        } catch (\Throwable $exception) {
            report($exception);
            // The response is deliberately the same for existing and absent accounts.
        }

        return back()->with('status', $notice);
    }

    public function showResetForm(Request $request, string $token): Response
    {
        return response()->view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ])->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);
        $data['email'] = mb_strtolower(trim($data['email']));

        // A valid broker token never grants access to an inactive/public-registration account.
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! $this->canRecover($user)) {
            return back()->withErrors(['email' => 'Tautan tidak valid atau telah kedaluwarsa. Minta tautan baru.']);
        }

        $status = Password::broker()->reset($data, function (User $account, string $password): void {
            $account->forceFill([
                'password' => $password, // User model casts password as hashed.
                'remember_token' => Str::random(60),
                'must_change_password' => false,
            ])->save();

            // If sessions are stored in the application's database, revoke old logins.
            // No destructive DDL or session operation is performed for other drivers.
            if (config('session.driver') === 'database') {
                try {
                    \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))
                        ->where('user_id', $account->getKey())->delete();
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }

            event(new PasswordReset($account));
        });

        if ($status === Password::PASSWORD_RESET) {
            // A reset may be completed while logged in as this or another user.
            // End the browser session before redirecting to guest-only /login.
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Password berhasil direset. Silakan masuk dengan password baru.');
        }

        return back()->withErrors(['email' => 'Tautan reset tidak valid, sudah digunakan, atau kedaluwarsa. Minta tautan baru.']);
    }

    private function canRecover(User $user): bool
    {
        $mail = mb_strtolower(trim((string) $user->email));
        $domain = substr(strrchr($mail, '@') ?: '', 1);

        return in_array($user->role, ['admin', 'guru', 'siswa'], true)
            && filter_var($mail, FILTER_VALIDATE_EMAIL) !== false
            && $domain !== ''
            && ! str_ends_with($domain, '.local')
            && ! in_array($domain, ['localhost', 'invalid'], true);
    }
}
