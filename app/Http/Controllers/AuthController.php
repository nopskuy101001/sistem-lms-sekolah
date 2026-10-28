<?php

namespace App\Http\Controllers;

use App\Models\User;
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

    public function showRegister(): View
    {
        return view('auth.register-modern');
    }

    /** Pendaftaran publik hanya untuk siswa. Akun guru/admin dibuat oleh admin. */
    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'nomor_induk' => ['nullable', 'string', 'max:50', 'unique:users,nomor_induk'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($data + ['role' => 'siswa']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Akun berhasil dibuat. Selamat datang!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
