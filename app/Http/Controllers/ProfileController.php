<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Mail\ProfileEmailChangeMail;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /**
     * Begin a verified email update. The existing email remains active until
     * the recipient confirms the new address from their inbox.
     */
    public function requestEmailChange(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'current_password'],
        ]);

        if (strcasecmp($data['email'], (string) $user->email) === 0) {
            throw ValidationException::withMessages(['email' => 'Alamat email baru harus berbeda dari email saat ini.']);
        }

        // A log/array mail transport cannot prove inbox ownership.
        $mailer = (string) config('mail.default');
        if (! in_array($mailer, ['smtp', 'ses', 'postmark', 'mailgun', 'resend'], true) || (app()->isProduction() && (string) config('cache.default') === 'array')) {
            throw ValidationException::withMessages(['email' => 'Verifikasi email belum tersedia. Minta admin mengaktifkan SMTP/email delivery dan cache persisten.']);
        }

        $token = bin2hex(random_bytes(32));
        $pending = [
            'old_email' => (string) $user->email,
            'new_email' => $data['email'],
            'token_hash' => hash('sha256', $token),
        ];
        $key = $this->emailPendingKey((int) $user->getKey());
        Cache::put($key, $pending, now()->addMinutes(30));
        $link = URL::temporarySignedRoute(
            'profile.email.confirm', now()->addMinutes(30),
            ['user' => $user->getKey(), 'token' => $token],
        );

        try {
            Mail::to($data['email'])->send(new ProfileEmailChangeMail($user->name, $data['email'], $link));
        } catch (\Throwable $exception) {
            // Preserve any *newer* pending request if requests overlapped.
            $saved = Cache::get($key);
            if (is_array($saved) && hash_equals((string) ($saved['token_hash'] ?? ''), $pending['token_hash'])) {
                Cache::forget($key);
            }
            report($exception);
            throw ValidationException::withMessages(['email' => 'Email verifikasi gagal dikirim. Periksa SMTP dan coba lagi.']);
        }

        return back()->with('success', 'Tautan konfirmasi telah dikirim ke email baru. Email lama tetap aktif hingga tautan dibuka (maksimal 30 menit).');
    }

    /**
     * The link is signed, expires after 30 minutes and is bound to the same
     * authenticated user who requested the email change.
     */
    public function confirmEmailChange(Request $request, int $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless((int) $actor->getKey() === $user, 403);
        $token = (string) $request->query('token', '');
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            abort(403);
        }
        $key = $this->emailPendingKey($user);
        $pending = Cache::get($key);
        if (!is_array($pending) || !hash_equals((string) ($pending['token_hash'] ?? ''), hash('sha256', $token))) {
            return redirect()->route('profile.edit')->withErrors(['email' => 'Tautan sudah digunakan, dibatalkan, atau kedaluwarsa. Ulangi permintaan.']);
        }

        try {
            DB::transaction(function () use ($actor, $pending): void {
                $locked = User::query()->lockForUpdate()->findOrFail($actor->getKey());
                if ($locked->email !== $pending['old_email']) {
                    throw ValidationException::withMessages(['email' => 'Email akun sudah berubah. Silakan ajukan ulang.']);
                }
                $newEmail = (string) $pending['new_email'];
                if (User::query()->where('email', $newEmail)->whereKeyNot($locked->getKey())->exists()) {
                    throw ValidationException::withMessages(['email' => 'Alamat email tersebut sudah digunakan akun lain.']);
                }
                $locked->forceFill(['email' => $newEmail, 'email_verified_at' => now()])->save();
            });
        } catch (QueryException $exception) {
            // Handle a concurrent unique-index conflict without leaking SQL.
            report($exception);
            return redirect()->route('profile.edit')->withErrors(['email' => 'Email sudah digunakan atau tidak dapat disimpan. Hubungi admin bila masalah berulang.']);
        }

        Cache::forget($key);
        $request->session()->regenerate();
        return redirect()->route('profile.edit')->with('success', 'Email baru berhasil diverifikasi dan digunakan untuk login. NIS dan role tidak berubah.');
    }

    private function emailPendingKey(int $userId): string
    {
        return 'lms-profile:email-change:' . $userId;
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'profile_photo_zoom' => ['sometimes', 'integer', 'between:100,300'],
            'profile_photo_position_x' => ['sometimes', 'integer', 'between:0,100'],
            'profile_photo_position_y' => ['sometimes', 'integer', 'between:0,100'],
        ]);

        $user = $request->user();
        $oldPath = $user->profile_photo_path;
        if (! $request->hasFile('profile_photo') && ! $oldPath) {
            throw ValidationException::withMessages(['profile_photo' => 'Pilih foto profil terlebih dahulu.']);
        }

        $newPath = $request->hasFile('profile_photo')
            ? $data['profile_photo']->store('profile-photos', 'public')
            : null;
        if ($request->hasFile('profile_photo') && ! $newPath) {
            return back()->withErrors(['profile_photo' => 'Foto profil gagal disimpan. Coba unggah kembali.']);
        }

        try {
            $user->update([
                'profile_photo_path' => $newPath ?: $oldPath,
                'profile_photo_zoom' => (int) ($data['profile_photo_zoom'] ?? $user->profile_photo_zoom ?? 100),
                'profile_photo_position_x' => (int) ($data['profile_photo_position_x'] ?? $user->profile_photo_position_x ?? 50),
                'profile_photo_position_y' => (int) ($data['profile_photo_position_y'] ?? $user->profile_photo_position_y ?? 50),
            ]);
        } catch (\Throwable $exception) {
            if ($newPath) Storage::disk('public')->delete($newPath);
            throw $exception;
        }

        if ($newPath && $oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $oldPath = $user->profile_photo_path;

        if ($oldPath) {
            $user->update([
                'profile_photo_path' => null,
                'profile_photo_zoom' => 100,
                'profile_photo_position_x' => 50,
                'profile_photo_position_y' => 50,
            ]);
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', 'Foto profil dihapus. Avatar kembali menggunakan inisial nama.');
    }
}
