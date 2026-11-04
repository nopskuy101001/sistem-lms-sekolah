@extends('layouts.app')
@section('title', 'Registrasi Siswa')
@section('content')
<div class="lms-auth-page">
    <section class="lms-auth-form-wrap">
        <a href="{{ route('login') }}" class="lms-brand lms-auth-brand">
            <span class="lms-brand-mark" aria-hidden="true">✦</span>
            <span><span class="lms-brand-title">RuangKelas</span><span class="lms-brand-subtitle">SMK NEGERI 1 CERME</span></span>
        </a>
        <main class="lms-auth-main">
            <p class="lms-eyebrow">Tahap 1 dari 2 · Daftar akun siswa</p>
            <h1>Buat akun RuangKelas</h1>
            <p>Akun akan dibuat dengan status <strong>belum terverifikasi</strong>. Setelah mendaftar, kode enam digit dikirim ke Gmail dan dimasukkan pada halaman berikutnya.</p>
            @if ($errors->any())
                <div class="lms-alert lms-alert-error mt-4" role="alert">
                    <p>Registrasi belum dapat dilanjutkan:</p>
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ route('register.store') }}" class="space-y-4 mt-5">
                @csrf
                <div class="lms-field"><label for="name">Nama lengkap</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name" placeholder="Nama sesuai data sekolah">
                </div>
                <div class="lms-field"><label for="nomor_induk">NIS</label>
                    <input type="text" id="nomor_induk" name="nomor_induk" value="{{ old('nomor_induk') }}" required maxlength="50" placeholder="Nomor Induk Siswa">
                </div>
                <div class="lms-field"><label for="email">Alamat Gmail aktif</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" maxlength="255" placeholder="contoh@gmail.com">
                </div>
                <div class="lms-field"><label for="password">Kata sandi (minimal 10 karakter, huruf dan angka)</label>
                    <input type="password" id="password" name="password" required minlength="10" autocomplete="new-password">
                </div>
                <div class="lms-field"><label for="password_confirmation">Ulangi kata sandi</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="10" autocomplete="new-password">
                </div>
                <button type="submit" class="lms-button lms-button-primary w-full">Buat Akun &amp; Kirim Kode Gmail</button>
            </form>
            <p class="text-xs text-slate-500 text-center mt-5">Sudah punya akun atau perlu melanjutkan verifikasi? <a href="{{ route('login') }}">Masuk di sini</a>.</p>
        </main>
    </section>
    <aside class="lms-auth-visual" aria-hidden="true"><div class="lms-auth-visual-content"><p class="lms-hero-kicker">Akun siswa</p><h2>Daftar dulu, verifikasi kemudian.</h2><p>Periksa Gmail untuk mengaktifkan akun.</p></div></aside>
</div>
@endsection
