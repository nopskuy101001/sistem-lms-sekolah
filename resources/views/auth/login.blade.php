@extends('layouts.app')
@section('title', 'Masuk')

@section('content')
<div class="max-w-md mx-auto mt-10 bg-white rounded-xl border border-slate-200 p-8">
    <h1 class="text-2xl font-semibold mb-1">Masuk</h1>
    <p class="text-sm text-slate-500 mb-6">Gunakan akun LMS sekolah kamu.</p>

    <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-lg border-slate-300 border px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Password</label>
            <input type="password" name="password" required class="w-full rounded-lg border-slate-300 border px-3 py-2">
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember"> Ingat saya
        </label>
        <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-2 font-medium">Masuk</button>
    </form>

    <p class="text-sm text-slate-500 mt-6 text-center">
        Siswa baru? <a href="{{ route('register') }}" class="text-indigo-700 hover:underline">Daftar di sini</a>
    </p>
</div>
@endsection
