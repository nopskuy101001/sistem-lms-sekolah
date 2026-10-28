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
