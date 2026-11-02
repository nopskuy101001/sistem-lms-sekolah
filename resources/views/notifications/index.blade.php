@extends('layouts.app')
@section('title', 'Notifikasi')

@section('content')
<div class="lms-page-heading">
    <div>
        <p class="lms-eyebrow">Pusat informasi</p>
        <h1 class="lms-page-title">Notifikasi</h1>
        <p class="lms-page-description">Pembaruan kelas, aktivitas siswa, dan informasi nilai tersimpan di sini.</p>
    </div>
    @if(auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="lms-button lms-button-primary" type="submit">Tandai semua dibaca</button>
        </form>
    @endif
</div>

<section class="lms-notification-page" aria-label="Riwayat notifikasi">
    @forelse($notifications as $notification)
        <x-notification-entry :notification="$notification" />
    @empty
        <div class="lms-empty-state">
            <div class="lms-empty-icon">
                <svg viewBox="0 0 24 24" width="27" height="27" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
            </div>
            <h3>Belum ada notifikasi</h3>
            <p>Pembaruan kelas dan aktivitas akademik akan muncul di halaman ini.</p>
        </div>
    @endforelse
</section>

@if($notifications->hasPages())
    <div class="mt-5">{{ $notifications->links() }}</div>
@endif
@endsection
