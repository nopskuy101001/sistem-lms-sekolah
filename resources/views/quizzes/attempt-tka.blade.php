@extends('layouts.app')
@section('title', 'Hasil '.$quiz->title)
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); @endphp

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-4 text-sm"><a href="{{ route('quizzes.show', $quiz) }}" class="text-indigo-700 hover:underline">← {{ $quiz->title }}</a></div>
    <header class="bg-white rounded-2xl border border-slate-200 p-6 mb-6">
        <div class="flex flex-wrap justify-between gap-5">
            <div><div class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Hasil asesmen TKA</div><h1 class="text-2xl font-bold mt-1">{{ $quiz->title }}</h1><p class="text-sm text-slate-500 mt-1">{{ $attempt->user->name }} · selesai {{ $attempt->finished_at->translatedFormat('d M Y, H:i') }}</p></div>
            <div class="text-right"><div><span class="text-4xl font-bold text-indigo-700">{{ $fmt($attempt->score) }}</span><span class="text-slate-500"> / {{ $totalPoints }}</span></div><p class="text-xs text-slate-500 mt-1">nilai akhir</p>@if((float) $attempt->penalty_score > 0)<p class="text-xs text-amber-700 mt-1">Nilai mentah {{ $fmt($attempt->raw_score) }} - penalti {{ $fmt($attempt->penalty_score) }}</p>@endif</div>
        </div>
        @unless($attempt->is_graded)<p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Masih ada soal esai yang menunggu penilaian guru.</p>@endunless

        @if($quiz->monitoring_enabled || $quiz->require_fullscreen)
            @php $integrityLabel = $attempt->integrityLabel($violationCount); @endphp
            <div class="mt-5 pt-5 border-t border-slate-100">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-sm font-semibold">Ringkasan integritas ujian</h2><p class="text-xs text-slate-500 mt-1">Indikator kehilangan fokus atau keluar dari layar penuh, bukan vonis kecurangan.</p></div><span class="integrity-badge {{ $violationCount === 0 ? 'integrity-safe' : ($violationCount <= 2 ? 'integrity-review' : 'integrity-risk') }}">{{ $integrityLabel }} · {{ $violationCount }} peringatan</span></div>
                @if($quiz->penalty_enabled)<p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Kebijakan penalti: {{ $quiz->penalty_percent_per_violation }}% dari total poin per peringatan (maks. {{ \App\Models\Quiz::MAX_VIOLATION_PENALTY_PERCENT }}%). Potongan hasil ini: {{ $fmt($attempt->penalty_score) }} poin.</p>@endif
                @if($isManager)<div class="integrity-timeline">@forelse($securityEvents as $event)<div class="integrity-event {{ $event->isViolation() ? 'violation' : '' }}"><strong>{{ $event->label() }}</strong><span>{{ $event->occurred_at->translatedFormat('d M Y, H:i:s') }}@if($event->duration_seconds !== null) · durasi {{ $event->duration_seconds }} detik @endif</span></div>@empty<p class="text-xs text-emerald-700 m-0">Tidak ada perpindahan fokus yang tercatat.</p>@endforelse</div>@endif
            </div>
        @endif
    </header>

    @if($isManager)<form method="POST" action="{{ route('attempts.grade', $attempt) }}" class="space-y-4">@csrf @method('PUT')@else<div class="space-y-4">@endif
        @foreach($questions as $i => $question)
            @php
                $answer = $answers->get($question->id);
                $decoded = $question->decodeAnswer($answer?->answer);
            @endphp
            @if($question->group && ($i === 0 || $questions[$i - 1]->question_group_id !== $question->question_group_id))
                <section class="quiz-stimulus-card rounded-2xl border border-indigo-200 bg-indigo-50/70 p-5"><div class="text-xs font-bold uppercase tracking-wider text-indigo-600 mb-2">Stimulus TKA</div>@if($question->group->title)<h2 class="quiz-readable-text font-semibold mb-2">{{ $question->group->title }}</h2>@endif @if($question->group->stimulus)<div class="quiz-readable-text text-sm leading-7">{{ $question->group->stimulus }}</div>@endif @include('quizzes.partials.stimulus-media', ['group' => $question->group])</section>
            @endif
            <article class="quiz-question-card bg-white rounded-2xl border p-5 {{ $question->isObjective() ? ($answer?->is_correct ? 'border-emerald-300' : 'border-amber-300') : 'border-slate-200' }}">
                <div class="flex flex-wrap gap-2 text-xs text-slate-500 mb-3"><span>Soal {{ $i + 1 }}</span><span>·</span><span>{{ $question->typeLabel() }}</span><span>·</span><span>maks. {{ $question->points }} poin</span>@if($question->cognitiveLevelLabel())<span class="rounded-full bg-indigo-50 text-indigo-700 px-2">{{ $question->cognitiveLevelLabel() }}</span>@endif</div>
                <p class="quiz-readable-text mb-4">{{ $question->body }}</p>

                @if($question->isPilihanGanda() || $question->isPilihanGandaKompleks())
                    @php $selected = $question->isPilihanGanda() ? array_filter([$decoded]) : $decoded; $keys = $question->isPilihanGanda() ? [$question->correct_option] : ($question->correct_options ?? []); @endphp
                    <ul class="space-y-2 text-sm">@foreach($question->options() as $key => $text)@php $isChosen = in_array($key,$selected,true); $isKey = in_array($key,$keys,true); @endphp<li class="quiz-readable-text rounded-lg px-3 py-2 {{ $isKey ? 'bg-emerald-50 text-emerald-800 font-medium' : ($isChosen ? 'bg-red-50 text-red-800' : 'bg-slate-50 text-slate-600') }}"><b>{{ strtoupper($key) }}.</b> {{ $text }} @if($isChosen)<span class="text-xs">(jawaban {{ $isManager ? 'siswa' : 'kamu' }})</span>@endif @if($isKey)<span class="text-xs">✓ kunci</span>@endif</li>@endforeach</ul>
                    @if(empty($selected))<p class="text-xs text-slate-500 mt-2">Tidak dijawab.</p>@endif
                    <p class="text-sm mt-3 text-slate-600">Skor: {{ $fmt($answer?->score ?? 0) }} / {{ $question->points }}</p>
                @elseif($question->isKategori())
                    <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="bg-slate-50"><th class="text-left p-2">Pernyataan</th><th class="p-2">Jawaban</th><th class="p-2">Kunci</th></tr></thead><tbody>@foreach($question->category_items ?? [] as $itemIndex => $item)@php $chosen = $decoded[$itemIndex] ?? null; @endphp<tr class="border-t"><td class="p-2">{{ $item['statement'] }}</td><td class="p-2 text-center {{ $chosen === $item['answer'] ? 'text-emerald-700' : 'text-red-700' }}">{{ $chosen ? ucfirst($chosen) : '–' }}</td><td class="p-2 text-center font-medium">{{ ucfirst($item['answer']) }}</td></tr>@endforeach</tbody></table></div>
                    <p class="text-sm mt-3 text-slate-600">Skor proporsional: {{ $fmt($answer?->score ?? 0) }} / {{ $question->points }}</p>
                @else
                    <div class="rounded-lg bg-slate-50 p-3 text-sm whitespace-pre-line">{{ filled($decoded) ? $decoded : 'Tidak dijawab.' }}</div>
                    @if($isManager && $answer)<div class="mt-3 flex items-center gap-2 text-sm"><label>Nilai</label><input type="number" step="0.01" min="0" max="{{ $question->points }}" name="scores[{{ $answer->id }}]" value="{{ $answer->score !== null ? $fmt($answer->score) : '' }}" class="w-24 rounded-lg border border-slate-300 px-2 py-1.5"><span>/ {{ $question->points }}</span></div>@else<p class="text-sm mt-3">Skor: {{ $answer?->score !== null ? $fmt($answer->score).' / '.$question->points : 'menunggu penilaian guru' }}</p>@endif
                @endif
            </article>
        @endforeach
    @if($isManager)
        @if($questions->contains(fn ($q) => $q->type === \App\Models\Question::TYPE_ESAI))<div class="text-right"><button class="lms-button lms-button-primary">Simpan nilai esai</button></div>@endif
    </form>@else</div>@endif
</div>
@endsection
