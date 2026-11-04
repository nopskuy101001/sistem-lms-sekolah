<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login-modern');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required'],
        ]);

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'nomor_induk';
        $credentials = [$field => trim($data['login']), 'password' => $data['password']];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if ($request->user()->role === 'siswa_pending') {
                $id = $request->user()->id;
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $request->session()->put('lms_student_registration_pending_id', $id);
                return redirect()->route('register.verification')
                    ->with('registration_notice', 'Akun belum terverifikasi. Masukkan kode Gmail atau klik Kirim Ulang Kode.');
            }
            $request->session()->regenerate();

            if ($request->user()->must_change_password) {
                return redirect()->route('password.change');
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withErrors(['login' => 'NIS/email atau password salah.'])
            ->onlyInput('login');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
