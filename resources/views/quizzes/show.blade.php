@extends('layouts.app')
@section('title', $quiz->title)

@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
@endphp

@section('content')
<div class="mb-4 text-sm">
    <a href="{{ route('classrooms.classwork', $classroom) }}" class="text-indigo-700 hover:underline">← {{ $classroom->name }}</a>
</div>

{{-- ====================== TAMPILAN GURU ====================== --}}
@if($isManager)
    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ $quiz->title }}</h1>
                @if($quiz->description)
                    <p class="text-sm text-slate-600 mt-1 whitespace-pre-line">{{ $quiz->description }}</p>
                @endif
                <div class="text-sm text-slate-500 mt-2">
                    {{ $quiz->questions->count() }} soal · total {{ $quiz->totalPoints() }} poin ·
                    {{ $quiz->duration_minutes ? $quiz->duration_minutes.' menit' : 'tanpa batas waktu' }}
                    @if($quiz->start_at) · buka {{ $quiz->start_at->translatedFormat('d M Y, H:i') }} @endif
                    @if($quiz->end_at) · tutup {{ $quiz->end_at->translatedFormat('d M Y, H:i') }} @endif
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs px-2 py-1 rounded-full {{ $quiz->is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ $quiz->statusLabel() }}
                </span>
                <form method="POST" action="{{ route('quizzes.publish', $quiz) }}">
                    @csrf
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium">
                        {{ $quiz->is_published ? 'Tarik kembali (draft)' : 'Publikasikan' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('quizzes.destroy', $quiz) }}" onsubmit="return confirm('Hapus kuis beserta semua soal dan hasilnya?')">
                    @csrf @method('DELETE')
                    <button class="text-sm text-slate-400 hover:text-red-600">Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('quizzes.security.update', $quiz) }}" class="bg-white rounded-xl border border-slate-200 p-5 mb-6">
        @csrf @method('PUT')
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <div class="flex items-center gap-2">
                    <span class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 grid place-items-center">
                        <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <div><h2 class="font-semibold">Keamanan ujian</h2><p class="text-xs text-slate-500 mt-0.5">Mencatat indikator perpindahan tab atau hilangnya fokus selama siswa mengerjakan.</p></div>
                </div>
                <p class="text-[11px] leading-5 text-slate-400 mt-3">Catatan keamanan adalah indikator untuk membantu peninjauan guru, bukan bukti mutlak kecurangan. Sistem tidak merekam layar, kamera, isi notifikasi, atau aplikasi lain.</p>
            </div>
            <div class="w-full space-y-2 sm:w-auto sm:min-w-56">
                <label class="flex items-center gap-2 text-xs cursor-pointer"><input type="checkbox" name="monitoring_enabled" value="1" @checked($quiz->monitoring_enabled) class="rounded text-indigo-600"> Aktifkan pemantauan fokus</label>
                <label class="flex items-center gap-2 text-xs cursor-pointer"><input type="checkbox" name="require_fullscreen" value="1" @checked($quiz->require_fullscreen) class="rounded text-indigo-600"> Wajibkan mode layar penuh</label>
                <label class="flex items-center gap-2 text-xs cursor-pointer"><input type="checkbox" name="shuffle_questions" value="1" @checked($quiz->shuffle_questions) class="rounded text-indigo-600"> Acak urutan soal per siswa</label>
                <button class="lms-button lms-button-primary mt-2">Simpan keamanan</button>
            </div>
        </div>
    </form>

    <div class="grid md:grid-cols-2 gap-6">
        {{-- Daftar soal --}}
        <section>
            <h2 class="text-lg font-semibold mb-3">Soal</h2>
            <div class="space-y-3">
                @forelse($quiz->questions as $i => $question)
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="text-sm">
                                <span class="font-medium">{{ $i + 1 }}.</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 ml-1">
                                    {{ $question->isPilihanGanda() ? 'Pilihan ganda' : 'Esai' }} · {{ $question->points }} poin
                                </span>
                                <p class="mt-2 whitespace-pre-line">{{ $question->body }}</p>
                            </div>
                            <form method="POST" action="{{ route('questions.destroy', $question) }}" onsubmit="return confirm('Hapus soal ini?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-slate-400 hover:text-red-600">Hapus</button>
                            </form>
                        </div>
                        @if($question->isPilihanGanda())
                            <ul class="mt-2 text-sm space-y-1">
                                @foreach($question->options() as $key => $text)
                                    <li class="{{ $key === $question->correct_option ? 'text-emerald-700 font-medium' : 'text-slate-600' }}">
                                        {{ strtoupper($key) }}. {{ $text }} @if($key === $question->correct_option) ✓ @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada soal. Tambahkan lewat form di samping.</p>
                @endforelse
            </div>
        </section>

        {{-- Form tambah soal --}}
        <section>
            <h2 class="text-lg font-semibold mb-3">Tambah soal</h2>
            <form method="POST" action="{{ route('questions.store', $quiz) }}" class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Jenis soal</label>
                        <select name="type" id="q-type" onchange="document.getElementById('pg-fields').style.display = this.value === 'pilihan_ganda' ? 'block' : 'none'"
                                class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                            <option value="pilihan_ganda">Pilihan ganda</option>
                            <option value="esai">Esai</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Poin</label>
                        <input type="number" name="points" value="10" min="1" max="1000" required class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-500 mb-1">Pertanyaan</label>
                    <textarea name="body" rows="3" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('body') }}</textarea>
                </div>

                <div id="pg-fields" class="space-y-2">
                    @foreach(['a', 'b', 'c', 'd'] as $key)
                        <div class="flex items-center gap-2">
                            <span class="w-5 text-sm font-medium">{{ strtoupper($key) }}</span>
                            <input type="text" name="option_{{ $key }}" value="{{ old('option_'.$key) }}"
                                   placeholder="Opsi {{ strtoupper($key) }}{{ in_array($key, ['c', 'd']) ? ' (opsional)' : '' }}"
                                   class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </div>
                    @endforeach
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Kunci jawaban</label>
                        <select name="correct_option" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                            @foreach(['a', 'b', 'c', 'd'] as $key)
                                <option value="{{ $key }}" @selected(old('correct_option') === $key)>{{ strtoupper($key) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <p class="text-xs text-slate-500">Soal esai dinilai manual oleh guru setelah siswa mengumpulkan.</p>
                <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium">Tambah soal</button>
            </form>
        </section>
    </div>

    {{-- Hasil siswa --}}
    <h2 class="text-lg font-semibold mt-8 mb-3">Hasil siswa</h2>
    <div class="mobile-data-table overflow-x-auto bg-white rounded-xl border border-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="text-left px-4 py-3 font-medium">Siswa</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Nilai</th>
                    <th class="px-4 py-3 font-medium">Integritas</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($attempts as $attempt)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ $attempt->user->name }}</td>
                        <td class="px-4 py-3 text-center">
                            @if(! $attempt->isFinished())
                                <span class="text-amber-600 text-xs">Sedang mengerjakan</span>
                            @elseif(! $attempt->is_graded)
                                <span class="text-amber-600 text-xs">Menunggu nilai esai</span>
                            @else
                                <span class="text-emerald-600 text-xs">Selesai</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($attempt->isFinished()) {{ $fmt($attempt->score) }} / {{ $quiz->totalPoints() }} @else – @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($quiz->monitoring_enabled)
                                @php $integrityLabel = $attempt->integrityLabel((int) $attempt->violation_count); @endphp
                                <span class="integrity-badge {{ $attempt->violation_count === 0 ? 'integrity-safe' : ($attempt->violation_count <= 2 ? 'integrity-review' : 'integrity-risk') }}">
                                    {{ $integrityLabel }} · {{ $attempt->violation_count }}
                                </span>
                            @else
                                <span class="text-xs text-slate-400">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($attempt->isFinished())
                                <a href="{{ route('attempts.show', $attempt) }}" class="text-indigo-700 hover:underline">Lihat / nilai</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Belum ada siswa yang mengerjakan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

{{-- ====================== TAMPILAN SISWA ====================== --}}
@else
    <div class="bg-white rounded-xl border border-slate-200 p-6 max-w-2xl">
        <h1 class="text-2xl font-semibold">{{ $quiz->title }}</h1>
        @if($quiz->description)
            <p class="text-sm text-slate-600 mt-2 whitespace-pre-line">{{ $quiz->description }}</p>
        @endif

        <ul class="text-sm text-slate-600 mt-4 space-y-1">
            <li>Jumlah soal: <b>{{ $questionCount }}</b></li>
            <li>Waktu: <b>{{ $quiz->duration_minutes ? $quiz->duration_minutes.' menit' : 'tanpa batas' }}</b></li>
            @if($quiz->start_at)<li>Dibuka: {{ $quiz->start_at->translatedFormat('d M Y, H:i') }}</li>@endif
            @if($quiz->end_at)<li>Ditutup: {{ $quiz->end_at->translatedFormat('d M Y, H:i') }}</li>@endif
            <li>Kesempatan mengerjakan: <b>1 kali</b></li>
        </ul>

        @if($quiz->monitoring_enabled)
            <div class="mt-5 rounded-xl border border-indigo-100 bg-indigo-50/70 p-4">
                <div class="flex gap-3">
                    <svg viewBox="0 0 24 24" width="20" height="20" class="text-indigo-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                    <div><p class="text-sm font-semibold text-indigo-900">Mode ujian terpantau</p><p class="text-xs text-indigo-700/80 mt-1 leading-5">Perpindahan tab, hilangnya fokus jendela{{ $quiz->require_fullscreen ? ', dan keluar dari layar penuh' : '' }} akan dicatat. Sistem tidak melihat isi layar atau notifikasi pribadi.</p></div>
                </div>
            </div>
        @endif

        @if($quiz->shuffle_questions)
            <div class="mt-3 rounded-xl border border-amber-100 bg-amber-50/70 p-4">
                <div class="flex gap-3">
                    <svg viewBox="0 0 24 24" width="20" height="20" class="text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg>
                    <div><p class="text-sm font-semibold text-amber-900">Urutan soal diacak</p><p class="text-xs text-amber-700/80 mt-1 leading-5">Setiap siswa dapat menerima nomor soal dalam urutan berbeda. Urutanmu akan tetap sama jika halaman dimuat ulang.</p></div>
                </div>
            </div>
        @endif

        <div class="mt-6">
            @if($attempt && $attempt->isFinished())
                <a href="{{ route('attempts.show', $attempt) }}" class="inline-block bg-sky-600 hover:bg-sky-700 text-white rounded-lg px-5 py-2 text-sm font-medium">Lihat hasil</a>
            @elseif($attempt)
                <a href="{{ route('attempts.take', $attempt) }}" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-5 py-2 text-sm font-medium">Lanjutkan mengerjakan</a>
            @elseif($quiz->isOpen())
                <form method="POST" action="{{ route('attempts.start', $quiz) }}" onsubmit="return confirm('Mulai kuis sekarang? Waktu langsung berjalan dan kamu hanya punya 1 kesempatan.')">
                    @csrf
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-5 py-2 text-sm font-medium">Mulai kuis</button>
                </form>
            @else
                <p class="text-sm text-amber-700">Kuis saat ini: {{ $quiz->statusLabel() }}.</p>
            @endif
        </div>
    </div>
@endif
@endsection
