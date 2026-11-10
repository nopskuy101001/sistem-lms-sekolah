@props(['wrapperClass' => ''])

<span class="lms-password-field {{ $wrapperClass }}">
    <input type="password" {{ $attributes }}>
    <button type="button" class="lms-password-toggle" data-password-toggle
        aria-controls="{{ $attributes->get('id') }}" aria-label="Tampilkan password" title="Tampilkan password" aria-pressed="false">
        <svg data-password-icon="show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" hidden>
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
            <circle cx="12" cy="12" r="2.8"/>
        </svg>
        <svg data-password-icon="hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="m3 3 18 18"/>
            <path d="M10.6 6.1A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a15.7 15.7 0 0 1-2.1 2.8M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6a9.8 9.8 0 0 0 3.1-.5"/>
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
        </svg>
    </button>
</span>
