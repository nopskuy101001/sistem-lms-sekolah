@extends('layouts.app')
@section('title', 'Tugas & Materi - '.$classroom->name)

@section('content')
@include('classrooms._header-modern', ['tab' => 'classwork'])

@if($isManager)
<div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    {{-- Tambah materi --}}
    <details class="bg-white border border-slate-200 rounded-xl">
        <summary class="cursor-pointer px-4 py-3 font-medium text-indigo-700">+ Materi</summary>
        <form method="POST" action="{{ route('materials.store', $classroom) }}" enctype="multipart/form-data" class="p-4 space-y-3 border-t border-slate-200">
            @csrf
            <input type="text" name="title" required placeholder="Judul materi" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <textarea name="description" rows="2" placeholder="Deskripsi (opsional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            <input type="url" name="link" placeholder="Tautan (opsional), mis. https://youtube.com/..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="file" name="file" class="w-full text-sm">
            <p class="text-xs text-slate-500">Maks. 10 MB.</p>
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium">Simpan materi</button>
        </form>
    </details>

    {{-- Tambah tugas --}}
    <details class="bg-white border border-slate-200 rounded-xl">
        <summary class="cursor-pointer px-4 py-3 font-medium text-indigo-700">+ Tugas</summary>
        <form method="POST" action="{{ route('assignments.store', $classroom) }}" enctype="multipart/form-data" class="p-4 space-y-3 border-t border-slate-200">
            @csrf
            <input type="text" name="title" required placeholder="Judul tugas" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <textarea name="instructions" rows="3" placeholder="Petunjuk pengerjaan" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Tenggat</label>
                    <input type="datetime-local" name="due_at" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Poin maks.</label>
                    <input type="number" name="points" value="100" min="1" max="1000" required class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                </div>
            </div>
            <input type="file" name="file" class="w-full text-sm">
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium">Buat tugas</button>
        </form>
    </details>

    {{-- Tambah kuis --}}
    <details class="bg-white border border-slate-200 rounded-xl">
        <summary class="cursor-pointer px-4 py-3 font-medium text-indigo-700">+ Kuis / Ujian</summary>
        <form method="POST" action="{{ route('quizzes.store', $classroom) }}" class="p-4 space-y-3 border-t border-slate-200">
            @csrf
            <input type="text" name="title" required placeholder="Judul kuis" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <textarea name="description" rows="2" placeholder="Petunjuk (opsional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Durasi (menit, kosongkan = tanpa batas)</label>
                <input type="number" name="duration_minutes" min="1" max="600" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Dibuka</label>
                    <input type="datetime-local" name="start_at" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Ditutup</label>
                    <input type="datetime-local" name="end_at" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                </div>
            </div>
            <div class="rounded-lg bg-indigo-50 px-3 py-2 text-[11px] leading-5 text-indigo-700">
                Pemantauan fokus, autosave, dan pengacakan urutan soal aktif secara default. Pengaturannya dapat diubah setelah kuis dibuat.
            </div>
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium">Buat kuis</button>
        </form>
    </details>
    {{-- Video Interaktif --}}
    <details class="bg-white border border-slate-200 rounded-xl">
        <summary class="cursor-pointer px-4 py-3 font-medium text-indigo-700">+ Video Interaktif</summary>
        <form method="POST" action="{{ route('video-lessons.store', $classroom) }}" enctype="multipart/form-data" class="p-4 space-y-3 border-t border-slate-200">
            @csrf
            <input type="text" name="title" required maxlength="180" placeholder="Judul video" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <textarea name="description" rows="2" placeholder="Deskripsi (opsional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
            <label class="block text-xs text-slate-600">Jenis video</label>
            <select name="source_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="youtube">YouTube</option><option value="mp4">Video MP4</option>
            </select>
            <input type="text" name="source_url" placeholder="URL YouTube / link langsung .mp4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <label class="block text-xs text-slate-600">Atau unggah MP4 (pilih jenis MP4)</label>
            <input type="file" name="video_file" accept="video/mp4,.mp4" class="w-full text-xs">
            <p class="text-[11px] text-slate-500">Unggahan maks. 100 MB (tergantung php.ini). Soal ditambahkan setelah video dibuat.</p>
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium">Buat Video Interaktif</button>
        </form>
    </details>
</div>
@endif

{{-- ===== Video Interaktif ===== --}}
<h2 class="text-lg font-semibold mb-3">Video Interaktif</h2>
<div class="space-y-2 mb-8">
    @forelse($videoLessons as $videoLesson)
        @php $videoProgress = $videoAttempts->get($videoLesson->id); @endphp
        <div class="flex flex-col items-start justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
            <a href="{{ $isManager ? route('video-lessons.edit', $videoLesson) : route('video-lessons.show', $videoLesson) }}" class="w-full min-w-0 flex-1 hover:text-indigo-700 sm:w-auto">
                <div class="font-semibold">🎬 {{ $videoLesson->title }}</div>
                <div class="text-xs text-slate-500 mt-1">{{ $videoLesson->checkpoints_count }} checkpoint · {{ strtoupper($videoLesson->source_type) }} · Nilai maks. 100</div>
            </a>
            @if($isManager)
                <span class="rounded-full px-2 py-1 text-xs {{ $videoLesson->is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $videoLesson->is_published ? 'Terbit' : 'Draf' }}</span>
            @elseif($videoProgress?->completed_at)
                <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Selesai · {{ number_format((float) $videoProgress->score, 0) }}/100</span>
            @elseif($videoProgress)
                <span class="rounded-full bg-amber-50 px-2 py-1 text-xs text-amber-700">Dalam proses</span>
            @else
                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">Belum dimulai</span>
            @endif
        </div>
    @empty
        <p class="text-sm text-slate-500">Belum ada video interaktif di kelas ini.</p>
    @endforelse
</div>

{{-- ===== Tugas ===== --}}
<h2 class="text-lg font-semibold mb-3">Tugas</h2>
<div class="space-y-2 mb-8">
    @forelse($assignments as $assignment)
        @php $submission = $submissions->get($assignment->id); @endphp
        <a href="{{ route('assignments.show', $assignment) }}"
           class="flex flex-col items-start justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 hover:shadow-sm sm:flex-row sm:items-center">
            <div class="w-full min-w-0 sm:w-auto">
                <div class="font-medium">{{ $assignment->title }}</div>
                <div class="text-xs text-slate-500">
                    {{ $assignment->due_at ? 'Tenggat '.$assignment->due_at->translatedFormat('d M Y, H:i') : 'Tanpa tenggat' }}
                    · {{ $assignment->points }} poin
                </div>
            </div>
            @unless($isManager)
                @if($submission && $submission->score !== null)
                    <span class="text-sm text-emerald-700 font-medium">{{ rtrim(rtrim((string) $submission->score, '0'), '.') }}/{{ $assignment->points }}</span>
                @elseif($submission)
                    <span class="text-xs px-2 py-1 rounded-full bg-sky-50 text-sky-700">Dikumpulkan</span>
                @elseif($assignment->isOverdue())
                    <span class="text-xs px-2 py-1 rounded-full bg-red-50 text-red-700">Terlambat</span>
                @else
                    <span class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700">Belum dikumpulkan</span>
                @endif
            @endunless
        </a>
    @empty
        <p class="text-sm text-slate-500">Belum ada tugas.</p>
    @endforelse
</div>

{{-- ===== Kuis ===== --}}
<h2 class="text-lg font-semibold mb-3">Kuis & Ujian</h2>
<div class="space-y-2 mb-8">
    @forelse($quizzes as $quiz)
        @php $attempt = $attempts->get($quiz->id); @endphp
        <a href="{{ route('quizzes.show', $quiz) }}"
           class="flex flex-col items-start justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 hover:shadow-sm sm:flex-row sm:items-center">
            <div class="w-full min-w-0 sm:w-auto">
                <div class="font-medium">{{ $quiz->title }}</div>
                <div class="text-xs text-slate-500">
                    {{ $quiz->questions_count }} soal
                    · {{ $quiz->duration_minutes ? $quiz->duration_minutes.' menit' : 'tanpa batas waktu' }}
                </div>
            </div>
            @if($isManager)
                <span class="text-xs px-2 py-1 rounded-full {{ $quiz->is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ $quiz->statusLabel() }}
                </span>
            @elseif($attempt && $attempt->isFinished())
                <span class="text-xs px-2 py-1 rounded-full bg-sky-50 text-sky-700">Selesai</span>
            @else
                <span class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700">{{ $quiz->statusLabel() }}</span>
            @endif
        </a>
    @empty
        <p class="text-sm text-slate-500">Belum ada kuis.</p>
    @endforelse
</div>

{{-- ===== Materi ===== --}}
<h2 class="text-lg font-semibold mb-3">Materi</h2>
<div class="space-y-2">
    @forelse($materials as $material)
        <div class="bg-white rounded-xl border border-slate-200 px-4 py-3">
            <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-start sm:gap-4">
                <div class="w-full min-w-0 sm:w-auto">
                    <div class="font-medium">{{ $material->title }}</div>
                    <div class="mt-1 flex items-center gap-1.5 text-[11px] text-slate-400">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <time datetime="{{ $material->created_at->toIso8601String() }}" title="{{ $material->created_at->diffForHumans() }}">
                            Diunggah {{ $material->created_at->translatedFormat('d F Y, H:i') }}
                        </time>
                    </div>
                    @if($material->description)
                        <p class="text-sm text-slate-600 whitespace-pre-line mt-1">{{ $material->description }}</p>
                    @endif
                    <div class="mt-2 flex flex-wrap gap-3 text-sm">
                        @if($material->file_path)
                            <a href="{{ route('materials.download', $material) }}" class="text-indigo-700 hover:underline">Unduh: {{ $material->file_name }}</a>
                        @endif
                        @if($material->link)
                            <a href="{{ $material->link }}" target="_blank" rel="noopener noreferrer" class="text-indigo-700 hover:underline">Buka tautan</a>
                        @endif
                    </div>
                </div>
                @if($isManager)
                    <form method="POST" action="{{ route('materials.destroy', $material) }}" onsubmit="return confirm('Hapus materi ini?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-slate-400 hover:text-red-600">Hapus</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-slate-500">Belum ada materi.</p>
    @endforelse
</div>
@endsection
