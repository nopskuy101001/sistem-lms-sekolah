@extends('layouts.app')
@section('title', 'Transkrip Nilai')

@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    $predicate = function ($score) {
        if ($score === null) return ['Belum ada nilai', 'bg-slate-100 text-slate-500'];
        if ($score >= 90) return ['Sangat Baik', 'bg-emerald-50 text-emerald-700'];
        if ($score >= 80) return ['Baik', 'bg-sky-50 text-sky-700'];
        if ($score >= 70) return ['Cukup', 'bg-amber-50 text-amber-700'];
        return ['Perlu bimbingan', 'bg-rose-50 text-rose-700'];
    };
@endphp

@section('content')
<header class="rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-indigo-800 p-7 text-white shadow-xl shadow-indigo-100 mb-6 overflow-hidden relative">
    <div class="absolute -right-12 -top-16 h-48 w-48 rounded-full border-[32px] border-white/5"></div>
    <div class="relative flex flex-wrap items-end justify-between gap-5">
        <div class="max-w-2xl">
            <div class="text-xs uppercase tracking-[.22em] text-indigo-200 font-semibold mb-2">Laporan akademik guru</div>
            <h1 class="text-3xl font-bold">Transkrip Nilai Siswa</h1>
            <p class="text-sm leading-6 text-indigo-100/80 mt-2">Pantau dan koreksi nilai tugas, kuis, serta video. Gunakan Final Validation setelah semua nilai diverifikasi.</p>
        </div>
        @if($classrooms->isNotEmpty())
            <form method="GET" action="{{ route('transcripts.index') }}" class="w-full rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur-sm sm:w-auto sm:min-w-64">
                <label for="classroom" class="block text-[11px] uppercase tracking-wider text-indigo-100 mb-1">Pilih kelas</label>
                <select id="classroom" name="classroom" onchange="this.form.submit()" class="w-full rounded-xl border-0 bg-white px-3 py-2.5 text-sm font-semibold text-slate-800">
                    @foreach($classrooms as $item)
                        <option value="{{ $item->id }}" @selected($classroom?->id === $item->id)>{{ $item->name }} ({{ $item->students_count }} siswa)</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>
</header>

@if(!$classroom)
    <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
        <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-indigo-50 text-indigo-600 mb-4">
            <svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 3h16v18H4z"/><path d="M8 7h8M8 11h8M8 15h5"/></svg>
        </div>
        <h2 class="font-semibold text-slate-900">Belum ada kelas yang dapat dibuatkan transkrip</h2>
        <p class="text-sm text-slate-500 mt-2">Buat kelas dan tambahkan siswa terlebih dahulu.</p>
        <a href="{{ route('dashboard') }}" class="lms-button lms-button-primary mt-5">Kembali ke beranda</a>
    </section>
@else
    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-slate-900">{{ $classroom->name }}</h2>
            <p class="text-sm text-slate-500 mt-1">{{ $classroom->subject ?: 'Mata pelajaran belum diisi' }}@if($classroom->section) · {{ $classroom->section }}@endif · diperbarui {{ now()->translatedFormat('d M Y, H:i') }}</p>
        </div>
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
            <a href="{{ route('transcripts.csv', $classroom) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-indigo-300 hover:text-indigo-700">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M5 19h14"/></svg>CSV
            </a>
            <a href="{{ route('transcripts.excel', $classroom) }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 shadow-sm">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 3h11l5 5v13H4z"/><path d="M14 3v6h6M8 13l4 5m0-5-4 5"/></svg>Excel (.xlsx)
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif
    @if($report['finalized'])
        <div class="mb-5 rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">
            <strong>Transkrip final — terkunci.</strong> Final Validation dilakukan {{ $report['finalized_at']?->translatedFormat('d M Y H:i') }}. Data dan ekspor memakai snapshot saat validasi; perubahan nilai sumber setelah itu tidak mengubah transkrip final.
        </div>
    @else
        <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Draft transkrip (dapat diedit).</strong> Klik "Koreksi nilai" pada sel siswa. Nilai 0 sah untuk yang tidak mengerjakan; nilai kosong tidak otomatis sama dengan 0. Koreksi tidak menimpa jawaban, tugas, atau rekaman video asli.
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3 mb-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs uppercase tracking-wider text-slate-400">Jumlah siswa</p><strong class="mt-2 block text-3xl text-slate-900">{{ $report['student_count'] }}</strong><span class="text-xs text-slate-500">anggota kelas</span></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs uppercase tracking-wider text-slate-400">Komponen nilai</p><strong class="mt-2 block text-3xl text-slate-900">{{ $report['activity_count'] }}</strong><span class="text-xs text-slate-500">tugas, kuis, dan video</span></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs uppercase tracking-wider text-slate-400">Rata-rata kelas</p><strong class="mt-2 block text-3xl text-indigo-700">{{ $report['class_average'] !== null ? $fmt($report['class_average']) : '—' }}</strong><span class="text-xs text-slate-500">skala 0–100</span></div>
    </div>

    @if($report['rows']->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Belum ada siswa di kelas ini. Transkrip dapat diunduh setelah siswa bergabung.</div>
    @elseif($report['activities']->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Belum ada tugas, kuis, atau video sebagai komponen transkrip.</div>
    @else
        <section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
            <p id="transcript-scroll-help" class="border-b border-slate-100 px-4 py-3 text-xs text-slate-500">Geser tabel ke samping untuk melihat semua nilai dan rata-rata. Buka Koreksi nilai untuk mengubah nilai siswa.</p>
            <div class="mobile-data-table overflow-x-auto" role="region" aria-label="Transkrip nilai siswa" aria-describedby="transcript-scroll-help" tabindex="0">
                <table class="academic-score-table min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th scope="col" class="score-student bg-slate-50 px-4 py-3 text-left font-semibold">Siswa</th>
                            @foreach($report['activities'] as $activity)
                                <th scope="col" class="px-4 py-3 text-center font-semibold min-w-36"><span class="block text-[10px] uppercase tracking-wider {{ $activity['type'] === 'Kuis' ? 'text-violet-600' : 'text-sky-600' }}">{{ $activity['type'] }}</span><span class="score-activity-title">{{ $activity['title'] }}</span><span class="block text-[11px] font-normal text-slate-400">maks. {{ $fmt($activity['maximum']) }}</span></th>
                            @endforeach
                            <th scope="col" class="score-average bg-indigo-50 px-4 py-3 text-center font-semibold text-indigo-900 min-w-36">Rata-rata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['rows'] as $row)
                            @php [$label, $badgeClass] = $predicate($row['average']); @endphp
                            <tr class="border-t border-slate-100 hover:bg-slate-50/50">
                                <td class="score-student bg-white px-4 py-3">
                                    <div class="font-semibold text-slate-900">{{ $row['student']->name }}</div>
                                    <div class="text-xs text-slate-400">{{ $row['student']->nomor_induk ?: 'Nomor induk belum diisi' }}</div>
                                </td>
                                @foreach($report['activities'] as $activity)
                                    @php $score = $row['scores'][$activity['key']]; @endphp
                                    <td class="px-4 py-3 text-center">
                                        @if($score['score'] !== null)
                                            <span class="font-semibold text-slate-800">{{ $fmt($score['score']) }} / {{ $fmt($score['maximum']) }}</span>
                                            <span class="block text-[11px] {{ $score['final'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $fmt($score['percentage']) }}%{{ !$score['final'] ? ' · sementara' : '' }}</span>
                                        @else
                                            <span class="text-xs text-slate-400">{{ $score['status'] }}</span>
                                        @endif
                                        @if($score['manual'] ?? false)
                                            <span class="block mt-1 text-[10px] font-semibold text-violet-700" title="{{ $score['reason'] ?? '' }}">Koreksi manual guru</span>
                                        @endif
                                        @unless($report['finalized'])
                                            <details class="mt-2 text-left group min-w-44">
                                                <summary class="cursor-pointer text-xs text-indigo-600 hover:text-indigo-800 select-none">Koreksi nilai</summary>
                                                <form method="POST" action="{{ route('transcripts.scores.update', $classroom) }}" class="mt-2 flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="student_id" value="{{ $row['student']->id }}">
                                                    <input type="hidden" name="activity_type" value="{{ explode('-', $activity['key'], 2)[0] }}">
                                                    <input type="hidden" name="activity_id" value="{{ explode('-', $activity['key'], 2)[1] }}">
                                                    <label class="text-[11px] text-slate-600">Nilai (0–{{ $fmt($score['maximum']) }})
                                                        <input type="number" name="score" min="0" max="{{ $score['maximum'] }}" step="0.01" required value="{{ $score['score'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-800">
                                                    </label>
                                                    <input type="text" name="reason" maxlength="500" placeholder="Alasan koreksi (opsional)" value="{{ $score['reason'] ?? '' }}" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs text-slate-700">
                                                    <button type="submit" class="rounded-lg bg-indigo-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-800">Simpan</button>
                                                </form>
                                                @if($score['manual'] ?? false)
                                                    <form method="POST" action="{{ route('transcripts.scores.clear', $classroom) }}" class="mt-1">
                                                        @csrf @method('DELETE')
                                                        <input type="hidden" name="student_id" value="{{ $row['student']->id }}">
                                                        <input type="hidden" name="activity_type" value="{{ explode('-', $activity['key'], 2)[0] }}">
                                                        <input type="hidden" name="activity_id" value="{{ explode('-', $activity['key'], 2)[1] }}">
                                                        <button type="submit" class="text-[11px] text-rose-600 hover:underline">Hapus koreksi (pakai nilai otomatis)</button>
                                                    </form>
                                                @endif
                                            </details>
                                        @endunless
                                    </td>
                                @endforeach
                                <td class="score-average bg-indigo-50 px-4 py-3 text-center">
                                    <strong class="text-lg text-indigo-800">{{ $row['average'] !== null ? $fmt($row['average']) : '—' }}</strong>
                                    <span class="mt-1 block rounded-full px-2 py-0.5 text-[10px] {{ $badgeClass }}">{{ $label }}</span>
                                    <span class="block text-[10px] text-slate-400 mt-1">{{ $row['completed'] }}/{{ $report['activity_count'] }} final</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 bg-slate-50 px-5 py-3 text-xs leading-5 text-slate-500">Rata-rata dihitung dari nilai final dalam skala 0–100. Koreksi guru dianggap sebagai nilai final komponen. Kuis yang menunggu penilaian esai tidak masuk rata-rata kecuali diberi koreksi guru.</div>
        </section>
        @unless($report['finalized'])
            <form method="POST" action="{{ route('transcripts.finalize', $classroom) }}" class="mt-5 rounded-2xl border-2 border-amber-300 bg-white p-5">
                @csrf
                <h3 class="text-base font-bold text-slate-900">Final Validation — kunci transkrip kelas</h3>
                <p class="mt-1 text-sm text-slate-600">Periksa kembali seluruh nilai, termasuk siswa yang tidak mengerjakan. Setelah dikonfirmasi, nilai transkrip dan hasil ekspor dibekukan. Tidak tersedia tombol buka kunci pada fitur ini.</p>
                <label class="mt-4 flex items-start gap-3 text-sm text-slate-800">
                    <input type="checkbox" name="final_validation" value="1" required class="mt-1 h-4 w-4 rounded border-slate-400">
                    <span>Saya telah memeriksa nilai dan menyetujui <strong>Final Validation</strong> untuk kelas ini. Saya memahami nilai transkrip tidak dapat diedit lagi.</span>
                </label>
                <button type="submit" onclick="return confirm('Final Validation akan mengunci seluruh nilai transkrip kelas ini. Lanjutkan?')" class="mt-4 rounded-xl bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-rose-800">Konfirmasi Final Validation</button>
            </form>
        @endunless
    @endif
@endif
@endsection
