@extends('layouts.app')
@section('title', 'Transkrip Saya')

@php
    $fmt = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp

@section('content')
<header class="mb-6 rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-indigo-800 p-7 text-white">
    <p class="text-xs font-semibold uppercase tracking-widest text-indigo-200">Perkembangan akademik</p>
    <h1 class="mt-2 text-3xl font-bold">Transkrip Saya</h1>
    <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100">Seluruh kelas yang kamu ikuti ada di satu rekap. Nilai setiap kelas muncul setelah guru kelas tersebut melakukan Final Validation.</p>
    @if($classrooms->isNotEmpty())
        <div class="mt-5 flex flex-wrap items-center gap-3">
            <span class="rounded-xl bg-white/15 px-4 py-2 text-sm">{{ $validatedCount }} dari {{ $classrooms->count() }} kelas tervalidasi</span>
            <a href="{{ route('student.transcripts.all.csv') }}" class="lms-button lms-button-light">Unduh CSV</a>
            <a href="{{ route('student.transcripts.all.excel') }}" class="lms-button lms-button-primary">Excel (.xlsx)</a>
        </div>
    @endif
</header>

@if($classrooms->isEmpty())
    <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-7 text-center">
        <h2 class="text-lg font-semibold text-slate-900">Belum ada kelas yang diikuti</h2>
        <p class="mt-2 text-sm text-slate-500">Gabung ke kelas menggunakan kode dari guru untuk melihat transkripmu.</p>
        <a href="{{ route('dashboard') }}#kelas" class="lms-button lms-button-primary mt-4">Buka kelas saya</a>
    </section>
@else
    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs text-slate-500">Kelas diikuti</p><strong class="mt-2 block text-2xl text-slate-900">{{ $classrooms->count() }}</strong></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs text-slate-500">Sudah divalidasi</p><strong class="mt-2 block text-2xl text-slate-900">{{ $validatedCount }}</strong></div>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5"><p class="text-xs text-indigo-700">Rata-rata nilai final</p><strong class="mt-2 block text-2xl text-indigo-950">{{ $average !== null ? $fmt($average) : '—' }}</strong><span class="text-xs text-indigo-700"> / 100</span></div>
    </div>

    <section aria-labelledby="student-transcript-details" class="min-w-0">
        <h2 id="student-transcript-details" class="text-lg font-bold text-slate-900">Rekap nilai seluruh kelas</h2>
        <p class="mt-1 text-xs text-slate-500">Kelas yang belum divalidasi tetap tercantum dengan kolom nilai kosong. Rata-rata dihitung dari {{ $completed }} nilai final pada kelas tervalidasi.</p>
        <p id="student-transcript-scroll-help" class="mb-2 mt-3 text-xs text-slate-500">Pada layar kecil, geser tabel ke samping untuk melihat semua kolom.</p>
        <div class="mobile-data-table overflow-x-auto rounded-2xl border border-slate-200 bg-white" role="region" aria-label="Rekap nilai seluruh kelas" aria-describedby="student-transcript-scroll-help" tabindex="0">
            <table class="min-w-[980px] w-full text-sm">
                <thead class="bg-slate-50 text-slate-700">
                    <tr>
                        <th scope="col" class="w-12 px-3 py-3 text-center font-semibold">No.</th>
                        <th scope="col" class="min-w-40 px-3 py-3 text-left font-semibold">Kelas</th>
                        <th scope="col" class="w-20 px-3 py-3 text-center font-semibold">Nilai</th>
                        <th scope="col" class="min-w-36 px-3 py-3 text-left font-semibold">Status</th>
                        <th scope="col" class="min-w-60 px-3 py-3 text-left font-semibold">Aktivitas</th>
                        <th scope="col" class="w-32 px-3 py-3 text-left font-semibold">Jenis</th>
                        <th scope="col" class="min-w-36 px-3 py-3 text-left font-semibold">Guru</th>
                        <th scope="col" class="w-20 px-3 py-3 text-center font-semibold">Maks.</th>
                        <th scope="col" class="w-24 px-3 py-3 text-center font-semibold">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $entry)
                        @php $score = $entry['score']; @endphp
                        <tr class="border-t border-slate-100 align-top">
                            <td class="px-3 py-3 text-center text-slate-500">{{ $loop->iteration }}</td>
                            <td class="px-3 py-3 font-medium text-slate-900"><span class="quiz-readable-text block">{{ $entry['classroom']->name }}</span></td>
                            <td class="px-3 py-3 text-center font-semibold text-slate-900">{{ ($score['score'] ?? null) !== null ? $fmt($score['score']) : '—' }}</td>
                            <td class="px-3 py-3 {{ $entry['status'] === 'Belum divalidasi' ? 'font-semibold text-amber-700' : 'text-slate-600' }}">{{ $entry['status'] }}@if($score && !$score['final'] && $score['score'] !== null)<span class="block text-xs text-amber-700">Nilai sementara</span>@endif</td>
                            <td class="px-3 py-3 text-slate-800"><span class="quiz-readable-text block">{{ $entry['activity']['title'] ?? '—' }}</span></td>
                            <td class="px-3 py-3 text-slate-600">{{ $entry['activity']['type'] ?? '—' }}</td>
                            <td class="px-3 py-3 text-slate-600"><span class="quiz-readable-text block">{{ $entry['classroom']->teacher->name }}</span></td>
                            <td class="px-3 py-3 text-center text-slate-600">{{ ($score['maximum'] ?? null) !== null ? $fmt($score['maximum']) : '—' }}</td>
                            <td class="px-3 py-3 text-center text-slate-700">{{ ($score['percentage'] ?? null) !== null ? $fmt($score['percentage']).'%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-indigo-200 bg-indigo-50 font-semibold text-indigo-950">
                        <th colspan="2" scope="row" class="px-3 py-4 text-right">Rata-rata final</th>
                        <td class="px-3 py-4 text-center">{{ $average !== null ? $fmt($average).'%' : '—' }}</td>
                        <td colspan="6" class="px-3 py-4 text-left">{{ $completed }} nilai final dari kelas tervalidasi</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="mt-3 text-xs leading-5 text-slate-500">Nilai kosong berbeda dari 0. Kuis yang menunggu penilaian esai belum masuk rata-rata; nilai kuis mengikuti penalti jika berlaku.</p>
    </section>
@endif
@endsection
