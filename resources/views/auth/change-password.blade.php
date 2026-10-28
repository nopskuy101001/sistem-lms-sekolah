@extends('layouts.app')
@section('title', 'Ganti Password Awal')

@section('content')
<div class="mx-auto max-w-xl">
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 mb-5">
        <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Keamanan akun</p>
        <h1 class="mt-1 text-xl font-bold text-slate-900">Ganti password awal</h1>
        <p class="mt-2 text-sm leading-6 text-amber-900/80">Akun ini dibuat melalui import siswa. Demi keamanan, buat password baru sebelum mengakses LMS.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="rounded-2xl border border-slate-200 bg-white p-6 space-y-4">
        @csrf @method('PUT')
        <div>
            <label for="current_password" class="block text-sm font-medium mb-1">Password awal</label>
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Tanggal lahir format ddmmyyyy">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium mb-1">Password baru</label>
            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Minimal 8 karakter">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium mb-1">Ulangi password baru</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <button class="lms-button lms-button-primary w-full">Simpan password dan lanjutkan</button>
    </form>
</div>
@endsection
