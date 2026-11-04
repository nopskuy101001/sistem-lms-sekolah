<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak sesi siswa_pending yang dibuat sebelum registrasi ditutup.
 * Akun tersebut tidak dihapus atau diubah otomatis agar NIS dan histori
 * tetap dapat diaudit oleh admin sekolah.
 */
class RejectPendingStudent
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === 'siswa_pending') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->withErrors(['login' => 'Akun registrasi mandiri belum disetujui. Hubungi administrator sekolah.']);
        }
        return $next($request);
    }
}
