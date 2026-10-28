{{-- Banner kelas + tab navigasi. Butuh: $classroom, $tab --}}
@php
    $tabs = [
        'stream' => ['Stream', route('classrooms.show', $classroom)],
        'classwork' => ['Tugas & Materi', route('classrooms.classwork', $classroom)],
        'people' => ['Anggota', route('classrooms.people', $classroom)],
        'grades' => ['Nilai', route('classrooms.grades', $classroom)],
    ];
    $canManage = $classroom->canManage(auth()->user());
@endphp

<div class="{{ $classroom->bannerClass() }} text-white rounded-xl p-6 mb-1">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $classroom->name }}</h1>
            <p class="opacity-90">
                {{ $classroom->section }}{{ $classroom->subject ? ' · '.$classroom->subject : '' }}{{ $classroom->room ? ' · Ruang '.$classroom->room : '' }}
            </p>
            <p class="text-sm opacity-80 mt-1">Guru: {{ $classroom->teacher->name }}</p>
        </div>

        @if($canManage)
            <div class="bg-white/15 rounded-lg px-4 py-2 text-center">
                <div class="text-xs uppercase tracking-wide opacity-80">Kode kelas</div>
                <div class="text-2xl font-mono font-bold tracking-widest">{{ $classroom->code }}</div>
            </div>
        @endif
    </div>
</div>

<div class="flex items-center justify-between border-b border-slate-200 mb-6">
    <div class="flex gap-6 text-sm">
        @foreach($tabs as $key => [$label, $url])
            <a href="{{ $url }}"
               class="py-3 border-b-2 {{ $tab === $key ? 'border-indigo-600 text-indigo-700 font-medium' : 'border-transparent text-slate-600 hover:text-slate-900' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="flex items-center gap-4 text-sm">
        @if(auth()->user()->isSiswa())
            <form method="POST" action="{{ route('classrooms.leave', $classroom) }}" onsubmit="return confirm('Keluar dari kelas ini?')">
                @csrf
                <button class="text-slate-500 hover:text-red-600">Keluar kelas</button>
            </form>
        @endif
        @if($canManage)
            <form method="POST" action="{{ route('classrooms.destroy', $classroom) }}"
                  onsubmit="return confirm('Hapus kelas beserta semua tugas, materi, dan kuis di dalamnya?')">
                @csrf @method('DELETE')
                <button class="text-slate-500 hover:text-red-600">Hapus kelas</button>
            </form>
        @endif
    </div>
</div>
