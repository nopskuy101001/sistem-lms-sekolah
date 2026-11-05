<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $wasRequired = $request->user()->must_change_password;
        $request->user()->update([
            'password' => $data['password'],
            'must_change_password' => false,
        ]);

        $request->session()->regenerate();
        $request->session()->forget('password_change_deferred');

        return redirect()->route($wasRequired ? 'dashboard' : 'profile.edit')
            ->with('success', 'Password berhasil diperbarui.');
    }

    public function defer(Request $request): RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        // Tetap simpan kewajiban di akun. Pilihan ini hanya berlaku untuk sesi
        // saat ini sehingga pengingat muncul kembali pada login berikutnya.
        $request->session()->put('password_change_deferred', true);

        return redirect()->route('dashboard')->with(
            'success',
            'Penggantian password ditunda. Kamu akan diminta kembali saat login berikutnya dan tetap dapat menggantinya melalui Profil.'
        );
    }
}
