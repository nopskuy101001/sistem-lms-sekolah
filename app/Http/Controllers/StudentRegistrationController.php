<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Semua jalur registrasi publik dinonaktifkan secara permanen.
 * Stub ini sengaja dipertahankan agar route cache lama tidak dapat
 * membuat akun atau mengubah status siswa sebelum cache dibersihkan.
 */
class StudentRegistrationController extends Controller
{
    public function create(): never { abort(404); }
    public function store(Request $request): never { abort(404); }
    public function notice(): never { abort(404); }
    public function verify(Request $request, ?string $token = null): never { abort(404); }
    public function verification(Request $request): never { abort(404); }
    public function confirm(Request $request): never { abort(404); }
    public function resend(Request $request): never { abort(404); }
}
