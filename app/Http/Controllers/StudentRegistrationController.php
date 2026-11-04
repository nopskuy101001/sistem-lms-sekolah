<?php

namespace App\Http\Controllers;

use App\Mail\StudentRegistrationCodeMail;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/** Registrasi akun yang baru aktif setelah verifikasi OTP Gmail. Tidak ada migrasi DB. */
class StudentRegistrationController extends Controller
{
    private const PENDING_SESSION = 'lms_student_registration_pending_id';

    public function create(): View
    {
        return view('auth.register-modern');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'nomor_induk' => trim((string) $request->input('nomor_induk')),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'nomor_induk' => ['required', 'string', 'max:50', 'regex:/^(?=.*[A-Za-z0-9])[A-Za-z0-9.\-\/]+$/'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $email = $this->canonicalGmail($data['email']);
        if ($email === null) {
            throw ValidationException::withMessages(['email' => 'Gunakan Gmail yang valid (@gmail.com).']);
        }
        if (User::query()->where('email', $email)->orWhere('nomor_induk', $data['nomor_induk'])->exists()) {
            throw ValidationException::withMessages(['email' => 'Email atau NIS sudah digunakan. Jika akun sudah pernah dibuat, silakan login atau hubungi admin.']);
        }

        // role siswa_pending menjaga akun baru berbeda dari siswa lama yang dibuat admin.
        // Role siswa dan email_verified_at hanya diberikan setelah kode benar.
        try {
            $student = User::forceCreate([
                'name' => $data['name'],
                'nomor_induk' => $data['nomor_induk'],
                'email' => $email,
                'password' => Hash::make($data['password']),
                'role' => 'siswa_pending',
                'email_verified_at' => null,
                'must_change_password' => false,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => 'Email atau NIS sudah digunakan.']);
        }

        $request->session()->regenerate();
        $request->session()->put(self::PENDING_SESSION, $student->id);
        try {
            $this->issueCode($student);
            $message = 'Akun siswa berhasil dibuat (belum aktif). Kode verifikasi telah dikirim ke Gmail Anda.';
        } catch (Throwable $exception) {
            Log::warning('Student OTP email failed', ['user_id' => $student->id, 'error_type' => get_class($exception)]);
            $message = 'Akun sudah dibuat tetapi email verifikasi belum berhasil dikirim. Periksa SMTP, lalu gunakan Kirim Ulang Kode.';
        }
        return redirect()->route('register.verification')->with('registration_notice', $message);
    }

    public function notice(): RedirectResponse
    {
        return redirect()->route('register.verification');
    }

    public function verification(Request $request): View|RedirectResponse
    {
        $student = $this->pendingUser($request);
        if (!$student) {
            return redirect()->route('login')->withErrors(['login' => 'Untuk melanjutkan verifikasi, masuk dengan Gmail/NIS dan password akun yang baru dibuat.']);
        }
        return view('auth.registration-notice', [
            'emailMasked' => $this->maskEmail($student->email),
            'otpMinutes' => (int) config('lms_student_registration.otp_minutes', 10),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $student = $this->pendingUser($request);
        if (!$student) {
            return redirect()->route('login')->withErrors(['login' => 'Sesi verifikasi tidak tersedia. Masuk kembali dengan akun yang belum aktif.']);
        }
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $attemptKey = 'lms:student-otp-attempt:' . $student->id;
        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            throw ValidationException::withMessages(['code' => 'Terlalu banyak percobaan. Coba lagi setelah 15 menit atau minta bantuan admin.']);
        }
        $saved = Cache::get($this->otpKey($student));
        if (!is_array($saved) || !is_string($saved['hash'] ?? null)) {
            throw ValidationException::withMessages(['code' => 'Kode sudah kedaluwarsa atau belum terkirim. Klik Kirim Ulang Kode.']);
        }
        if (!Hash::check($data['code'], $saved['hash'])) {
            RateLimiter::hit($attemptKey, 900);
            throw ValidationException::withMessages(['code' => 'Kode verifikasi tidak sesuai.']);
        }

        // Update atomik mencegah dua request memverifikasi akun berulang kali.
        $updated = User::query()->whereKey($student->id)
            ->where('role', 'siswa_pending')->whereNull('email_verified_at')
            ->update(['role' => 'siswa', 'email_verified_at' => now()]);
        if ($updated !== 1) {
            return redirect()->route('login')->withErrors(['login' => 'Status akun telah berubah. Silakan masuk kembali.']);
        }
        Cache::forget($this->otpKey($student));
        Cache::forget($this->cooldownKey($student));
        RateLimiter::clear($attemptKey);
        $request->session()->forget(self::PENDING_SESSION);
        return redirect()->route('login')->with('success', 'Gmail berhasil diverifikasi. Akun siswa sekarang aktif, silakan masuk.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $student = $this->pendingUser($request);
        if (!$student) {
            return redirect()->route('login')->withErrors(['login' => 'Sesi verifikasi berakhir. Silakan login untuk melanjutkan.']);
        }
        try {
            $this->issueCode($student);
            return redirect()->route('register.verification')->with('registration_notice', 'Kode verifikasi baru sudah dikirim. Kode sebelumnya otomatis tidak berlaku.');
        } catch (Throwable $exception) {
            Log::warning('Student OTP resend failed', ['user_id' => $student->id, 'error_type' => get_class($exception)]);
            return redirect()->route('register.verification')->withErrors(['code' => 'Kode tidak dapat dikirim atau batas pengiriman tercapai. Tunggu sebentar dan periksa pengaturan SMTP.']);
        }
    }

    private function issueCode(User $student): void
    {
        if (in_array(config('mail.default'), ['log', 'array', 'failover'], true)) {
            throw new \RuntimeException('SMTP belum dikonfigurasi.');
        }
        $limitKey = 'lms:student-otp-send:' . $student->id;
        if (RateLimiter::tooManyAttempts($limitKey, 3)) {
            throw new \RuntimeException('Batas kirim kode per jam tercapai.');
        }
        if (!Cache::add($this->cooldownKey($student), true, now()->addSeconds(60))) {
            throw new \RuntimeException('Jeda pengiriman kode minimal 60 detik.');
        }
        $previous = Cache::get($this->otpKey($student));
        // Pastikan kode baru berbeda dari kode sebelumnya meskipun hasil random kebetulan sama.
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (is_array($previous) && isset($previous['hash']) && Hash::check($code, $previous['hash']));
        $minutes = max(5, min(30, (int) config('lms_student_registration.otp_minutes', 10)));
        try {
            if (!Cache::put($this->otpKey($student), [
                'hash' => Hash::make($code),
                'expires_at' => now()->addMinutes($minutes)->timestamp,
            ], now()->addMinutes($minutes))) {
                throw new \RuntimeException('Cache token tidak tersedia.');
            }
            Mail::to($student->email)->send(new StudentRegistrationCodeMail($code, $minutes));
            RateLimiter::hit($limitKey, 3600);
        } catch (Throwable $exception) {
            if (is_array($previous) && isset($previous['expires_at'])
                && (int) $previous['expires_at'] > now()->timestamp) {
                Cache::put($this->otpKey($student), $previous,
                    now()->addSeconds((int) $previous['expires_at'] - now()->timestamp));
            } else {
                Cache::forget($this->otpKey($student));
            }
            Cache::forget($this->cooldownKey($student));
            throw $exception;
        }
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::PENDING_SESSION);
        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            return null;
        }
        return User::query()->whereKey((int) $id)->where('role', 'siswa_pending')
            ->whereNull('email_verified_at')->first();
    }

    private function otpKey(User $student): string
    {
        return 'lms:student-otp:'. $student->id;
    }

    private function cooldownKey(User $student): string
    {
        return 'lms:student-otp:cooldown:' . $student->id;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        return substr($local, 0, 2) . str_repeat('*', max(2, min(8, strlen($local) - 2))) . '@' . $domain;
    }

    private function canonicalGmail(string $value): ?string
    {
        $email = strtolower(trim($value));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $parts = explode('@', $email);
        if (count($parts) !== 2 || !in_array($parts[1], ['gmail.com', 'googlemail.com'], true)) return null;
        $local = str_replace('.', '', explode('+', $parts[0], 2)[0]);
        if ($local === '' || strlen($local) > 64 || !preg_match('/^[a-z0-9]+$/D', $local)) return null;
        return $local . '@gmail.com';
    }
}
