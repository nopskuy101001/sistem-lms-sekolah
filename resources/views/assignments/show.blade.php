@extends('layouts.app')
@section('title', $assignment->title)

@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
@endphp

@section('content')
<div class="mb-4 text-sm">
    <a href="{{ route('classrooms.classwork', $classroom) }}" class="text-indigo-700 hover:underline">← {{ $classroom->name }}</a>
</div>

<div class="grid md:grid-cols-3 gap-6">
    {{-- Detail tugas --}}
    <section class="md:col-span-2 bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <h1 class="text-2xl font-semibold">{{ $assignment->title }}</h1>
            @if($isManager)
                <form method="POST" action="{{ route('assignments.destroy', $assignment) }}"
                      onsubmit="return confirm('Hapus tugas beserta semua pengumpulan siswa?')">
                    @csrf @method('DELETE')
                    <button class="text-sm text-slate-400 hover:text-red-600">Hapus</button>
                </form>
            @endif
        </div>

        <div class="text-sm text-slate-500 mt-1">
            {{ $assignment->points }} poin ·
            {{ $assignment->due_at ? 'Tenggat '.$assignment->due_at->translatedFormat('d M Y, H:i') : 'Tanpa tenggat' }}
        </div>

        @if($assignment->instructions)
            <p class="mt-4 whitespace-pre-line text-sm">{{ $assignment->instructions }}</p>
        @endif

        @if($assignment->file_path)
            <a href="{{ route('assignments.download', $assignment) }}" class="inline-block mt-4 text-sm text-indigo-700 hover:underline">
                Unduh lampiran: {{ $assignment->file_name }}
            </a>
        @endif
    </section>

    {{-- Panel kanan --}}
    <aside>
        @if(! $isManager)
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="font-medium mb-3">Tugas kamu</h3>

                @if($submission && $submission->score !== null)
                    <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm">
                        <div class="font-medium text-emerald-800">Nilai: {{ $fmt($submission->score) }} / {{ $assignment->points }}</div>
                        @if($submission->feedback)
                            <p class="mt-1 text-emerald-900 whitespace-pre-line">{{ $submission->feedback }}</p>
                        @endif
                    </div>
                @endif

                @if($submission)
                    <div class="text-sm mb-3">
                        <div class="text-slate-500">
                            Dikumpulkan {{ $submission->submitted_at->translatedFormat('d M Y, H:i') }}
                            @if($submission->isLate()) <span class="text-red-600">(terlambat)</span> @endif
                        </div>
                        @if($submission->content)
                            <p class="mt-2 whitespace-pre-line bg-slate-50 rounded-lg p-2">{{ $submission->content }}</p>
                        @endif
                        @if($submission->file_path)
                            <a href="{{ route('submissions.download', $submission) }}" class="inline-block mt-2 text-indigo-700 hover:underline">{{ $submission->file_name }}</a>
                        @endif
                    </div>
                @endif

                @if(! $submission || $submission->score === null)
                    <form method="POST" action="{{ route('submissions.store', $assignment) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <textarea name="content" rows="4" placeholder="Tulis jawaban (opsional jika melampirkan file)"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('content', $submission->content ?? '') }}</textarea>
                        <input type="file" name="file" class="w-full text-sm">
                        <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-2 text-sm font-medium">
                            {{ $submission ? 'Kumpulkan ulang' : 'Kumpulkan' }}
                        </button>
                        @if($assignment->isOverdue())
                            <p class="text-xs text-red-600">Tenggat sudah lewat, pengumpulan akan ditandai terlambat.</p>
                        @endif
                    </form>
                @endif
            </div>
        @else
            <div class="bg-white rounded-xl border border-slate-200 p-5 text-sm">
                <h3 class="font-medium mb-3">Ringkasan</h3>
                <div class="flex justify-between"><span>Dikumpulkan</span><span class="font-medium">{{ $submissions->count() }} / {{ $students->count() }}</span></div>
                <div class="flex justify-between"><span>Sudah dinilai</span><span class="font-medium">{{ $submissions->whereNotNull('score')->count() }}</span></div>
            </div>
        @endif
    </aside>
</div>

{{-- Daftar pengumpulan (guru) --}}
@if($isManager)
    <h2 class="text-lg font-semibold mt-8 mb-3">Pengumpulan siswa</h2>
    <div class="space-y-3">
        @forelse($students as $student)
            @php $sub = $submissions->get($student->id); @endphp
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="font-medium">{{ $student->name }}</div>
                    @if($sub)
                        <div class="text-xs text-slate-500">
                            {{ $sub->submitted_at->translatedFormat('d M Y, H:i') }}
                            @if($sub->isLate()) <span class="text-red-600">· terlambat</span> @endif
                        </div>
                    @else
                        <span class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700">Belum mengumpulkan</span>
                    @endif
                </div>

                @if($sub)
                    @if($sub->content)
                        <p class="mt-2 text-sm whitespace-pre-line bg-slate-50 rounded-lg p-2">{{ $sub->content }}</p>
                    @endif
                    @if($sub->file_path)
                        <a href="{{ route('submissions.download', $sub) }}" class="inline-block mt-2 text-sm text-indigo-700 hover:underline">{{ $sub->file_name }}</a>
                    @endif

                    <form method="POST" action="{{ route('submissions.grade', $sub) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf @method('PUT')
                        <div>
                            <label class="block text-xs text-slate-500 mb-1">Nilai (maks. {{ $assignment->points }})</label>
                            <input type="number" step="0.01" min="0" max="{{ $assignment->points }}" name="score"
                                   value="{{ $sub->score !== null ? $fmt($sub->score) : '' }}" required
                                   class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                        <div class="min-w-0 basis-full sm:min-w-[200px] sm:flex-1 sm:basis-auto">
                            <label class="block text-xs text-slate-500 mb-1">Komentar</label>
                            <input type="text" name="feedback" value="{{ $sub->feedback }}" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                        <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-1.5 text-sm font-medium">Simpan</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-sm text-slate-500">Belum ada siswa di kelas ini.</p>
        @endforelse
    </div>
@endif
@endsection
