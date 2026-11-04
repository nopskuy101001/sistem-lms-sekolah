@extends('layouts.app')
@section('title', 'Masukkan Kode Verifikasi Gmail')
@section('content')
<div class="lms-auth-page">
    <section class="lms-auth-form-wrap">
        <a href="{{ route('login') }}" class="lms-brand lms-auth-brand"><span class="lms-brand-title">RuangKelas</span></a>
        <main class="lms-auth-main">
            <p class="lms-eyebrow">Tahap 2 dari 2 · Verifikasi email</p>
            <h1>Masukkan Kode Gmail</h1>
            @if(session('registration_notice'))<p role="status" class="lms-alert lms-alert-info">{{ session('registration_notice') }}</p>@endif
            <p>Akun sudah tercatat namun belum aktif. Periksa Gmail <strong>{{ $emailMasked }}</strong> (termasuk Spam), lalu masukkan 6 digit kode di bawah. Kode berlaku {{ $otpMinutes }} menit.</p>
            <form method="POST" action="{{ route('register.verify.code') }}" class="space-y-4 mt-5">
                @csrf
                <div class="lms-field"><label for="code">Kode verifikasi enam digit</label>
                    <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required autofocus placeholder="000000">
                    @error('code')<small role="alert" class="text-red-600">{{ $message }}</small>@enderror
                </div>
                <button type="submit" class="lms-button lms-button-primary w-full">Verifikasi &amp; Aktifkan Akun</button>
            </form>
            <form method="POST" action="{{ route('register.resend') }}" class="mt-4">
                @csrf
                <button type="submit" class="lms-button w-full">Kirim Ulang Kode</button>
            </form>
            <p class="text-xs text-slate-500 text-center mt-5">Kode belum masuk? Tunggu sekurangnya 60 detik sebelum mengirim ulang. <a href="{{ route('login') }}">Kembali ke login</a>.</p>
        </main>
    </section>
</div>
@endsection
