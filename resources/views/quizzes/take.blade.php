@extends('layouts.app')
@section('title', $quiz->title)

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="sticky top-0 z-10 mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50/95 py-3 backdrop-blur">
        <h1 class="min-w-0 break-words text-xl font-semibold">{{ $quiz->title }}</h1>
        @if($deadline)
            <div class="text-sm">
                Sisa waktu: <span id="countdown" class="font-mono font-semibold text-red-600">--:--</span>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('attempts.submit', $attempt) }}" id="quiz-form" class="space-y-4">
        @csrf

        @foreach($quiz->questions as $i => $question)
            @if($question->group && ($i === 0 || $quiz->questions[$i - 1]->question_group_id !== $question->question_group_id))
                <section class="quiz-stimulus-card rounded-2xl border border-indigo-200 bg-indigo-50/70 p-4 sm:p-5">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-indigo-600">Stimulus TKA</div>
                    @if($question->group->title)<h2 class="quiz-readable-text mb-2 font-semibold">{{ $question->group->title }}</h2>@endif
                    @if($question->group->stimulus)<div class="quiz-readable-text text-sm leading-7 text-slate-700">{{ $question->group->stimulus }}</div>@endif
                    @include('quizzes.partials.stimulus-media', ['group' => $question->group])
                </section>
            @endif
            <div class="quiz-question-card rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <div class="text-xs text-slate-500 mb-1">Soal {{ $i + 1 }} · {{ $question->points }} poin</div>
                <p class="quiz-readable-text mb-3">{{ $question->body }}</p>

                @if($question->isPilihanGanda())
                    <div class="space-y-2">
                        @foreach($question->options() as $key => $text)
                            <label class="flex items-start gap-2 text-sm cursor-pointer rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50">
                                <input type="radio" name="answers[{{ $question->id }}]" value="{{ $key }}" class="mt-1">
                                <span class="quiz-readable-text min-w-0"><b>{{ strtoupper($key) }}.</b> {{ $text }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <textarea name="answers[{{ $question->id }}]" rows="5" placeholder="Tulis jawaban kamu..."
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                @endif
            </div>
        @endforeach

        <div class="text-right">
            <button class="w-full rounded-lg bg-indigo-600 px-6 py-2.5 font-medium text-white hover:bg-indigo-700 sm:w-auto"
                    onclick="return confirm('Kumpulkan jawaban sekarang? Jawaban tidak bisa diubah lagi.')">
                Kumpulkan jawaban
            </button>
        </div>
    </form>
</div>

@if($deadline)
<script>
    // Hitung mundur berdasarkan waktu server agar tidak bisa dicurangi lewat jam perangkat.
    (function () {
        var remaining = {{ (int) max(0, floor(now()->diffInSeconds($deadline, false))) }};
        var el = document.getElementById('countdown');
        var form = document.getElementById('quiz-form');

        function tick() {
            if (remaining <= 0) {
                el.textContent = '00:00';
                form.submit(); // waktu habis: kumpulkan otomatis
                return;
            }
            var h = Math.floor(remaining / 3600);
            var m = Math.floor((remaining % 3600) / 60);
            var s = remaining % 60;
            el.textContent = (h > 0 ? String(h).padStart(2, '0') + ':' : '')
                + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            remaining--;
            setTimeout(tick, 1000);
        }
        tick();
    })();
</script>
@endif
@endsection
