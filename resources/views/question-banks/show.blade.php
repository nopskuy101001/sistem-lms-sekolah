@extends('layouts.app')
@section('title', $questionBank->title)

@php
    $quizMeta = $payload['quiz'] ?? [];
    $typeLabels = [
        'pilihan_ganda' => 'Pilihan Ganda',
        'pilihan_ganda_kompleks' => 'PG Kompleks · Multi Jawaban',
        'kategori' => 'PG Kompleks · Kategori',
        'esai' => 'Esai',
    ];
    $cognitiveLabels = [
        'pemahaman' => 'Pemahaman',
        'penerapan' => 'Penerapan',
        'penalaran' => 'Penalaran',
    ];
    $lastGroupKey = null;
@endphp

@section('content')
<div class="mb-5 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <a href="{{ route('question-banks.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">← Kembali ke Bank Soal</a>
        <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-indigo-600">Pratinjau Paket</p>
        <h1 class="quiz-readable-text mt-1 text-2xl font-bold text-slate-900">{{ $questionBank->title }}</h1>
        <p class="mt-2 text-sm text-slate-500">Sumber: {{ $questionBank->source_classroom ?: 'Kelas tidak dicatat' }} · disimpan {{ $questionBank->created_at->translatedFormat('d M Y, H:i') }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <span class="rounded-full bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700">{{ $questionBank->question_count }} soal · {{ $questionBank->total_points }} poin</span>
        <a href="{{ route('question-banks.excel', $questionBank) }}" class="lms-button border border-emerald-200 bg-white text-emerald-800 hover:bg-emerald-50">Unduh Excel</a>
        <a href="#bank-use-package" class="lms-button lms-button-primary">Gunakan paket ini</a>
    </div>
</div>

@if($groups->contains(fn ($group) => filled($group['media_path'] ?? null)))
    <p class="mb-5 text-xs text-amber-700">File Excel memuat soal, teks, dan tabel stimulus. Simpan gambar stimulus secara terpisah jika paket ini akan dipakai di luar sistem.</p>
@endif

<div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
    <main class="min-w-0 space-y-4">
        @if($questionBank->description)
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-slate-900">Deskripsi paket</h2>
                <p class="quiz-readable-text mt-2 text-sm leading-7 text-slate-600">{{ $questionBank->description }}</p>
            </section>
        @endif

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="bank-questions" class="bank-anchor text-lg font-bold text-slate-900" tabindex="-1">Isi soal</h2>
                <p class="mt-1 text-xs text-slate-500">Stimulus, kunci jawaban, dan poin ditampilkan untuk pemeriksaan guru.</p>
            </div>
            <span class="text-xs font-semibold text-slate-500">{{ $groups->count() }} stimulus</span>
        </div>

        @forelse($questions as $question)
            @php
                $groupKey = $question['group_key'] ?? null;
                $group = $groupKey ? $groups->get($groupKey) : null;
                $type = $question['type'] ?? 'esai';
                $options = collect([
                    'a' => $question['option_a'] ?? null,
                    'b' => $question['option_b'] ?? null,
                    'c' => $question['option_c'] ?? null,
                    'd' => $question['option_d'] ?? null,
                ])->filter(fn ($value) => filled($value));
                $correctKeys = $type === 'pilihan_ganda'
                    ? array_filter([$question['correct_option'] ?? null])
                    : ($question['correct_options'] ?? []);
            @endphp

            @if($group && $groupKey !== $lastGroupKey)
                @php $tableRows = collect($group['table_data'] ?? []); @endphp
                <section class="quiz-stimulus-card rounded-2xl border border-indigo-200 bg-indigo-50/70 p-5">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wider text-indigo-600">Stimulus</div>
                    @if(filled($group['title'] ?? null))
                        <h3 class="quiz-readable-text font-semibold text-slate-900">{{ $group['title'] }}</h3>
                    @endif
                    @if(filled($group['stimulus'] ?? null))
                        <div class="quiz-readable-text mt-2 text-sm leading-7 text-slate-700">{{ $group['stimulus'] }}</div>
                    @endif
                    @if(filled($group['media_path'] ?? null))
                        <figure class="quiz-stimulus-media mt-4">
                            <img src="{{ route('question-banks.media', ['questionBank' => $questionBank, 'groupKey' => $groupKey]) }}"
                                 alt="{{ $group['media_alt'] ?? ($group['title'] ?? 'Media stimulus') }}"
                                 loading="lazy"
                                 class="max-h-[32rem] rounded-xl border border-slate-200 bg-white">
                            @if(filled($group['media_alt'] ?? null))
                                <figcaption class="quiz-readable-text mt-1.5 text-xs text-slate-500">{{ $group['media_alt'] }}</figcaption>
                            @endif
                        </figure>
                    @endif
                    @if($tableRows->isNotEmpty())
                        <div class="quiz-stimulus-table mt-4 rounded-xl border border-slate-200 bg-white" role="region" aria-label="Tabel stimulus" tabindex="0">
                            <table class="text-sm">
                                <thead class="bg-slate-100 text-slate-700">
                                    <tr>
                                        @foreach($tableRows->first() as $cell)
                                            <th class="border-r border-slate-200 px-3 py-2 text-left font-semibold last:border-r-0">{{ $cell }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tableRows->slice(1) as $row)
                                        <tr class="border-t border-slate-200 odd:bg-white even:bg-slate-50/70">
                                            @foreach($row as $cell)
                                                <td class="border-r border-slate-200 px-3 py-2 text-slate-700 last:border-r-0">{{ $cell }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif

            <article class="quiz-question-card rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-indigo-50 px-2.5 py-1 font-semibold text-indigo-700">Soal {{ $loop->iteration }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ $typeLabels[$type] ?? 'Esai' }}</span>
                        @if(filled($question['cognitive_level'] ?? null))
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-700">{{ $cognitiveLabels[$question['cognitive_level']] ?? ucfirst($question['cognitive_level']) }}</span>
                        @endif
                    </div>
                    <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ $question['points'] ?? 0 }} poin</span>
                </div>

                <p class="quiz-readable-text mt-4 text-sm font-medium leading-7 text-slate-800">{{ $question['body'] ?? 'Isi soal tidak tersedia.' }}</p>

                @if(filled($question['competency'] ?? null) || filled($question['subcompetency'] ?? null))
                    <div class="mt-2 space-y-1 text-xs text-slate-500">
                        @if(filled($question['competency'] ?? null))<p>Kompetensi: <span class="quiz-readable-text">{{ $question['competency'] }}</span></p>@endif
                        @if(filled($question['subcompetency'] ?? null))<p>Subkompetensi: <span class="quiz-readable-text">{{ $question['subcompetency'] }}</span></p>@endif
                    </div>
                @endif

                @if(in_array($type, ['pilihan_ganda', 'pilihan_ganda_kompleks'], true))
                    <ul class="mt-4 grid gap-2 text-sm">
                        @foreach($options as $key => $text)
                            @php $isCorrect = in_array($key, $correctKeys, true); @endphp
                            <li class="bank-answer rounded-xl border px-3 py-2.5 {{ $isCorrect ? 'border-emerald-200 bg-emerald-50 font-medium text-emerald-800' : 'border-slate-100 bg-slate-50 text-slate-600' }}">
                                <b class="bank-answer-letter">{{ strtoupper($key) }}.</b>
                                <div class="min-w-0">
                                    <span class="quiz-readable-text block">{{ $text }}</span>
                                    @if($isCorrect)<span class="mt-1 block text-xs">✓ kunci</span>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @elseif($type === 'kategori')
                    <div class="mt-4 grid gap-2 text-sm">
                        @foreach($question['category_items'] ?? [] as $item)
                            <div class="flex min-w-0 flex-col items-start gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5 text-slate-700">
                                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">{{ ucfirst($item['answer'] ?? '-') }}</span>
                                <span class="quiz-readable-text">{{ $item['statement'] ?? '' }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-3 text-xs text-slate-500">Jawaban esai akan dinilai oleh guru.</div>
                @endif
            </article>

            @php $lastGroupKey = $groupKey; @endphp
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Paket ini belum memiliki data soal.</div>
        @endforelse
    </main>

    <aside class="min-w-0 space-y-5 xl:self-start">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold text-slate-900">Pengaturan tersimpan</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Durasi</dt><dd class="text-right font-medium">{{ filled($quizMeta['duration_minutes'] ?? null) ? $quizMeta['duration_minutes'].' menit' : 'Tanpa batas' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Acak soal</dt><dd class="font-medium">{{ ($quizMeta['shuffle_questions'] ?? false) ? 'Aktif' : 'Nonaktif' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Pantau fokus</dt><dd class="font-medium">{{ ($quizMeta['monitoring_enabled'] ?? false) ? 'Aktif' : 'Nonaktif' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Layar penuh</dt><dd class="font-medium">{{ ($quizMeta['require_fullscreen'] ?? false) ? 'Wajib' : 'Tidak wajib' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Penalti</dt><dd class="font-medium">{{ ($quizMeta['penalty_enabled'] ?? false) ? ($quizMeta['penalty_percent_per_violation'] ?? 0).'% / peringatan' : 'Nonaktif' }}</dd></div>
            </dl>
        </section>

        <form id="bank-use-package" tabindex="-1" method="POST" action="{{ route('question-banks.quizzes.store', $questionBank) }}" class="bank-anchor space-y-4 rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
            @csrf
            <div>
                <h2 class="font-semibold text-indigo-950">Gunakan paket ini</h2>
                <p class="mt-1 text-xs leading-5 text-indigo-700">Kuis baru dibuat sebagai draft dan dapat diperiksa kembali sebelum dipublikasikan.</p>
            </div>
            <label class="block text-xs font-semibold text-slate-700">Kelas tujuan
                <select name="classroom_id" required class="mt-1 w-full rounded-lg border border-indigo-200 bg-white px-3 py-2.5 text-sm">
                    <option value="">Pilih kelas</option>
                    @foreach($classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}@if($classroom->section) · {{ $classroom->section }}@endif</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs font-semibold text-slate-700">Judul kuis baru
                <input name="title" value="{{ $quizMeta['title'] ?? $questionBank->title }}" maxlength="200" class="mt-1 w-full rounded-lg border border-indigo-200 bg-white px-3 py-2.5 text-sm">
            </label>
            <button @disabled($classrooms->isEmpty()) class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">Buat kuis draft</button>
            <a href="#bank-questions" class="block py-2 text-center text-sm font-semibold text-indigo-700 hover:underline">Kembali ke isi soal</a>
        </form>
    </aside>
</div>
@endsection
