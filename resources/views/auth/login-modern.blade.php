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
            <span class="lms-brand-mark" aria-hidden="true"><img src="{{ asset('media/logo-smkn1-cerme.png') }}" alt="" class="lms-brand-logo"></span>
            <span><span class="lms-brand-title">RuangKelas</span><span class="lms-brand-subtitle" style="color:#7d89a2">SMK NEGERI 1 CERME</span></span>
        </a>

        <main class="lms-auth-main">
            <h1 class="lms-login-heading">Masuk ke ruang belajarmu</h1>
            @if(session('success'))<div role="status" class="lms-alert lms-alert-success">{{ session('success') }}</div>@endif
            @if(session('status'))
                <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 mt-3">{{ session('status') }}</div>
            @endif

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
                <div class="flex justify-end">
                    <a href="{{ route('password.request') }}" class="lms-forgot-link">Lupa password?</a>
                </div>
                <label class="flex items-center gap-2.5 text-xs text-slate-500 cursor-pointer">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"> Ingat saya di perangkat ini
                </label>
                <button class="lms-button lms-button-primary lms-login-submit w-full"><span>Masuk ke RuangKelas</span></button>
            </form>

            <p class="text-xs text-slate-500 text-center mt-7">Belum memiliki akun? Hubungi administrator sekolah untuk memperoleh akun siswa resmi.</p>
        </main>

        <footer class="lms-auth-footer"><x-copyright :compact="true" /></footer>
    </section>
    </div>
</div>
@endsection
