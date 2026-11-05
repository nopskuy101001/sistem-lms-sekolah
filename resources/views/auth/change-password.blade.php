@extends('layouts.app')
@section('title', auth()->user()->must_change_password ? 'Ganti Password Awal' : 'Ganti Password')

@section('content')
<div class="mx-auto max-w-xl">
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 mb-5">
        <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Keamanan akun</p>
        <h1 class="mt-1 text-xl font-bold text-slate-900">{{ auth()->user()->must_change_password ? 'Ganti password awal' : 'Ganti password' }}</h1>
        <p class="mt-2 text-sm leading-6 text-amber-900/80">{{ auth()->user()->must_change_password ? 'Akun ini masih memakai password awal dari administrator. Kamu dapat membuat password baru sekarang atau menundanya jika belum menentukan password yang mudah diingat.' : 'Masukkan password saat ini, lalu buat password baru untuk akunmu.' }}</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="rounded-2xl border border-slate-200 bg-white p-6 space-y-4">
        @csrf @method('PUT')
        <div>
            <label for="current_password" class="block text-sm font-medium mb-1">{{ auth()->user()->must_change_password ? 'Password awal' : 'Password saat ini' }}</label>
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
        <button class="lms-button lms-button-primary w-full">{{ auth()->user()->must_change_password ? 'Simpan password dan lanjutkan' : 'Simpan password baru' }}</button>
    </form>

    @if(auth()->user()->must_change_password)
        <form method="POST" action="{{ route('password.defer') }}" class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-center">
            @csrf
            <button type="submit" class="lms-button w-full border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">Nanti saja</button>
            <p class="mt-2 text-xs leading-5 text-slate-500">Kamu dapat masuk ke LMS sekarang. Pengingat akan muncul lagi saat login berikutnya, atau ubah password kapan saja melalui halaman Profil.</p>
        </form>
    @endif
</div>
@endsection
