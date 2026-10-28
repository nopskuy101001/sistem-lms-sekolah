@extends('layouts.app')
@section('title', $classroom->name)

@section('content')
@include('classrooms._header-modern', ['tab' => 'stream'])

<div class="lms-stream-grid">
    <aside class="lms-side-stack">
        @if(auth()->user()->isSiswa())
            <section class="lms-panel">
                <div class="lms-panel-title"><h3>Tugas Mendatang</h3><span>{{ $upcoming->count() }}</span></div>
                <div class="lms-agenda">
                    @forelse($upcoming as $assignment)
                        <a href="{{ route('assignments.show', $assignment) }}" class="lms-agenda-item">
                            <span class="lms-agenda-date">
                                @if($assignment->due_at)
                                    <strong>{{ $assignment->due_at->format('d') }}</strong><span>{{ $assignment->due_at->translatedFormat('M') }}</span>
                                @else
                                    <strong>∞</strong><span>Bebas</span>
                                @endif
                            </span>
                            <span class="lms-agenda-copy"><strong>{{ $assignment->title }}</strong><span>{{ $assignment->due_at ? $assignment->due_at->format('H:i') : 'Tanpa tenggat' }}</span></span>
                        </a>
                    @empty
                        <div class="lms-empty-mini">Semua tugas sudah aman. Kerja bagus!</div>
                    @endforelse
                </div>
            </section>
        @else
            <section class="lms-panel">
                <div class="lms-panel-title"><h3>Aksi Cepat</h3><span>Kelola kelas</span></div>
                <div class="grid gap-2">
                    <a href="{{ route('classrooms.classwork', $classroom) }}" class="lms-button lms-button-primary">Tambah materi atau tugas</a>
                    <a href="{{ route('classrooms.people', $classroom) }}" class="lms-button bg-slate-100 text-slate-600">Lihat anggota kelas</a>
                </div>
            </section>
            <details class="lms-panel" @if($errors->hasAny(['name', 'section', 'subject', 'room', 'description', 'color', 'theme_image', 'theme_zoom', 'theme_position_x', 'theme_position_y'])) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-slate-800">Ubah tema dan detail kelas</summary>
                <form method="POST" action="{{ route('classrooms.update', $classroom) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 text-xs">
                    @csrf @method('PUT')
                    <label class="grid gap-1 font-medium">Nama kelas *<input name="name" value="{{ old('name', $classroom->name) }}" required maxlength="150" class="w-full rounded-lg border border-slate-300 p-2"></label>
                    <label class="grid gap-1 font-medium">Rombel / bagian<input name="section" value="{{ old('section', $classroom->section) }}" maxlength="50" class="w-full rounded-lg border border-slate-300 p-2"></label>
                    <label class="grid gap-1 font-medium">Mata pelajaran<input name="subject" value="{{ old('subject', $classroom->subject) }}" maxlength="100" class="w-full rounded-lg border border-slate-300 p-2"></label>
                    <label class="grid gap-1 font-medium">Ruangan<input name="room" value="{{ old('room', $classroom->room) }}" maxlength="50" class="w-full rounded-lg border border-slate-300 p-2"></label>
                    <label class="grid gap-1 font-medium">Warna dasar<select name="color" class="w-full rounded-lg border border-slate-300 p-2">@foreach(\App\Models\Classroom::COLORS as $color)<option value="{{ $color }}" @selected(old('color', $classroom->color) === $color)>{{ ucfirst($color) }}</option>@endforeach</select></label>
                    @include('classrooms._theme-editor', ['themeClassroom' => $classroom])
                    @if($classroom->theme_image_path)<label class="flex items-center gap-2 font-normal"><input type="checkbox" name="remove_theme_image" value="1"> Hapus gambar dan gunakan warna dasar</label>@endif
                    <label class="grid gap-1 font-medium">Deskripsi<textarea name="description" rows="3" class="w-full rounded-lg border border-slate-300 p-2">{{ old('description', $classroom->description) }}</textarea></label>
                    @if($errors->any())<p class="text-red-600">{{ $errors->first() }}</p>@endif
                    <button class="lms-button lms-button-primary">Simpan perubahan kelas</button>
                </form>
            </details>
        @endif

        @if($classroom->description)
            <section class="lms-panel">
                <div class="lms-panel-title"><h3>Tentang Kelas</h3></div>
                <p class="text-xs leading-6 text-slate-500 m-0">{{ $classroom->description }}</p>
            </section>
        @endif
    </aside>

    <section class="space-y-4">
        @if($classroom->canManage(auth()->user()))
            <form method="POST" action="{{ route('announcements.store', $classroom) }}" class="lms-announcement-form">
                @csrf
                <span class="lms-avatar">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                <div class="flex-1">
                    <textarea name="body" rows="3" required placeholder="Bagikan informasi, pengingat, atau semangat untuk kelas..."></textarea>
                    <div class="mt-2 flex justify-between items-center gap-3">
                        <span class="text-[10px] text-slate-400">Pengumuman akan terlihat oleh seluruh siswa.</span>
                        <button class="lms-button lms-button-primary">Kirim pengumuman</button>
                    </div>
                </div>
            </form>
        @endif

        @forelse($announcements as $announcement)
            <article class="lms-feed-card">
                <div class="lms-feed-head">
                    <div class="lms-feed-user">
                        <span class="lms-mini-avatar">{{ strtoupper(mb_substr($announcement->user->name, 0, 1)) }}</span>
                        <span><strong>{{ $announcement->user->name }}</strong><time>{{ $announcement->created_at->diffForHumans() }} · {{ $announcement->created_at->translatedFormat('d M Y, H:i') }}</time></span>
                    </div>
                    @if($classroom->canManage(auth()->user()))
                        <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" onsubmit="return confirm('Hapus pengumuman ini?')">
                            @csrf @method('DELETE')
                            <button class="lms-text-action">Hapus</button>
                        </form>
                    @endif
                </div>
                <p class="lms-feed-body whitespace-pre-line">{{ $announcement->body }}</p>
            </article>
        @empty
            <div class="lms-empty-state">
                <div class="lms-empty-icon"><svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/></svg></div>
                <h3>Belum ada pengumuman</h3>
                <p>Informasi terbaru dari guru akan muncul di halaman ini.</p>
            </div>
        @endforelse
    </section>
</div>
@endsection
