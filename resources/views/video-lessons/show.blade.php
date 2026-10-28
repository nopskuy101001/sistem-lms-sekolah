@extends('layouts.app')
@section('title', 'Video Interaktif - '.$videoLesson->title)
@section('content')
<div class="mb-5 flex items-start justify-between gap-3 flex-wrap">
    <div>
        <a href="{{ route('classrooms.classwork', $videoLesson->classroom) }}" class="text-sm font-medium text-indigo-700 hover:underline">← Tugas & Materi · {{ $videoLesson->classroom->name }}</a>
        <h1 class="text-2xl font-bold text-slate-900 mt-2">{{ $videoLesson->title }}</h1>
        @if($videoLesson->description)<p class="mt-2 text-sm text-slate-600">{{ $videoLesson->description }}</p>@endif
    </div>
    @if($isManager)<a href="{{ route('video-lessons.edit', $videoLesson) }}" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm text-white font-semibold">Kelola video & soal</a>@endif
</div>
@if($isManager)<div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900">Mode pratinjau guru: jawaban tidak disimpan dan tidak memengaruhi nilai siswa.</div>@endif
<div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
    <iframe id="video-task-frame" src="{{ route('video-lessons.player', $videoLesson) }}" title="Video Interaktif: {{ $videoLesson->title }}" class="video-task-frame" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" referrerpolicy="strict-origin-when-cross-origin" loading="eager"></iframe>
</div>
<p id="video-task-status" class="mt-4 text-sm text-slate-600">Video akan berhenti pada setiap checkpoint. Jawab untuk melanjutkan.</p>
@if($attempt?->completed_at)<div class="mt-4 rounded-xl bg-emerald-50 p-4 text-emerald-800 font-semibold">✓ Semua soal sudah dijawab. Nilai: {{ number_format((float) $attempt->score, 2) }}/100.</div>@endif
<script>
(() => {
    const iframe = document.getElementById('video-task-frame');
    const status = document.getElementById('video-task-status');
    let frameObserver;
    let resizeTimer;

    const resizeFrame = () => {
        try {
            const documentHeight = Math.max(
                iframe.contentDocument.documentElement.scrollHeight,
                iframe.contentDocument.body.scrollHeight
            );
            const minimum = window.innerWidth <= 620 ? 420 : 480;
            const nextHeight = Math.max(minimum, Math.min(documentHeight + 4, 900));

            if (Math.abs(iframe.getBoundingClientRect().height - nextHeight) > 1) {
                iframe.style.height = `${nextHeight}px`;
            }
        } catch (_) {}
    };

    iframe.addEventListener('load', () => {
        resizeFrame();
        frameObserver?.disconnect();
        if ('ResizeObserver' in window && iframe.contentDocument?.body) {
            frameObserver = new ResizeObserver(resizeFrame);
            frameObserver.observe(iframe.contentDocument.body);
        }
    });
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(resizeFrame, 120);
    });

    window.addEventListener('message', event => {
        if (event.origin !== window.location.origin || event.source !== iframe.contentWindow) return;
        const detail = event.data;
        if (!detail || typeof detail.type !== 'string' || detail.lessonId !== {{ (int) $videoLesson->id }}) return;
        if (detail.type === 'videotask:checkpoint') status.textContent = 'Video dijeda: jawablah pertanyaan yang muncul di pemutar.';
        if (detail.type === 'videotask:answered') status.textContent = `${detail.answeredCount}/${detail.totalCount} pertanyaan dijawab.`;
        if (detail.type === 'videotask:completed') {
            status.textContent = @json($isManager)
                ? '✓ Pratinjau seluruh checkpoint selesai.'
                : `✓ Semua checkpoint selesai, nilai ${detail.percentage}/100.`;
            status.classList.add('text-emerald-700', 'font-semibold');
        }
        resizeFrame();
    });
})();
</script>
@endsection
