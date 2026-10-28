@extends('layouts.app')
@section('title', 'Kelas Saya')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h1 class="text-2xl font-semibold">Kelas Saya</h1>

    @if(auth()->user()->isSiswa())
        <form method="POST" action="{{ route('classrooms.join') }}" class="flex w-full gap-2 sm:w-auto">
            @csrf
            <input type="text" name="code" value="{{ old('code') }}" placeholder="Kode kelas" maxlength="10" required
                   class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 uppercase sm:w-40 sm:flex-none">
            <button class="shrink-0 whitespace-nowrap rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Gabung Kelas</button>
        </form>
    @endif
</div>

@if(auth()->user()->isGuru() || auth()->user()->isAdmin())
    <details class="mb-6 bg-white border border-slate-200 rounded-xl">
        <summary class="cursor-pointer px-4 py-3 font-medium text-indigo-700">+ Buat kelas baru</summary>
        <form method="POST" action="{{ route('classrooms.store') }}" enctype="multipart/form-data" class="p-4 grid md:grid-cols-2 gap-4 border-t border-slate-200">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Nama kelas *</label>
                <input type="text" name="name" required placeholder="contoh: Matematika Wajib" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Rombel / bagian</label>
                <input type="text" name="section" placeholder="contoh: X-A" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Mata pelajaran</label>
                <input type="text" name="subject" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Ruangan</label>
                <input type="text" name="room" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Warna</label>
                <select name="color" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @foreach(\App\Models\Classroom::COLORS as $color)
                        <option value="{{ $color }}">{{ ucfirst($color) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">@include('classrooms._theme-editor', ['themeClassroom' => null])</div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Deskripsi</label>
                <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
            </div>
            <div class="md:col-span-2">
                <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium">Buat kelas</button>
            </div>
        </form>
    </details>
@endif

@if($classrooms->isEmpty())
    <div class="text-center text-slate-500 py-16 bg-white rounded-xl border border-dashed border-slate-300">
        @if(auth()->user()->isSiswa())
            Kamu belum bergabung ke kelas mana pun. Minta kode kelas ke gurumu lalu masukkan di atas.
        @else
            Belum ada kelas. Buat kelas pertama kamu di atas.
        @endif
    </div>
@else
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($classrooms as $classroom)
            <a href="{{ route('classrooms.show', $classroom) }}"
               class="block bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-md transition">
                <div class="relative {{ $classroom->bannerClass() }} text-white p-4 h-28 overflow-hidden">
                    @if($classroom->theme_image_path)<img src="{{ asset('storage/'.$classroom->theme_image_path) }}" alt="" class="lms-theme-image" loading="lazy" style="{{ $classroom->themeImageStyle() }}"><div class="absolute inset-0 bg-slate-950/50"></div>@endif
                    <div class="relative z-10 font-semibold text-lg leading-tight">{{ $classroom->name }}</div>
                    <div class="relative z-10 text-sm opacity-90">{{ $classroom->section }}{{ $classroom->subject ? ' · '.$classroom->subject : '' }}</div>
                </div>
                <div class="p-4 text-sm text-slate-600">
                    <div>Guru: {{ $classroom->teacher->name }}</div>
                    <div>{{ $classroom->students_count }} siswa</div>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
