@extends('layouts.app')
@section('title', 'Beranda')

@php
    $hour = now()->hour;
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
    $firstName = explode(' ', trim(auth()->user()->name))[0];
    $statIcons = [
        '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
        '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/>',
        '<path d="m5 12 4 4L19 6"/>',
        '<path d="M3 3v18h18"/><path d="m7 16 4-5 4 3 5-7"/>',
    ];
@endphp

@section('content')
<section class="lms-hero">
    <div class="lms-hero-content">
        <span class="lms-hero-kicker">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="4"/></svg>
            {{ now()->translatedFormat('l, d F Y') }}
        </span>
        <h1>{{ $greeting }}, {{ $firstName }}!</h1>
        <p>
            @if(auth()->user()->isSiswa())
                Siap belajar hari ini? Pantau tugas, kuis, dan materi terbaru dari semua kelasmu dalam satu tempat.
            @else
                Semua aktivitas kelas ada di sini. Bagikan materi, pantau pengumpulan, dan bantu siswa terus berkembang.
            @endif
        </p>
        <div class="lms-hero-actions">
            <a href="#kelas" class="lms-button lms-button-light">Lihat kelas saya</a>
            @if(auth()->user()->isSiswa())
                <a href="#gabung-kelas" class="lms-button lms-button-ghost-light">Gabung kelas</a>
            @else
                <a href="#buat-kelas" class="lms-button lms-button-ghost-light">Buat kelas baru</a>
            @endif
        </div>
    </div>
</section>

<section class="lms-stats" id="ringkasan" aria-label="Ringkasan aktivitas">
    @foreach($dashboardStats as $index => $stat)
        <div class="lms-stat">
            <span class="lms-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.9">{!! $statIcons[$index] !!}</svg></span>
            <span><strong>{{ $stat['value'] }}</strong><span>{{ $stat['label'] }}</span></span>
        </div>
    @endforeach
</section>

@if(auth()->user()->isGuru() || auth()->user()->isAdmin())
    <details class="lms-create-panel" id="buat-kelas" @if($errors->hasAny(['name', 'section', 'subject', 'room', 'color', 'description'])) open @endif>
        <summary>＋ Buat ruang kelas baru</summary>
        <form method="POST" action="{{ route('classrooms.store') }}" class="lms-form-grid">
            @csrf
            <div class="lms-field lms-span-2"><label>Nama kelas *</label><input type="text" name="name" value="{{ old('name') }}" required placeholder="Contoh: Matematika Wajib"></div>
            <div class="lms-field"><label>Rombel / bagian</label><input type="text" name="section" value="{{ old('section') }}" placeholder="Contoh: X-A"></div>
            <div class="lms-field"><label>Mata pelajaran</label><input type="text" name="subject" value="{{ old('subject') }}" placeholder="Contoh: Matematika"></div>
            <div class="lms-field"><label>Ruangan</label><input type="text" name="room" value="{{ old('room') }}" placeholder="Contoh: R-12"></div>
            <div class="lms-field"><label>Warna kelas</label><select name="color">@foreach(\App\Models\Classroom::COLORS as $color)<option value="{{ $color }}" @selected(old('color') === $color)>{{ ucfirst($color) }}</option>@endforeach</select></div>
            <div class="lms-field lms-span-2"><label>Deskripsi</label><textarea name="description" rows="3" placeholder="Tujuan belajar atau informasi singkat kelas">{{ old('description') }}</textarea></div>
            <div class="lms-span-2"><button class="lms-button lms-button-primary">Buat kelas</button></div>
        </form>
    </details>
@endif

<div class="lms-dashboard-grid">
    <section id="kelas">
        <div class="lms-section-head"><h2>Kelas Saya</h2><span>{{ $classrooms->count() }} kelas aktif</span></div>

        @if($classrooms->isEmpty())
            <div class="lms-empty-state">
                <div class="lms-empty-icon"><svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-6 9 6-9 6-9-6Z"/><path d="M7 13.5V18l5 3 5-3v-4.5"/></svg></div>
                <h3>Belum ada kelas</h3>
                <p>@if(auth()->user()->isSiswa()) Minta kode kelas kepada guru, lalu masukkan kode tersebut pada panel Gabung Kelas. @else Buat kelas pertama untuk mulai membagikan materi dan tugas. @endif</p>
            </div>
        @else
            <div class="lms-class-grid">
                @foreach($classrooms as $classroom)
                    <a href="{{ route('classrooms.show', $classroom) }}" class="lms-class-card" data-class-card data-color="{{ $classroom->color }}">
                        <div class="lms-class-cover">
                            <span class="lms-class-label">{{ $classroom->subject ?: 'Ruang belajar' }}</span>
                            <h3>{{ $classroom->name }}</h3>
                            <p>{{ $classroom->section ?: 'Kelas umum' }}{{ $classroom->room ? ' · Ruang '.$classroom->room : '' }}</p>
                        </div>
                        <div class="lms-class-body">
                            <div class="lms-class-teacher"><span class="lms-mini-avatar">{{ strtoupper(mb_substr($classroom->teacher->name, 0, 1)) }}</span><span>{{ $classroom->teacher->name }}</span></div>
                            <div class="lms-class-meta">
                                <span><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="7" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 11a4 4 0 0 1 4 4v2"/></svg>{{ $classroom->students_count }} siswa</span>
                                <span><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5"/></svg>{{ $classroom->materials_count }} materi</span>
                                <span><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>{{ $classroom->assignments_count }} tugas</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="lms-no-results" data-no-results>Tidak ada kelas yang cocok dengan pencarianmu.</div>
        @endif
    </section>

    <aside class="lms-side-stack">
        <section class="lms-panel" id="agenda">
            <div class="lms-panel-title"><h3>Agenda Terdekat</h3><span>{{ $upcomingAssignments->count() }} agenda</span></div>
            <div class="lms-agenda">
                @forelse($upcomingAssignments as $assignment)
                    <a href="{{ route('assignments.show', $assignment) }}" class="lms-agenda-item">
                        <span class="lms-agenda-date"><strong>{{ $assignment->due_at->format('d') }}</strong><span>{{ $assignment->due_at->translatedFormat('M') }}</span></span>
                        <span class="lms-agenda-copy"><strong>{{ $assignment->title }}</strong><span>{{ $assignment->classroom->name }} · {{ $assignment->due_at->format('H:i') }}</span></span>
                    </a>
                @empty
                    <div class="lms-empty-mini">Belum ada tenggat dalam waktu dekat. Nikmati waktu luangmu!</div>
                @endforelse
            </div>
        </section>

        @if(auth()->user()->isSiswa())
            <section class="lms-panel" id="gabung-kelas">
                <div class="lms-panel-title"><h3>Gabung Kelas</h3><span>Dengan kode</span></div>
                <form method="POST" action="{{ route('classrooms.join') }}" class="lms-quick-form">
                    @csrf
                    <input type="text" name="code" value="{{ old('code') }}" placeholder="Masukkan kode kelas" maxlength="10" required aria-label="Kode kelas">
                    <button class="lms-button lms-button-primary">Gabung sekarang</button>
                </form>
            </section>
        @else
            <section class="lms-panel">
                <div class="lms-panel-title"><h3>Tips Hari Ini</h3><span>Untuk pengajar</span></div>
                <p class="text-xs leading-6 text-slate-500 m-0">Berikan umpan balik singkat dan spesifik agar siswa tahu satu hal yang sudah baik dan satu hal yang perlu diperbaiki.</p>
            </section>
        @endif
    </aside>
</div>
@endsection
