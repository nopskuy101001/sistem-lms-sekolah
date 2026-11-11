@php
    $compact = $compact ?? false;
    $tableRows = collect($group->table_data ?? []);
@endphp

@if($group->media_path)
    <figure class="quiz-stimulus-media {{ $compact ? 'mt-3' : 'mt-4' }}">
        <img
            src="{{ route('question-groups.media', $group) }}"
            alt="{{ $group->media_alt ?: ($group->title ? 'Media stimulus '.$group->title : 'Media stimulus TKA') }}"
            loading="lazy"
            class="{{ $compact ? 'max-h-52' : 'max-h-[32rem]' }} w-auto max-w-full rounded-xl border border-slate-200 bg-white object-contain"
        >
        @if($group->media_alt)
            <figcaption class="quiz-readable-text mt-1.5 text-xs text-slate-500">{{ $group->media_alt }}</figcaption>
        @endif
    </figure>
@endif

@if($group->hasVideo())
    @php $youtubeId = $group->youtubeVideoId(); @endphp
    @if($compact)
        <div class="mt-3 inline-flex items-center gap-2 rounded-lg bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-700">
            <span aria-hidden="true">&#9654;</span> Video stimulus tersedia
        </div>
    @else
        <figure class="quiz-stimulus-video mt-4">
            <div class="quiz-stimulus-video-frame overflow-hidden rounded-xl border border-slate-200 bg-black">
                @if($youtubeId)
                    <iframe src="https://www.youtube-nocookie.com/embed/{{ $youtubeId }}"
                            title="{{ $group->video_caption ?: ($group->title ? 'Video stimulus '.$group->title : 'Video stimulus TKA') }}"
                            loading="lazy" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                @else
                    <video controls preload="metadata" playsinline
                           src="{{ $group->video_type === 'upload' ? route('question-groups.video', $group) : $group->video_url }}">
                        Browser Anda tidak mendukung pemutaran video.
                    </video>
                @endif
            </div>
            @if($group->video_caption)
                <figcaption class="quiz-readable-text mt-1.5 text-xs text-slate-500">{{ $group->video_caption }}</figcaption>
            @endif
        </figure>
    @endif
@endif

@if($tableRows->isNotEmpty())
    <div class="quiz-stimulus-table {{ $compact ? 'mt-3' : 'mt-4' }} rounded-xl border border-slate-200 bg-white" role="region" aria-label="Tabel stimulus" tabindex="0">
        <table class="min-w-full text-sm">
            <caption class="sr-only">Tabel stimulus {{ $group->title ?: 'TKA' }}</caption>
            <thead class="bg-slate-100 text-slate-700">
                <tr>
                    @foreach($tableRows->first() as $cell)
                        <th scope="col" class="border-r border-slate-200 px-3 py-2 text-left font-semibold last:border-r-0">{{ $cell }}</th>
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
