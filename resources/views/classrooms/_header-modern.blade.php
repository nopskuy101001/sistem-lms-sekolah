@php
    $tabs = [
        'stream' => ['Beranda Kelas', route('classrooms.show', $classroom)],
        'classwork' => ['Tugas & Materi', route('classrooms.classwork', $classroom)],
        'people' => ['Anggota', route('classrooms.people', $classroom)],
        'grades' => ['Nilai', route('classrooms.grades', $classroom)],
    ];
    $canManage = $classroom->canManage(auth()->user());
@endphp

<section class="lms-class-hero {{ $classroom->bannerClass() }} {{ $classroom->theme_image_path ? 'lms-has-theme-image' : '' }}">
    @if($classroom->theme_image_path)<img class="lms-theme-image" src="{{ asset('storage/'.$classroom->theme_image_path) }}" alt="" style="{{ $classroom->themeImageStyle() }}">@endif
    <div class="lms-class-hero-content">
        <div>
            <span class="lms-hero-kicker">{{ $classroom->subject ?: 'Ruang belajar' }}</span>
            <h1>{{ $classroom->name }}</h1>
            <p>{{ $classroom->section ?: 'Kelas umum' }}{{ $classroom->room ? ' · Ruang '.$classroom->room : '' }} · {{ $classroom->teacher->name }}</p>
        </div>
        @if($canManage)
            <div class="lms-class-code-panel">
                <button type="button" class="lms-class-code" data-copy-code="{{ $classroom->code }}" title="Klik untuk menyalin kode kelas">
                    <span>Kode kelas · klik untuk salin</span>
                    <strong>{{ $classroom->code }}</strong>
                </button>
                <form method="POST" action="{{ route('classrooms.code.refresh', $classroom) }}" onsubmit="return confirm('Buat kode kelas baru? Kode lama akan langsung tidak berlaku, tetapi siswa yang sudah bergabung tidak akan dikeluarkan.')">
                    @csrf
                    <button type="submit" class="lms-code-refresh" title="Buat kode kelas baru">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 11a8 8 0 1 0-2.34 5.66"/><path d="M20 4v7h-7"/></svg>
                        Refresh kode
                    </button>
                </form>
            </div>
        @endif
    </div>
</section>

<div class="lms-class-tabs">
    <nav class="lms-tabs-scroll" aria-label="Navigasi kelas">
        @foreach($tabs as $key => [$label, $url])
            <a href="{{ $url }}" class="lms-tab {{ $tab === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="lms-class-actions">
        @if(auth()->user()->isSiswa())
            <form method="POST" action="{{ route('classrooms.leave', $classroom) }}" onsubmit="return confirm('Keluar dari kelas ini?')">
                @csrf
                <button class="lms-text-action">Keluar kelas</button>
            </form>
        @endif
        @if($canManage)
            <form method="POST" action="{{ route('classrooms.destroy', $classroom) }}" onsubmit="return confirm('Hapus kelas beserta semua tugas, materi, dan kuis di dalamnya?')">
                @csrf @method('DELETE')
                <button class="lms-text-action">Hapus kelas</button>
            </form>
        @endif
    </div>
</div>
