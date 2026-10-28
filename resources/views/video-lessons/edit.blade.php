@extends('layouts.app')
@section('title', 'Kelola Video Interaktif - '.$videoLesson->title)
@section('content')
<div class="mb-5 flex items-center justify-between gap-3 flex-wrap">
    <div>
        <a href="{{ route('classrooms.classwork', $videoLesson->classroom) }}" class="text-sm font-medium text-indigo-700 hover:underline">← Tugas & Materi · {{ $videoLesson->classroom->name }}</a>
        <h1 class="text-2xl font-bold mt-2">Kelola Video Interaktif</h1>
        <p class="text-sm text-slate-500 mt-1">Siapkan video dan checkpoint sebelum dipublikasikan untuk siswa.</p>
    </div>
    <a href="{{ route('video-lessons.show', $videoLesson) }}" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">▶ Pratinjau pemutar</a>
</div>
@if($errors->any())
<div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><strong>Periksa formulir:</strong><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="grid gap-5 lg:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
        <h2 class="text-lg font-semibold">Informasi Video</h2>
        <form method="POST" action="{{ route('video-lessons.update', $videoLesson) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf @method('PUT')
            <label class="block text-sm font-medium">Judul <input class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm" name="title" value="{{ old('title', $videoLesson->title) }}" maxlength="180" required></label>
            <label class="block text-sm font-medium">Deskripsi <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">{{ old('description', $videoLesson->description) }}</textarea></label>
            <label class="block text-sm font-medium">Sumber <select name="source_type" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm"><option value="youtube" @selected(old('source_type', $videoLesson->source_type)==='youtube')>YouTube</option><option value="mp4" @selected(old('source_type', $videoLesson->source_type)==='mp4')>MP4</option></select></label>
            <label class="block text-sm font-medium">URL sumber baru (kosongkan untuk mempertahankan) <input name="source_url" type="text" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm" placeholder="https://www.youtube.com/watch?v=... atau https://.../video.mp4"></label>
            @if($videoLesson->file_path)<p class="text-xs text-emerald-700">✓ Video MP4 tersimpan di storage LMS.</p>@else <p class="text-xs text-slate-500 break-all">Sumber saat ini: {{ $videoLesson->source_url }}</p>@endif
            <label class="block text-sm font-medium">Ganti dengan unggahan MP4 (opsional) <input type="file" name="video_file" accept="video/mp4,.mp4" class="mt-1 block w-full text-sm"></label>
            @if($hasAnswers)<p class="text-xs text-amber-700">Sumber video dan checkpoint terkunci karena siswa sudah menjawab, agar nilai tidak berubah. Judul/deskripsi tetap dapat diubah.</p>@endif
            <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">Simpan perubahan</button>
        </form>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
        <div class="flex justify-between items-start"><div><h2 class="text-lg font-semibold">Status & Publikasi</h2><p class="text-xs text-slate-500 mt-1">{{ $videoLesson->checkpoints->count() }} checkpoint · {{ $attemptCount }} pengerjaan selesai</p></div><span class="rounded-lg px-3 py-1 text-xs {{ $videoLesson->is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $videoLesson->is_published ? 'Diterbitkan' : 'Draf' }}</span></div>
        <p class="text-sm text-slate-600">{{ $videoLesson->is_published ? 'Siswa anggota kelas dapat menonton dan mengerjakan.' : 'Video belum muncul untuk siswa. Publikasikan setelah membuat soal.' }}</p>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <h3 class="text-sm font-semibold text-slate-900">Kontrol pemutaran siswa</h3>
            <p class="mt-1 text-xs text-slate-600">Atur sebelum publikasi. Siswa dapat mengetuk dua kali sisi kanan video untuk maju, atau sisi kiri untuk mundur.</p>
            @if(!$videoLesson->is_published && !$hasAnswers)
                <form method="POST" action="{{ route('video-lessons.playback.update', $videoLesson) }}" class="mt-3 space-y-3">
                    @csrf @method('PUT')
                    <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="allow_seeking" value="1" @checked($videoLesson->allow_seeking) class="mt-1 rounded border-slate-300"> <span>Izinkan ketuk dua kali untuk skip video <small class="block text-xs text-slate-500">Sisi kanan maju, sisi kiri mundur. Checkpoint tetap wajib dijawab berurutan.</small></span></label>
                    <label class="block text-xs font-semibold text-slate-700">Jarak tiap lompatan
                        <select name="skip_seconds" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="5" @selected($videoLesson->skip_seconds === 5)>5 detik</option>
                            <option value="10" @selected($videoLesson->skip_seconds !== 5)>10 detik</option>
                        </select>
                    </label>
                    <button class="rounded-lg bg-slate-800 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-900">Simpan pengaturan pemutaran</button>
                </form>
            @else
                <p class="mt-3 text-xs text-slate-700">Ketuk dua kali untuk skip: <strong>{{ $videoLesson->allow_seeking ? 'Boleh' : 'Tidak boleh' }}</strong> · Jarak: <strong>{{ $videoLesson->skip_seconds }} detik</strong></p>
                <p class="mt-1 text-xs text-amber-700">{{ $hasAnswers ? 'Pengaturan terkunci karena sudah ada jawaban siswa.' : 'Sembunyikan video untuk mengubah pengaturan.' }}</p>
            @endif
        </div>
        <form method="POST" action="{{ route('video-lessons.publish', $videoLesson) }}">@csrf<button class="rounded-lg px-4 py-2.5 text-sm font-medium text-white {{ $videoLesson->is_published ? 'bg-amber-600' : 'bg-emerald-600' }}">{{ $videoLesson->is_published ? 'Sembunyikan dari siswa' : 'Publikasikan video' }}</button></form>
        <hr>
        <form method="POST" action="{{ route('video-lessons.destroy', $videoLesson) }}" onsubmit="return confirm('Hapus video beserta seluruh jawaban dan nilai siswa? Tidak bisa dibatalkan.');">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-600 hover:underline">Hapus video dan seluruh nilainya</button></form>
        <div class="rounded-xl bg-indigo-50 p-4 text-xs leading-6 text-indigo-800"><strong>Alur:</strong> Tambahkan URL / MP4 → buat checkpoint pada detik tertentu → publikasikan → siswa menonton → nilai 0–100 masuk tab Nilai dan transkrip guru.</div>
    </section>
</div>
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
    <h2 class="text-lg font-semibold mb-1">Checkpoint Pertanyaan</h2>
    <p class="text-xs text-slate-500 mb-5">Putar video atau geser timeline, lalu tentukan posisi checkpoint tanpa menghitung detik secara manual.</p>
    @if(!$hasAnswers)
    <div id="checkpoint-time-editor" class="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-slate-950">
        <div class="mx-auto w-full max-w-4xl" style="aspect-ratio: 16 / 9;">
            @if($videoLesson->source_type === 'youtube' && $youtubeId)
                <div id="video-editor-youtube" class="h-full w-full" aria-label="Pratinjau video YouTube"></div>
            @elseif($videoLesson->source_type === 'mp4')
                <video id="video-editor-mp4" class="h-full w-full" controls playsinline preload="metadata">
                    <source src="{{ $videoLesson->playback_url }}" type="video/mp4">
                    Browser Anda tidak mendukung video MP4.
                </video>
            @else
                <div class="flex h-full items-center justify-center p-6 text-center text-sm text-white">Sumber video tidak dapat dimuat.</div>
            @endif
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-900 px-4 py-3 text-xs text-slate-200">
            <span id="video-editor-position">Posisi video 00:00</span>
            <span id="video-editor-duration">Mendeteksi durasi video...</span>
        </div>
    </div>
    <details class="rounded-xl border border-indigo-200 bg-indigo-50/40 p-4 mb-5" open>
        <summary class="font-semibold text-sm text-indigo-800 cursor-pointer">+ Tambah pertanyaan</summary>
        <form method="POST" action="{{ route('video-checkpoints.store', $videoLesson) }}" class="grid gap-3 mt-4">@csrf
            @php $newCheckpointTime = (int) old('time_seconds', 5); @endphp
            <div class="rounded-xl border border-indigo-200 bg-white p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <label for="new-checkpoint-time" class="text-sm font-semibold text-slate-800">Posisi checkpoint</label>
                    <output data-time-output-for="new-checkpoint-time" class="rounded-lg bg-indigo-100 px-3 py-1 text-sm font-bold text-indigo-700">{{ sprintf('%02d:%02d', intdiv($newCheckpointTime, 60), $newCheckpointTime % 60) }}</output>
                </div>
                <input id="new-checkpoint-time" type="range" name="time_seconds" min="0" max="86400" step="1" value="{{ $newCheckpointTime }}" required class="video-time-range w-full cursor-pointer" style="accent-color: #4f46e5;" data-initial-time="{{ $newCheckpointTime }}">
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs text-slate-500">Slider otomatis menyesuaikan durasi video.</span>
                    <button type="button" data-use-player-time data-time-target="new-checkpoint-time" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Gunakan posisi video saat ini</button>
                </div>
            </div>
            <label class="text-sm">Pertanyaan <textarea name="question_text" rows="2" maxlength="3000" required class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"></textarea></label>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach(['A','B','C','D'] as $index => $letter)
                    <label class="text-sm">Pilihan {{ $letter }} <input type="text" name="choices[{{ $index }}]" required maxlength="500" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"></label>
                @endforeach
            </div>
            <label class="text-sm">Jawaban benar <select name="correct_index" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"><option value="0">A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option></select></label>
            <label class="text-sm">Penjelasan jawaban (opsional) <textarea name="explanation" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5"></textarea></label>
            <button class="justify-self-start rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Simpan checkpoint</button>
        </form>
    </details>
    @endif
    <div class="space-y-3">
        @forelse($videoLesson->checkpoints as $checkpoint)
        <details class="rounded-xl border border-slate-200 bg-slate-50/40 p-4">
            <summary class="cursor-pointer text-sm font-semibold text-slate-800">⏸ {{ sprintf('%02d:%02d', intdiv($checkpoint->time_seconds, 60), $checkpoint->time_seconds % 60) }} · {{ $checkpoint->question_text }}</summary>
            <div class="mt-4">
                @if(!$hasAnswers)
                <form method="POST" action="{{ route('video-checkpoints.update', [$videoLesson, $checkpoint]) }}" class="grid gap-3">@csrf @method('PUT')
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <label for="checkpoint-time-{{ $checkpoint->id }}" class="text-sm font-medium">Posisi checkpoint</label>
                            <output data-time-output-for="checkpoint-time-{{ $checkpoint->id }}" class="rounded-lg bg-slate-100 px-3 py-1 text-sm font-bold text-slate-700">{{ sprintf('%02d:%02d', intdiv($checkpoint->time_seconds, 60), $checkpoint->time_seconds % 60) }}</output>
                        </div>
                        <input id="checkpoint-time-{{ $checkpoint->id }}" type="range" min="0" max="86400" step="1" name="time_seconds" value="{{ $checkpoint->time_seconds }}" required class="video-time-range w-full cursor-pointer" style="accent-color: #4f46e5;" data-initial-time="{{ $checkpoint->time_seconds }}">
                        <div class="mt-2 text-right"><button type="button" data-use-player-time data-time-target="checkpoint-time-{{ $checkpoint->id }}" class="text-xs font-semibold text-indigo-700 hover:underline">Gunakan posisi video saat ini</button></div>
                    </div>
                    <label class="text-sm">Pertanyaan <textarea name="question_text" required class="mt-1 w-full rounded-lg border border-slate-300 p-2" rows="2">{{ $checkpoint->question_text }}</textarea></label>
                    <div class="grid gap-3 sm:grid-cols-2">@foreach($checkpoint->choices as $index => $choice)<label class="text-sm">Pilihan {{ ['A','B','C','D'][$index] }} <input type="text" name="choices[{{ $index }}]" value="{{ $choice }}" maxlength="500" required class="mt-1 w-full rounded-lg border border-slate-300 p-2"></label>@endforeach</div>
                    <label class="text-sm">Kunci jawaban <select name="correct_index" class="mt-1 w-full rounded-lg border border-slate-300 p-2">@foreach(['A','B','C','D'] as $i=>$choice)<option value="{{ $i }}" @selected($checkpoint->correct_index===$i)>{{ $choice }}</option>@endforeach</select></label>
                    <label class="text-sm">Penjelasan <textarea name="explanation" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 p-2">{{ $checkpoint->explanation }}</textarea></label>
                    <button class="justify-self-start rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">Simpan perubahan soal</button>
                </form>
                <form method="POST" action="{{ route('video-checkpoints.destroy', [$videoLesson, $checkpoint]) }}" onsubmit="return confirm('Hapus pertanyaan ini?');" class="mt-3">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Hapus pertanyaan</button></form>
                @else
                    <p class="text-sm text-slate-600">Jawaban benar: {{ ['A','B','C','D'][$checkpoint->correct_index] }} — {{ $checkpoint->choices[$checkpoint->correct_index] }}</p>
                    <p class="text-xs text-slate-500 mt-2">Pertanyaan terkunci karena sudah ada jawaban siswa.</p>
                @endif
            </div>
        </details>
        @empty
        <p class="rounded-xl border border-dashed border-slate-300 p-6 text-sm text-slate-500">Belum ada pertanyaan. Tambahkan checkpoint terlebih dahulu.</p>
        @endforelse
    </div>
</section>
<section id="student-video-progress" class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
    <div class="border-b border-slate-200 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Progres Pengerjaan Siswa</h2>
                <p class="mt-1 text-xs text-slate-500">Pantau siswa yang belum mulai, sedang mengerjakan, atau sudah menyelesaikan video ini.</p>
            </div>
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ $attemptCount }}/{{ $students->count() }} siswa selesai</span>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-emerald-50 p-3"><span class="text-xs text-emerald-700">Sudah selesai</span><strong class="mt-1 block text-2xl text-emerald-800">{{ $attemptCount }}</strong></div>
            <div class="rounded-xl bg-amber-50 p-3"><span class="text-xs text-amber-700">Sedang mengerjakan</span><strong class="mt-1 block text-2xl text-amber-800">{{ $inProgressCount }}</strong></div>
            <div class="rounded-xl bg-slate-100 p-3"><span class="text-xs text-slate-600">Belum mulai</span><strong class="mt-1 block text-2xl text-slate-800">{{ $notStartedCount }}</strong></div>
        </div>
    </div>
    @if($students->isEmpty())
        <div class="p-8 text-center text-sm text-slate-500">Belum ada siswa yang tergabung di kelas ini.</div>
    @else
        <div class="mobile-data-table overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Siswa</th>
                        <th class="px-5 py-3 text-center font-semibold">Checkpoint</th>
                        <th class="px-5 py-3 text-center font-semibold">Status</th>
                        <th class="px-5 py-3 text-center font-semibold">Nilai</th>
                        <th class="px-5 py-3 font-semibold">Waktu selesai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($students as $student)
                        @php
                            $studentAttempt = $studentAttempts->get($student->id);
                            $answeredCount = (int) ($studentAttempt?->answers_count ?? 0);
                            $checkpointCount = $videoLesson->checkpoints->count();
                        @endphp
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4">
                                <strong class="block text-slate-900">{{ $student->name }}</strong>
                                <span class="text-xs text-slate-500">{{ $student->nomor_induk ? 'NIS '.$student->nomor_induk : 'NIS belum diisi' }}</span>
                            </td>
                            <td class="px-5 py-4 text-center font-medium text-slate-700">{{ $answeredCount }}/{{ $checkpointCount }}</td>
                            <td class="px-5 py-4 text-center">
                                @if($studentAttempt?->completed_at)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Selesai</span>
                                @elseif($studentAttempt)
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Sedang mengerjakan</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Belum mulai</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center">
                                @if($studentAttempt?->completed_at)
                                    <strong class="text-emerald-700">{{ rtrim(rtrim(number_format((float) $studentAttempt->score, 2, '.', ''), '0'), '.') }}/100</strong>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">
                                {{ $studentAttempt?->completed_at?->translatedFormat('d M Y, H:i') ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@if(!$hasAnswers)
<script>
window.VideoTaskEditor = {{ \Illuminate\Support\Js::from([
    'sourceType' => $videoLesson->source_type,
    'youtubeId' => $youtubeId,
]) }};
</script>
<script src="{{ asset('video-task/js/editor.js') }}"></script>
@if($videoLesson->source_type === 'youtube' && $youtubeId)
<script src="https://www.youtube.com/iframe_api"></script>
@endif
@endif
@endsection
