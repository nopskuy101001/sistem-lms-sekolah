@extends('layouts.app')
@section('title', 'Panduan Penggunaan')

@section('content')
@php($user = auth()->user())

<header class="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-indigo-800 p-6 text-white shadow-xl shadow-indigo-100 sm:p-8">
    <div class="absolute -right-12 -top-14 h-48 w-48 rounded-full border-[30px] border-white/5" aria-hidden="true"></div>
    <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[.2em] text-indigo-200">Pusat bantuan RuangKelas</p>
            <h1 class="mt-2 text-3xl font-bold">Panduan untuk {{ $user->roleLabel() }}</h1>
            <p class="mt-2 text-sm leading-6 text-indigo-100/90">Pelajari alur utama sesuai hak akses akunmu, mulai dari langkah pertama hingga pengelolaan aktivitas belajar.</p>
        </div>
        <div class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-indigo-100 backdrop-blur-sm">
            <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.09 9a3 3 0 1 1 5.83 1c0 2-2.92 2-2.92 4"/><path d="M12 17h.01"/></svg>
        </div>
    </div>
</header>

<nav aria-label="Daftar isi panduan" class="guide-toc mb-7 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-3 shadow-sm" data-guide-toc>
    <a href="#mulai" class="is-active" data-guide-link aria-current="location"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m13 2-9 11h7l-1 9 9-12h-7z"/></svg><span>Mulai cepat</span></a>
    <a href="#panduan-role" data-guide-link><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg><span>Panduan {{ $user->roleLabel() }}</span></a>
    <a href="#akun" data-guide-link><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg><span>Akun &amp; keamanan</span></a>
    <a href="#faq" data-guide-link><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.09 9a3 3 0 1 1 5.83 1c0 2-2.92 2-2.92 4M12 17h.01"/></svg><span>Pertanyaan umum</span></a>
</nav>

<section id="mulai" aria-labelledby="mulai-heading" class="mb-6 scroll-mt-24">
    <div class="mb-4">
        <p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Dasar penggunaan</p>
        <h2 id="mulai-heading" class="mt-1 text-xl font-bold text-slate-900">Mulai cepat</h2>
    </div>
    <div class="grid gap-4 md:grid-cols-3">
        <article class="guide-quick-card flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between"><span class="grid h-11 w-11 place-items-center rounded-xl bg-indigo-50 text-indigo-700"><svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span><span class="text-xs font-bold uppercase tracking-wider text-slate-400">Langkah 1</span></div>
            <h3 class="mt-4 font-bold text-slate-900">Lengkapi profil</h3>
            <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">Periksa nama, email, NIS/NIP, dan foto profil agar identitas akun mudah dikenali.</p>
            <a href="{{ route('profile.edit') }}" class="mt-4 inline-flex text-sm font-semibold text-indigo-700 hover:underline">Buka profil →</a>
        </article>
        <article class="guide-quick-card flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between"><span class="grid h-11 w-11 place-items-center rounded-xl bg-sky-50 text-sky-700"><svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg></span><span class="text-xs font-bold uppercase tracking-wider text-slate-400">Langkah 2</span></div>
            <h3 class="mt-4 font-bold text-slate-900">Kenali beranda</h3>
            <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">Beranda merangkum kelas, agenda terdekat, aktivitas, dan informasi penting sesuai role.</p>
            <a href="{{ route('dashboard') }}" class="mt-4 inline-flex text-sm font-semibold text-indigo-700 hover:underline">Buka beranda →</a>
        </article>
        <article class="guide-quick-card flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between"><span class="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg></span><span class="text-xs font-bold uppercase tracking-wider text-slate-400">Langkah 3</span></div>
            <h3 class="mt-4 font-bold text-slate-900">Pantau notifikasi</h3>
            <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">Ikon lonceng memberi tahu aktivitas baru, pengumpulan, penilaian, dan validasi transkrip.</p>
            <a href="{{ route('notifications.index') }}" class="mt-4 inline-flex text-sm font-semibold text-indigo-700 hover:underline">Lihat notifikasi →</a>
        </article>
    </div>
</section>

<section id="panduan-role" aria-labelledby="role-heading" class="mb-6 scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-5 flex items-center gap-3">
        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-700">
            @if($user->isAdmin())
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
            @elseif($user->isGuru())
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 10 9-5 9 5-9 5z"/><path d="M7 12.5V17c3 2 7 2 10 0v-4.5M21 10v6"/></svg>
            @else
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg>
            @endif
        </div>
        <div><p class="text-xs font-bold uppercase tracking-wider text-violet-600">Sesuai hak akses</p><h2 id="role-heading" class="text-xl font-bold text-slate-900">Panduan {{ $user->roleLabel() }}</h2></div>
    </div>

    @if($user->isAdmin())
        <ol class="guide-step-grid grid gap-4 lg:grid-cols-2">
            <li class="guide-step-card"><strong class="text-slate-900">Kelola akun pengguna</strong><p class="mt-1 text-sm leading-6 text-slate-600">Buka Kelola Pengguna untuk membuat akun siswa, guru, atau admin. Role hanya ditentukan saat akun dibuat.</p></li>
            <li class="guide-step-card"><strong class="text-slate-900">Import siswa massal</strong><p class="mt-1 text-sm leading-6 text-slate-600">Unduh template CSV/XLSX, isi NIS, nama, tanggal lahir, email, dan jenis kelamin, lalu unggah file. Password awal dibuat dari tanggal lahir dalam format ddmmyyyy.</p></li>
            <li class="guide-step-card"><strong class="text-slate-900">Reset password</strong><p class="mt-1 text-sm leading-6 text-slate-600">Gunakan reset oleh admin jika guru atau siswa kehilangan akses. Pengguna akan diminta mengganti password awal saat login.</p></li>
            <li class="guide-step-card"><strong class="text-slate-900">Periksa akun sebelum menghapus</strong><p class="mt-1 text-sm leading-6 text-slate-600">Pastikan akun dan keterkaitannya sudah benar karena penghapusan pengguna dapat memengaruhi riwayat akademik.</p></li>
        </ol>
        <a href="{{ route('admin.users.index') }}" class="lms-button lms-button-primary mt-5">Buka Kelola Pengguna</a>
    @elseif($user->isGuru())
        <ol class="guide-step-grid grid gap-4 lg:grid-cols-2">
            <li class="guide-step-card"><strong class="text-slate-900">Buat dan atur kelas</strong><p class="mt-1 text-sm leading-6 text-slate-600">Buat kelas dari beranda, pilih tema, lalu bagikan kode kelas. Kode dapat diperbarui bila perlu membatasi siswa yang terlambat bergabung.</p></li>
            <li class="guide-step-card"><strong class="text-slate-900">Tambahkan pembelajaran</strong><p class="mt-1 text-sm leading-6 text-slate-600">Unggah materi, tugas, kuis, atau video task dari halaman kelas. Periksa status draf sebelum memublikasikan aktivitas.</p></li>
            <li class="guide-step-card"><strong class="text-slate-900">Kelola kuis dan bank soal</strong><p class="mt-1 text-sm leading-6 text-slate-600">Gunakan import soal massal, stimulus TKA, pengacakan, keamanan fokus, dan bank soal agar paket dapat digunakan kembali.</p></li>
            <li class="guide-step-card"><strong class="text-slate-900">Validasi transkrip</strong><p class="mt-1 text-sm leading-6 text-slate-600">Periksa seluruh nilai dan koreksi bila diperlukan. Setelah Final Validation, hasil siswa dibekukan dan dapat diekspor atau dicetak.</p></li>
        </ol>
        <div class="mt-5 flex flex-wrap gap-2"><a href="{{ route('dashboard') }}#kelas" class="lms-button lms-button-primary">Buka kelas</a><a href="{{ route('question-banks.index') }}" class="lms-button border border-slate-300 bg-white text-slate-700">Bank soal</a><a href="{{ route('transcripts.index') }}" class="lms-button border border-slate-300 bg-white text-slate-700">Transkrip</a></div>
    @else
        <ol class="guide-step-grid grid gap-4 lg:grid-cols-2">
            <li class="guide-step-card"><strong class="text-slate-900">Bergabung ke kelas</strong><p class="mt-1 text-sm leading-6 text-slate-600">Masukkan kode terbaru dari guru pada beranda. Jika kode tidak berlaku, minta kode kelas yang baru kepada guru.</p></li>
            <li class="guide-step-card"><strong class="text-slate-900">Ikuti aktivitas kelas</strong><p class="mt-1 text-sm leading-6 text-slate-600">Buka tab tugas kelas untuk membaca materi, mengumpulkan tugas, mengerjakan kuis, dan menyelesaikan video task.</p></li>
            <li class="guide-step-card"><strong class="text-slate-900">Patuhi keamanan kuis</strong><p class="mt-1 text-sm leading-6 text-slate-600">Jika kuis memakai layar penuh atau pemantauan fokus, jangan berpindah tab. Pelanggaran dapat dicatat dan mengurangi nilai sesuai pengaturan guru.</p></li>
            <li class="guide-step-card"><strong class="text-slate-900">Lihat transkrip</strong><p class="mt-1 text-sm leading-6 text-slate-600">Nilai kelas muncul pada Transkrip Saya setelah guru melakukan Final Validation. Transkrip dapat dibuka sebagai pratinjau dan diunduh sebagai PDF.</p></li>
        </ol>
        <div class="mt-5 flex flex-wrap gap-2"><a href="{{ route('dashboard') }}#kelas" class="lms-button lms-button-primary">Buka kelas saya</a><a href="{{ route('student.transcripts.index') }}" class="lms-button border border-slate-300 bg-white text-slate-700">Transkrip saya</a></div>
    @endif
</section>

<section id="akun" aria-labelledby="akun-heading" class="mb-6 scroll-mt-24">
    <div class="mb-4"><p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Berlaku untuk semua role</p><h2 id="akun-heading" class="mt-1 text-xl font-bold text-slate-900">Akun dan keamanan</h2></div>
    <div class="grid gap-4 md:grid-cols-2">
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"><h3 class="font-bold text-emerald-950">Jaga keamanan akun</h3><ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-emerald-900"><li>Ganti password awal dengan password yang tidak mudah ditebak.</li><li>Jangan membagikan password atau kode reset kepada orang lain.</li><li>Keluar dari akun setelah memakai perangkat bersama.</li></ul></article>
        <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5"><h3 class="font-bold text-sky-950">Personalisasi penggunaan</h3><ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-sky-900"><li>Atur foto dan posisi foto melalui halaman profil.</li><li>Gunakan tombol tema di sebelah notifikasi untuk mode terang atau gelap.</li><li>Pastikan email aktif agar pemulihan password mandiri dapat digunakan.</li></ul></article>
    </div>
</section>

<section id="faq" aria-labelledby="faq-heading" class="scroll-mt-24">
    <div class="mb-4"><p class="text-xs font-bold uppercase tracking-wider text-amber-600">Bantuan singkat</p><h2 id="faq-heading" class="mt-1 text-xl font-bold text-slate-900">Pertanyaan umum</h2></div>
    <div class="space-y-3">
        <details class="guide-faq rounded-2xl border border-slate-200 bg-white p-4"><summary class="cursor-pointer font-semibold text-slate-900">Saya lupa password, apa yang harus dilakukan?</summary><p class="mt-3 text-sm leading-6 text-slate-600">Gunakan “Lupa password?” pada halaman login jika email akun masih aktif. Jika email tidak dapat diakses, hubungi admin sekolah untuk reset langsung.</p></details>
        <details class="guide-faq rounded-2xl border border-slate-200 bg-white p-4"><summary class="cursor-pointer font-semibold text-slate-900">Mengapa sebuah fitur tidak terlihat?</summary><p class="mt-3 text-sm leading-6 text-slate-600">Menu mengikuti role akun. Admin, guru, dan siswa memiliki hak akses yang berbeda. Pastikan juga kamu sedang membuka kelas atau halaman yang sesuai.</p></details>
        <details class="guide-faq rounded-2xl border border-slate-200 bg-white p-4"><summary class="cursor-pointer font-semibold text-slate-900">Apa yang dilakukan jika halaman tidak memperbarui data?</summary><p class="mt-3 text-sm leading-6 text-slate-600">Muat ulang halaman dan periksa koneksi. Hindari mengirim form yang sama berulang kali. Jika masalah tetap terjadi, catat halaman dan pesan error lalu sampaikan kepada admin.</p></details>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/guide-navigation.js') }}?v={{ filemtime(public_path('js/guide-navigation.js')) }}"></script>
@endpush
