<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17213f">
    <title>@yield('title', 'Beranda') | RuangKelas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/lms-ui.css') }}?v={{ filemtime(public_path('css/lms-ui.css')) }}">
</head>
<body class="lms-body">
@auth
    <div class="lms-shell">
        <div class="lms-overlay" data-sidebar-overlay></div>

        <aside class="lms-sidebar" id="lms-sidebar" data-sidebar aria-label="Navigasi utama">
            <a href="{{ route('dashboard') }}" class="lms-brand">
                <span class="lms-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 10 9-6 9 6-9 6-9-6Z"/><path d="M7 13.5V18l5 3 5-3v-4.5"/><path d="M21 10v6"/></svg>
                </span>
                <span><span class="lms-brand-title">RuangKelas</span><span class="lms-brand-subtitle">SMK NEGERI 1 CERME</span></span>
            </a>

            <div class="lms-nav-label">Menu Utama</div>
            <nav class="lms-nav">
                <a href="{{ route('dashboard') }}" class="lms-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg><span>Beranda</span>
                </a>
                <a href="{{ route('dashboard') }}#kelas" class="lms-nav-link {{ request()->routeIs('classrooms.*', 'assignments.*', 'quizzes.*', 'attempts.*') ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg><span>Kelas Saya</span>
                </a>
                @if(auth()->user()->isGuru())
                    <a href="{{ route('question-banks.index') }}" class="lms-nav-link {{ request()->routeIs('question-banks.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg><span>Bank Soal</span>
                    </a>
                    <a href="{{ route('transcripts.index') }}" class="lms-nav-link {{ request()->routeIs('transcripts.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 3h16v18H4z"/><path d="M8 7h8M8 11h8M8 15h3"/><path d="m14 16 2 2 4-4"/></svg><span>Transkrip</span>
                    </a>
                @endif
                @if(auth()->user()->isSiswa())
                    <a href="{{ route('student.transcripts.index') }}" class="lms-nav-link {{ request()->routeIs('student.transcripts.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 3h16v18H4z"/><path d="M8 7h8M8 11h8M8 15h3"/><path d="m14 16 2 2 4-4"/></svg><span>Transkrip Saya</span>
                    </a>
                @endif
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.users.index') }}" class="lms-nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Kelola Pengguna</span>
                    </a>
                @endif
            </nav>

            <div class="lms-nav-label">Akademik</div>
            <nav class="lms-nav">
                <a href="{{ route('dashboard') }}#agenda" class="lms-nav-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><span>Agenda</span></a>
                <a href="{{ route('dashboard') }}#ringkasan" class="lms-nav-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3v18h18"/><path d="m7 16 4-5 4 3 5-7"/></svg><span>Ringkasan</span></a>
            </nav>

            <div class="lms-semester-card"><small>Tahun Ajaran</small><strong>Semester Ganjil</strong><span>2026 / 2027</span></div>
        </aside>

        <div class="lms-app">
            <header class="lms-topbar">
                <button type="button" class="lms-mobile-menu" data-sidebar-toggle aria-label="Buka menu" aria-controls="lms-sidebar" aria-expanded="false"><svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>

                <div class="lms-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" placeholder="Cari kelas, mata pelajaran, atau guru..." aria-label="Cari kelas" @if(request()->routeIs('dashboard')) data-class-search @endif>
                </div>

                <div class="lms-top-actions">
                    <a href="{{ route('dashboard') }}#agenda" class="lms-icon-button" aria-label="Lihat agenda" title="Agenda terbaru"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg><span class="lms-notification-dot"></span></a>
                    <details class="lms-user-menu">
                        <summary aria-label="Menu akun">
                            <span class="lms-avatar">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                            <span class="lms-user-copy"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->roleLabel() }}</span></span>
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="lms-user-popover">
                            <a href="{{ route('dashboard') }}">Beranda saya</a>
                            @if(auth()->user()->isGuru())<a href="{{ route('question-banks.index') }}">Bank soal</a><a href="{{ route('transcripts.index') }}">Transkrip nilai</a>@endif
                            @if(auth()->user()->isSiswa())<a href="{{ route('student.transcripts.index') }}">Transkrip saya</a>@endif
                            @if(auth()->user()->isAdmin())<a href="{{ route('admin.users.index') }}">Kelola pengguna</a>@endif
                            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Keluar dari akun</button></form>
                        </div>
                    </details>
                </div>
            </header>

            <main class="lms-content">
                @if(session('success'))
                    <div class="lms-alert lms-alert-success" role="status"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 12 2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg><span>{{ session('success') }}</span></div>
                @endif
                @if($errors->any())
                    <div class="lms-alert lms-alert-error" role="alert"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg><ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
@else
    @if($errors->any())
        <div class="fixed top-5 left-1/2 -translate-x-1/2 z-50 w-[calc(100%-2rem)] max-w-md lms-alert lms-alert-error" role="alert"><span>{{ $errors->first() }}</span></div>
    @endif
    @yield('content')
@endauth

<script src="{{ asset('js/lms-ui.js') }}"></script>
@stack('scripts')
</body>
</html>
