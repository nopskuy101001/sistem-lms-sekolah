@extends('layouts.app')
@section('title', 'Pendaftaran Ditutup')

@section('content')
<div class="max-w-md mx-auto mt-10 bg-white rounded-xl border border-slate-200 p-8">
    <h1 class="text-2xl font-semibold mb-1">Pendaftaran mandiri ditutup</h1>
    <p class="text-sm text-slate-500 mb-6">Hubungi administrator sekolah untuk memperoleh akun siswa.</p>
    <a href="{{ route('login') }}" class="lms-button lms-button-primary w-full">Kembali ke halaman masuk</a>
</div>
@endsection
