@extends('layouts.app')
@section('title', 'Daftar')

@section('content')
<div class="max-w-md mx-auto mt-10 bg-white rounded-xl border border-slate-200 p-8">
    <h1 class="text-2xl font-semibold mb-1">Daftar Akun Siswa</h1>
    <p class="text-sm text-slate-500 mb-6">Akun guru dibuat oleh admin sekolah.</p>

    <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nama lengkap</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">NIS (opsional)</label>
            <input type="text" name="nomor_induk" value="{{ old('nomor_induk') }}" class="w-full rounded-lg border-slate-300 border px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Password (min. 8 karakter)</label>
            <input type="password" name="password" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Ulangi password</label>
            <input type="password" name="password_confirmation" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
        </div>
        <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-2 font-medium">Daftar</button>
    </form>

    <p class="text-sm text-slate-500 mt-6 text-center">
        Sudah punya akun? <a href="{{ route('login') }}" class="text-indigo-700 hover:underline">Masuk</a>
    </p>
</div>
@endsection
