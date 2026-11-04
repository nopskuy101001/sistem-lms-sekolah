@extends('layouts.app')
@section('title', 'Lupa Password')

@section('content')
<div class="lms-auth-page lms-auth-page-login">
    <div class="lms-auth-backdrop" aria-hidden="true"></div>
    <div class="lms-auth-container">
        <section class="lms-auth-form-wrap">
            <a href="{{ route('login') }}" class="lms-brand lms-auth-brand">
                <span class="lms-brand-mark" aria-hidden="true">&#x1F512;</span>
                <span><span class="lms-brand-title">RuangKelas</span><span class="lms-brand-subtitle">SMK NEGERI 1 CERME</span></span>
            </a>
            <main class="lms-auth-main">
                <h1>Lupa password?</h1>
                <p class="text-sm text-slate-600 mt-3 mb-5">Masukkan NIS atau email yang terdaftar. Jika akun memiliki email aktif, kami akan mengirim tautan untuk membuat password baru.</p>
                @if(session('status'))
                    <div role="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>
                @endif
                <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                    @csrf
                    <div class="lms-field">
                        <label for="login">NIS atau Email</label>
                        <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus maxlength="255" autocomplete="username" placeholder="Masukkan NIS atau email terdaftar">
                        @error('login')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="lms-button lms-button-primary w-full">Kirim Link Reset</button>
                </form>
                <p class="text-xs text-slate-500 mt-4">Belum memiliki email asli pada akun? Hubungi administrator sekolah. NIS saja tidak cukup untuk menerima email.</p>
                <p class="text-center mt-5"><a href="{{ route('login') }}" class="lms-forgot-link">Kembali ke Login</a></p>
            </main>
            <footer class="lms-auth-footer">© {{ date('Y') }} RuangKelas</footer>
        </section>
    </div>
</div>
@endsection
