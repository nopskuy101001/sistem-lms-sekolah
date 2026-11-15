@extends('layouts.app')
@section('title', 'Koreksi Esai · '.$quiz->title)
@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); @endphp

@section('content')
<div class="essay-grading-page">
    <div class="essay-grading-back"><a href="{{ route('quizzes.show', $quiz) }}">← Kembali ke {{ $quiz->title }}</a></div>

    <header class="essay-grading-head">
        <div>
            <span class="essay-grading-kicker">Mode koreksi esai</span>
            <h1>{{ $quiz->title }}</h1>
            <p>Nilai jawaban siswa tanpa kembali ke halaman hasil atau melakukan scroll panjang.</p>
        </div>
        <div class="essay-grading-progress" aria-label="Progres penilaian">
            <span><strong>{{ $gradedCount }}</strong> / {{ $attempts->count() }} siswa dinilai</span>
            <div><i style="width: {{ $attempts->isNotEmpty() ? round(($gradedCount / $attempts->count()) * 100) : 0 }}%"></i></div>
        </div>
    </header>

    <div class="essay-grading-workspace">
        <aside class="essay-student-panel">
            <div class="essay-student-panel-head">
                <div><strong>Daftar siswa</strong><span>{{ $pendingCount }} belum dinilai</span></div>
                <div class="essay-status-filter" aria-label="Filter status penilaian">
                    <a href="{{ route('quizzes.essay-grading', ['quiz' => $quiz, 'status' => 'pending']) }}" class="{{ $statusFilter === 'pending' ? 'active' : '' }}">Belum</a>
                    <a href="{{ route('quizzes.essay-grading', ['quiz' => $quiz, 'status' => 'graded']) }}" class="{{ $statusFilter === 'graded' ? 'active' : '' }}">Selesai</a>
                    <a href="{{ route('quizzes.essay-grading', ['quiz' => $quiz, 'status' => 'all']) }}" class="{{ $statusFilter === 'all' ? 'active' : '' }}">Semua</a>
                </div>
            </div>
            <nav class="essay-student-list" aria-label="Pilih siswa untuk dinilai">
                @forelse($listedAttempts as $studentAttempt)
                    <a href="{{ route('quizzes.essay-grading', ['quiz' => $quiz, 'attempt' => $studentAttempt, 'status' => $statusFilter]) }}" class="essay-student-item {{ $selectedAttempt?->id === $studentAttempt->id ? 'active' : '' }}">
                        <x-user-avatar :user="$studentAttempt->user" class="essay-student-avatar" />
                        <span><strong>{{ $studentAttempt->user->name }}</strong><small>{{ $studentAttempt->pending_essay_count > 0 ? $studentAttempt->pending_essay_count.' jawaban belum dinilai' : 'Penilaian selesai' }}</small></span>
                        <i class="{{ $studentAttempt->pending_essay_count > 0 ? 'pending' : 'graded' }}" aria-hidden="true"></i>
                    </a>
                @empty
                    <div class="essay-student-empty">Tidak ada siswa pada filter ini.</div>
                @endforelse
            </nav>
        </aside>

        <main class="essay-answer-panel">
            @if($selectedAttempt)
                <div class="essay-answer-head">
                    <div>
                        <span>Jawaban siswa</span>
                        <h2>{{ $selectedAttempt->user->name }}</h2>
                        <p>Dikumpulkan {{ $selectedAttempt->finished_at->translatedFormat('d M Y, H:i') }}</p>
                    </div>
                    <div class="essay-current-score"><strong>{{ $fmt($selectedAttempt->score) }}</strong><span>/ {{ $fmt($quiz->totalPoints()) }} poin saat ini</span></div>
                </div>

                <form method="POST" action="{{ route('attempts.grade', $selectedAttempt) }}" class="essay-review-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="grading_mode" value="1">

                    <div class="essay-answer-list">
                        @foreach($essayQuestions as $index => $question)
                            @php $answer = $answers->get($question->id); @endphp
                            @if($question->group && ($index === 0 || $essayQuestions[$index - 1]->question_group_id !== $question->question_group_id))
                                <section class="essay-stimulus-card">
                                    <span>Stimulus</span>
                                    @if($question->group->title)<h3>{{ $question->group->title }}</h3>@endif
                                    @if($question->group->stimulus)<div class="quiz-readable-text">{{ $question->group->stimulus }}</div>@endif
                                    @include('quizzes.partials.stimulus-media', ['group' => $question->group])
                                </section>
                            @endif

                            <article class="essay-review-card">
                                <div class="essay-question-meta"><span>Esai {{ $index + 1 }}</span><span>Maks. {{ $fmt($question->points) }} poin</span></div>
                                <h3 class="quiz-readable-text">{{ $question->body }}</h3>
                                <div class="essay-student-answer">
                                    <span>Jawaban siswa</span>
                                    <p>{{ filled($answer?->answer) ? $answer->answer : 'Tidak dijawab.' }}</p>
                                </div>
                                @if($answer)
                                    <label class="essay-score-field">
                                        <span>Nilai jawaban</span>
                                        <span><input type="number" name="scores[{{ $answer->id }}]" value="{{ old('scores.'.$answer->id, $answer->score !== null ? $fmt($answer->score) : '') }}" min="0" max="{{ $question->points }}" step="0.01" required inputmode="decimal"><b>/ {{ $fmt($question->points) }}</b></span>
                                    </label>
                                @else
                                    <p class="essay-answer-warning">Data jawaban untuk soal ini belum tersedia.</p>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    <div class="essay-grading-actions">
                        <span>Pastikan seluruh nilai esai telah diisi.</span>
                        <div>
                            <button type="submit" name="navigation" value="stay" class="lms-button essay-save-button">Simpan</button>
                            <button type="submit" name="navigation" value="next" class="lms-button lms-button-primary">Simpan & siswa berikutnya <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
                        </div>
                    </div>
                </form>
            @else
                <div class="essay-no-selection">
                    <span aria-hidden="true"><svg viewBox="0 0 24 24" width="31" height="31" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
                    <h2>{{ $attempts->isEmpty() ? 'Belum ada jawaban' : 'Semua siswa pada filter ini sudah diperiksa' }}</h2>
                    <p>{{ $attempts->isEmpty() ? 'Ruang koreksi akan terisi setelah siswa mengumpulkan kuis.' : 'Pilih filter Semua untuk melihat atau mengoreksi nilai yang sudah disimpan.' }}</p>
                    @if($attempts->isNotEmpty())<a href="{{ route('quizzes.essay-grading', ['quiz' => $quiz, 'status' => 'all']) }}" class="lms-button lms-button-primary">Lihat semua penilaian</a>@endif
                </div>
            @endif
        </main>
    </div>
</div>
@endsection
