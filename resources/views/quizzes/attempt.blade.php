@extends('layouts.app')
@section('title', 'Hasil '.$quiz->title)

@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
@endphp

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-4 text-sm">
        <a href="{{ route('quizzes.show', $quiz) }}" class="text-indigo-700 hover:underline">← {{ $quiz->title }}</a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-6">
        <h1 class="text-xl font-semibold">{{ $quiz->title }}</h1>
        <div class="text-sm text-slate-500 mt-1">
            {{ $attempt->user->name }} · selesai {{ $attempt->finished_at->translatedFormat('d M Y, H:i') }}
        </div>

        <div class="mt-4 flex items-baseline gap-2">
            <span class="text-4xl font-bold text-indigo-700">{{ $fmt($attempt->score) }}</span>
            <span class="text-slate-500">/ {{ $totalPoints }} poin</span>
        </div>

        @unless($attempt->is_graded)
            <p class="mt-2 text-sm text-amber-700">Masih ada soal esai yang menunggu penilaian guru. Nilai akhir dapat berubah.</p>
        @endunless

        @if($quiz->monitoring_enabled)
            @php $integrityLabel = $attempt->integrityLabel($violationCount); @endphp
            <div class="mt-5 pt-5 border-t border-slate-100">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold">Ringkasan integritas ujian</h2>
                        <p class="text-[11px] text-slate-500 mt-1">Indikator kehilangan fokus selama pengerjaan.</p>
                    </div>
                    <span class="integrity-badge {{ $violationCount === 0 ? 'integrity-safe' : ($violationCount <= 2 ? 'integrity-review' : 'integrity-risk') }}">{{ $integrityLabel }} · {{ $violationCount }} peringatan</span>
                </div>

                @if($isManager)
                    <div class="integrity-timeline">
                        @forelse($securityEvents as $event)
                            <div class="integrity-event {{ $event->isViolation() ? 'violation' : '' }}">
                                <strong>{{ $event->label() }}</strong>
                                <span>{{ $event->occurred_at->translatedFormat('d M Y, H:i:s') }}@if($event->duration_seconds !== null) · durasi {{ $event->duration_seconds }} detik @endif</span>
                            </div>
                        @empty
                            <p class="text-xs text-emerald-700 m-0">Tidak ada perpindahan fokus yang tercatat.</p>
                        @endforelse
                    </div>
                    <p class="text-[10px] leading-5 text-slate-400 mt-3">Gunakan catatan ini bersama konteks lain. Kehilangan fokus dapat disebabkan notifikasi sistem, panggilan, masalah perangkat, atau tindakan pengguna.</p>
                @endif
            </div>
        @endif
    </div>

    @if($isManager)
        <form method="POST" action="{{ route('attempts.grade', $attempt) }}" class="space-y-4">
            @csrf @method('PUT')
    @else
        <div class="space-y-4">
    @endif

        @foreach($questions as $i => $question)
            @php $answer = $answers->get($question->id); @endphp
            <div class="bg-white rounded-xl border p-5
                {{ $question->isPilihanGanda()
                    ? ($answer && $answer->is_correct ? 'border-emerald-300' : 'border-red-300')
                    : 'border-slate-200' }}">
                <div class="text-xs text-slate-500 mb-1">
                    Soal {{ $i + 1 }} · {{ $question->isPilihanGanda() ? 'Pilihan ganda' : 'Esai' }} · maks. {{ $question->points }} poin
                </div>
                <p class="whitespace-pre-line mb-3">{{ $question->body }}</p>

                @if($question->isPilihanGanda())
                    <ul class="text-sm space-y-1">
                        @foreach($question->options() as $key => $text)
                            @php
                                $isChosen = $answer && $answer->answer === $key;
                                $isKey = $key === $question->correct_option;
                            @endphp
                            <li class="rounded px-2 py-1
                                {{ $isKey ? 'bg-emerald-50 text-emerald-800 font-medium' : ($isChosen ? 'bg-red-50 text-red-800' : 'text-slate-600') }}">
                                {{ strtoupper($key) }}. {{ $text }}
                                @if($isChosen) <span class="text-xs">(jawaban {{ $isManager ? 'siswa' : 'kamu' }})</span> @endif
                                @if($isKey) <span class="text-xs">✓ kunci</span> @endif
                            </li>
                        @endforeach
                    </ul>
                    @if(! $answer || $answer->answer === null)
                        <p class="text-xs text-slate-500 mt-2">Tidak dijawab.</p>
                    @endif
                    <div class="text-sm mt-2 text-slate-600">Skor: {{ $answer ? $fmt($answer->score) : 0 }} / {{ $question->points }}</div>
                @else
                    <div class="bg-slate-50 rounded-lg p-3 text-sm whitespace-pre-line">{{ $answer && filled($answer->answer) ? $answer->answer : 'Tidak dijawab.' }}</div>

                    @if($isManager && $answer)
                        <div class="mt-3 flex items-center gap-2 text-sm">
                            <label>Nilai</label>
                            <input type="number" step="0.01" min="0" max="{{ $question->points }}" name="scores[{{ $answer->id }}]"
                                   value="{{ $answer->score !== null ? $fmt($answer->score) : '' }}"
                                   class="w-24 rounded-lg border border-slate-300 px-2 py-1.5">
                            <span class="text-slate-500">/ {{ $question->points }}</span>
                        </div>
                    @else
                        <div class="text-sm mt-2 text-slate-600">
                            Skor: {{ $answer && $answer->score !== null ? $fmt($answer->score).' / '.$question->points : 'menunggu penilaian guru' }}
                        </div>
                    @endif
                @endif
            </div>
        @endforeach

    @if($isManager)
            @if($questions->contains(fn ($q) => ! $q->isPilihanGanda()))
                <div class="text-right">
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-6 py-2 text-sm font-medium">Simpan nilai esai</button>
                </div>
            @endif
        </form>
    @else
        </div>
    @endif
</div>
@endsection
