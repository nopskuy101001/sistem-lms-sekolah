@extends('layouts.app')
@section('title', 'Pendaftaran Ditutup')

@section('content')
<div class="lms-auth-page">
    <section class="lms-auth-form-wrap">
        <a href="{{ route('login') }}" class="lms-brand lms-auth-brand">
            <span class="lms-brand-mark"><svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 10 9-6 9 6-9 6-9-6Z"/><path d="M7 13.5V18l5 3 5-3v-4.5"/></svg></span>
            <span><span class="lms-brand-title">RuangKelas</span><span class="lms-brand-subtitle" style="color:#7d89a2">SMK NEGERI 1 CERME</span></span>
        </a>

        <main class="lms-auth-main">
            <p class="lms-eyebrow">Akun dikelola sekolah</p>
            <h1>Pendaftaran mandiri ditutup</h1>
            <p>Hubungi administrator sekolah untuk memperoleh akun siswa.</p>
            <a href="{{ route('login') }}" class="lms-button lms-button-primary w-full mt-5">Kembali ke halaman masuk</a>
        </main>

        <footer class="lms-auth-footer">Seluruh akun dibuat dan dikelola oleh administrator sekolah.</footer>
    </section>

    <aside class="lms-auth-visual" aria-hidden="true">
        <div class="lms-auth-visual-content">
            <p class="lms-hero-kicker">Ruang belajar digital</p>
            <h2>Semua kebutuhan sekolah, lebih dekat dan teratur.</h2>
            <p>Dapatkan materi terbaru, pengingat tenggat, kuis, dan hasil belajar tanpa kehilangan informasi penting.</p>
            <div class="lms-feature-pills"><span>Mudah digunakan</span><span>Responsif</span><span>Terorganisir</span></div>
        </div>
    </aside>
</div>
@endsection
