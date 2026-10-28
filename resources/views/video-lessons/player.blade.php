<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Pemutar Video Interaktif</title>
<link rel="stylesheet" href="{{ asset('video-task/css/player.css') }}">
</head>
<body>
<main class="vt-wrap">
    <header class="vt-heading"><div><h1>🎬 {{ $videoLesson->title }}</h1><p>{{ $preview ? 'Pratinjau guru (tidak menyimpan jawaban)' : 'Tonton dan jawab setiap pertanyaan yang muncul' }}</p></div><span id="checkpoint-progress" class="vt-pill" aria-live="polite">{{ count($answeredIds) }}/{{ count($questions) }} checkpoint</span></header>
    <div class="watch-layout">
        <div class="watch-main">
            <div class="player-shell" id="player-shell">
                @if($videoLesson->source_type === 'youtube')
                    @if($youtubeId)<div id="youtube-player" class="video-player" aria-label="Pemutar video YouTube"></div>
                    @else <div class="player-unavailable">URL YouTube tidak valid.</div>@endif
                @else
                    <video id="mp4-player" class="video-player" playsinline controls preload="metadata" controlsList="nodownload"><source src="{{ $videoLesson->playback_url }}" type="video/mp4">Browser Anda tidak mendukung MP4.</video>
                @endif
                <div class="question-overlay hidden" id="question-overlay" role="dialog" aria-modal="true" aria-labelledby="question-title">
                    <div class="question-dialog">
                        <div class="question-top"><span class="mini-label">PERTANYAAN INTERAKTIF</span><span class="question-time" id="question-time">00:00</span></div>
                        <h2 id="question-title">Pertanyaan</h2>
                        <p id="question-instruction" class="muted question-instruction">Pilih jawaban yang benar.</p>
                        <form id="answer-form"><div id="choices" class="choice-list"></div><button id="submit-answer" class="btn btn-primary full" type="submit" disabled>Kirim jawaban →</button></form>
                        <div id="answer-feedback" class="feedback hidden" aria-live="polite"></div>
                        <button id="continue-button" class="btn btn-primary full hidden" type="button">▶ Lanjutkan video</button>
                    </div>
                </div>
            </div>
            <div class="player-underbar"><span id="player-status">▶ Tekan Play untuk mulai</span><span>⏸ Video berhenti otomatis di checkpoint</span></div>
            <div id="player-error" class="alert alert-error hidden" role="alert"></div>
            <div id="finish-banner" class="card finish-banner {{ $attempt?->completed_at ? '' : 'hidden' }}"><div><strong>✓ Semua pertanyaan selesai</strong><p class="muted">{{ $preview ? 'Pratinjau guru selesai.' : 'Nilai tersimpan otomatis di tab Nilai.' }}</p></div></div>
        </div>
        <aside class="card watch-sidebar"><span class="eyebrow">CHECKPOINT BELAJAR</span><h2>Urutan pertanyaan</h2><div class="checkpoints">
            @foreach($videoLesson->checkpoints as $checkpoint)
            <div class="checkpoint" data-checkpoint="{{ $checkpoint->id }}"><div class="checkpoint-icon {{ in_array($checkpoint->id, $answeredIds) ? 'checkpoint-done' : '' }}">{{ in_array($checkpoint->id, $answeredIds) ? '✓' : $loop->iteration }}</div><div><strong>{{ sprintf('%02d:%02d', intdiv($checkpoint->time_seconds, 60), $checkpoint->time_seconds % 60) }}</strong><span>{{ in_array($checkpoint->id, $answeredIds) ? 'Sudah dijawab' : 'Menunggu diputar' }}</span></div></div>
            @endforeach
            </div><div class="sidebar-tip"><strong>Petunjuk</strong><p>Tonton video. Saat berhenti, jawab satu pertanyaan untuk melanjutkan.</p></div>
        </aside>
    </div>
</main>
<script>
window.VideoTaskLesson = {{ \Illuminate\Support\Js::from([
    'lessonId' => $videoLesson->id,
    'sourceType' => $videoLesson->source_type,
    'youtubeId' => $youtubeId,
    'questions' => $questions,
    'answeredIds' => $answeredIds,
    'completed' => (bool) $attempt?->completed_at,
    'answerUrl' => route('video-lessons.answer', $videoLesson),
    'embed' => true,
    'preview' => $preview,
    'parentOrigin' => $parentOrigin,
    'csrf' => true,
]) }};
</script>
<script defer src="{{ asset('video-task/js/player.js') }}"></script>
@if($videoLesson->source_type === 'youtube' && $youtubeId)
<script src="https://www.youtube.com/iframe_api"></script>
@endif
</body>
</html>
