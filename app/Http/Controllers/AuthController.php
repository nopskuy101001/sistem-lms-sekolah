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

        $login = trim($data['login']);
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $credentials = ['email' => mb_strtolower($login), 'password' => $data['password']];
        } else {
            $account = User::query()
                ->where('username', mb_strtolower($login))
                ->orWhere('nomor_induk', $login)
                ->first();
            $credentials = $account
                ? ['id' => $account->id, 'password' => $data['password']]
                : ['nomor_induk' => $login, 'password' => $data['password']];
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if ($request->user()->role === 'siswa_pending') {
                // Akun registrasi publik lama tidak boleh diaktifkan melalui login.
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['login' => 'Registrasi mandiri sudah ditutup. Hubungi administrator sekolah untuk pemeriksaan akun dan NIS.']);
            }
            $request->session()->regenerate();

            if ($request->user()->must_change_password) {
                $request->session()->forget('password_change_deferred');

                return redirect()->route('password.change');
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withErrors(['login' => 'Username, NIS/NIP, email, atau password salah.'])
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
