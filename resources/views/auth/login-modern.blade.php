@extends('layouts.app')
@section('title', 'Masuk')

@section('content')
<div class="lms-auth-page lms-auth-page-login">
    @if(file_exists(public_path('media/login-background.mp4')) || file_exists(public_path('media/login-background.webm')))
        <video class="lms-auth-video" autoplay muted loop playsinline preload="metadata" aria-hidden="true">
            @if(file_exists(public_path('media/login-background.webm')))<source src="{{ asset('media/login-background.webm') }}" type="video/webm">@endif
            @if(file_exists(public_path('media/login-background.mp4')))<source src="{{ asset('media/login-background.mp4') }}" type="video/mp4">@endif
        </video>
    @endif
    <div class="lms-auth-backdrop" aria-hidden="true"></div>
    <div class="lms-auth-container">
    <section class="lms-auth-form-wrap">
        <a href="{{ route('login') }}" class="lms-brand lms-auth-brand">
            <span class="lms-brand-mark"><svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 10 9-6 9 6-9 6-9-6Z"/><path d="M7 13.5V18l5 3 5-3v-4.5"/></svg></span>
            <span><span class="lms-brand-title">RuangKelas</span><span class="lms-brand-subtitle" style="color:#7d89a2">SMK NEGERI 1 CERME</span></span>
        </a>

        <main class="lms-auth-main">
            <h1>Masuk ke ruang belajarmu</h1>

            <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
                @csrf
                <div class="lms-field">
                    <label for="login">NIS atau alamat email</label>
                    <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username" placeholder="NIS atau nama@sekolah.id">
                </div>
                <div class="lms-field">
                    <label for="password">Kata sandi</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata sandi">
                </div>
                <label class="flex items-center gap-2.5 text-xs text-slate-500 cursor-pointer">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"> Ingat saya di perangkat ini
                </label>
                <button class="lms-button lms-button-primary w-full">Masuk ke RuangKelas</button>
            </form>

            <p class="text-xs text-slate-500 text-center mt-7">Siswa baru? <a href="{{ route('register') }}" class="font-bold text-indigo-600 hover:underline">Buat akun siswa</a></p>
        </main>

        <footer class="lms-auth-footer">© {{ date('Y') }} RuangKelas · Belajar lebih terarah</footer>
    </section>
    </div>
</div>
@endsection
