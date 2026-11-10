@extends('layouts.app')
@section('title', 'Atur Ulang Password')

@section('content')
<div class="lms-auth-page lms-auth-page-login">
    <div class="lms-auth-backdrop" aria-hidden="true"></div>
    <div class="lms-auth-container">
        <section class="lms-auth-form-wrap">
            <a href="{{ route('login') }}" class="lms-brand lms-auth-brand">
                <span class="lms-brand-mark" aria-hidden="true"><img src="{{ asset('media/logo-smkn1-cerme.png') }}" alt="" class="lms-brand-logo"></span>
                <span><span class="lms-brand-title">RuangKelas</span><span class="lms-brand-subtitle">SMK NEGERI 1 CERME</span></span>
            </a>
            <main class="lms-auth-main">
                <h1>Buat password baru</h1>
                <p class="text-sm text-slate-600 mt-3 mb-5">Masukkan password baru dan ulangi untuk konfirmasi. Tautan reset berlaku 30 menit.</p>
                <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="email" value="{{ $email }}">
                    @error('email')<p role="alert" class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <div class="lms-field">
                        <label for="password">Password Baru</label>
                        <x-password-input id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter" />
                        @error('password')<p role="alert" class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="lms-field">
                        <label for="password_confirmation">Konfirmasi Password Baru</label>
                        <x-password-input id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password" placeholder="Ulangi password baru" />
                    </div>
                    <button type="submit" class="lms-button lms-button-primary w-full">Simpan Password Baru</button>
                </form>
                <p class="text-center mt-5"><a href="{{ route('password.request') }}" class="lms-forgot-link">Minta tautan baru</a></p>
            </main>
            <footer class="lms-auth-footer"><x-copyright :compact="true" /></footer>
        </section>
    </div>
</div>
@endsection
