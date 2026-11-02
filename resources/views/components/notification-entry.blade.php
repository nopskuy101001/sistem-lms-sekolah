@props(['notification', 'compact' => false])

@php
    $data = $notification->data;
    $category = in_array($data['category'] ?? '', ['announcement', 'material', 'assignment', 'quiz', 'video', 'classroom', 'submission', 'grade', 'transcript'], true)
        ? $data['category']
        : 'general';
@endphp

<a href="{{ route('notifications.open', $notification->id) }}"
   class="lms-notification-entry {{ $notification->read_at ? '' : 'is-unread' }} {{ $compact ? 'is-compact' : '' }}">
    <span class="lms-notification-entry-icon" data-category="{{ $category }}" aria-hidden="true">
        @switch($category)
            @case('announcement')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11v2a2 2 0 0 0 2 2h2l4 4V5L7 9H5a2 2 0 0 0-2 2Z"/><path d="M16 9a4 4 0 0 1 0 6"/></svg>
                @break
            @case('material')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg>
                @break
            @case('assignment')
            @case('quiz')
            @case('video')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                @break
            @case('classroom')
            @case('submission')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M16 11h6"/></svg>
                @break
            @case('grade')
            @case('transcript')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 3h16v18H4z"/><path d="M8 7h8M8 11h5"/><path d="m13 16 2 2 4-4"/></svg>
                @break
            @default
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
        @endswitch
    </span>
    <span class="lms-notification-entry-copy">
        <strong>{{ $data['title'] ?? 'Notifikasi' }}</strong>
        <span>{{ $data['message'] ?? 'Ada pembaruan baru untuk akunmu.' }}</span>
        <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->diffForHumans() }}</time>
    </span>
    @unless($notification->read_at)<span class="lms-notification-unread" aria-label="Belum dibaca"></span>@endunless
</a>
