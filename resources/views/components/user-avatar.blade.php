@props(['user', 'class' => 'lms-avatar'])

<span {{ $attributes->merge(['class' => $class]) }} aria-hidden="true">
    @if($user->profile_photo_path)
        <img src="{{ asset('storage/'.$user->profile_photo_path) }}?v={{ $user->updated_at?->timestamp }}" alt="" loading="lazy" style="{{ $user->profilePhotoStyle() }}">
    @else
        {{ mb_strtoupper(mb_substr(trim($user->name), 0, 1)) }}
    @endif
</span>
