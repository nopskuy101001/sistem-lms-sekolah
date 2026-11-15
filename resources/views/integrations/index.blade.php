@extends('layouts.app')
@section('title', 'Integrasi Guru')

@section('content')
<section class="lms-integration-hero">
    <span class="lms-integration-hero-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22v-5"/><path d="M9 8V2"/><path d="M15 8V2"/><path d="M18 8v3a6 6 0 0 1-12 0V8Z"/></svg>
    </span>
    <div>
        <span class="lms-integration-kicker">Khusus guru</span>
        <h1>Pusat Integrasi Guru</h1>
        <p>Akses layanan sekolah pendukung dari satu tempat. Daftar ini dapat dikembangkan saat ada layanan baru.</p>
    </div>
</section>

<div class="lms-integration-heading">
    <div>
        <h2>Layanan tersedia</h2>
        <p>Pilih layanan yang ingin dibuka.</p>
    </div>
    <span>{{ count(config('integrations.teacher', [])) }} layanan</span>
</div>

<section class="lms-integration-grid" aria-label="Daftar integrasi guru">
    @forelse(config('integrations.teacher', []) as $key => $integration)
        <article class="lms-integration-card" data-integration="{{ $key }}" data-color="{{ $integration['color'] ?? 'indigo' }}">
            <div class="lms-integration-card-top">
                <span class="lms-integration-logo" aria-hidden="true">
                    @if($key === 'doku')
                        <svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/><path d="M9 7h7M9 11h7"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22v-5"/><path d="M9 8V2M15 8V2"/><path d="M18 8v3a6 6 0 0 1-12 0V8Z"/></svg>
                    @endif
                </span>
                <span class="lms-integration-status"><span aria-hidden="true"></span>Layanan eksternal</span>
            </div>
            <div class="lms-integration-card-copy">
                <h3>{{ $integration['name'] }}</h3>
                <strong>{{ $integration['subtitle'] }}</strong>
                <p>{{ $integration['description'] }}</p>
            </div>
            <a href="{{ $integration['url'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="strict-origin-when-cross-origin" class="lms-button lms-button-primary lms-integration-open">
                Buka {{ $integration['name'] }}
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
            </a>
        </article>
    @empty
        <div class="lms-empty-state">
            <h3>Belum ada integrasi</h3>
            <p>Layanan guru yang terhubung akan ditampilkan di sini.</p>
        </div>
    @endforelse
</section>

<p class="lms-integration-note">
    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
    Layanan akan terbuka di tab baru dan mungkin meminta Anda masuk menggunakan akun guru pada layanan tersebut.
</p>
@endsection
