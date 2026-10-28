@extends('layouts.app')
@section('title', 'Daftar Siswa')

@section('content')
<div class="lms-auth-page">
    <section class="lms-auth-form-wrap">
        <a href="{{ route('login') }}" class="lms-brand lms-auth-brand">
            <span class="lms-brand-mark"><svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 10 9-6 9 6-9 6-9-6Z"/><path d="M7 13.5V18l5 3 5-3v-4.5"/></svg></span>
            <span><span class="lms-brand-title">RuangKelas</span><span class="lms-brand-subtitle" style="color:#7d89a2">SMK NEGERI 1 CERME</span></span>
        </a>

        <main class="lms-auth-main">
            <p class="lms-eyebrow">Akun siswa baru</p>
            <h1>Mulai perjalanan belajarmu</h1>
            <p>Lengkapi data berikut. Setelah terdaftar, kamu dapat langsung bergabung ke kelas dengan kode dari guru.</p>

            <form method="POST" action="{{ route('register.store') }}" class="space-y-3.5">
                @csrf
                <div class="lms-field"><label for="name">Nama lengkap</label><input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="Nama lengkap siswa"></div>
                <div class="grid sm:grid-cols-2 gap-3.5">
                    <div class="lms-field"><label for="nomor_induk">NIS <span class="font-normal text-slate-400">(opsional)</span></label><input id="nomor_induk" type="text" name="nomor_induk" value="{{ old('nomor_induk') }}" placeholder="Nomor induk"></div>
                    <div class="lms-field"><label for="email">Alamat email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="nama@sekolah.id"></div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3.5">
                    <div class="lms-field"><label for="password">Kata sandi</label><input id="password" type="password" name="password" required autocomplete="new-password" placeholder="Min. 8 karakter"></div>
                    <div class="lms-field"><label for="password_confirmation">Ulangi kata sandi</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Ulangi kata sandi"></div>
                </div>
                <button class="lms-button lms-button-primary w-full">Buat akun siswa</button>
            </form>

            <p class="text-xs text-slate-500 text-center mt-7">Sudah punya akun? <a href="{{ route('login') }}" class="font-bold text-indigo-600 hover:underline">Masuk di sini</a></p>
        </main>

        <footer class="lms-auth-footer">Akun guru dan admin dibuat oleh administrator sekolah.</footer>
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
