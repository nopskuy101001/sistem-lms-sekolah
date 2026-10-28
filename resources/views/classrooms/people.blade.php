@extends('layouts.app')
@section('title', 'Anggota - '.$classroom->name)

@section('content')
@include('classrooms._header-modern', ['tab' => 'people'])

<div class="max-w-2xl">
    <h2 class="text-lg font-semibold border-b border-slate-300 pb-2 mb-3">Guru</h2>
    <div class="flex items-center gap-3 py-2 mb-8">
        <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-medium">
            {{ strtoupper(substr($classroom->teacher->name, 0, 1)) }}
        </div>
        <div>
            <div class="font-medium">{{ $classroom->teacher->name }}</div>
            @if($classroom->teacher->nomor_induk)
                <div class="text-xs text-slate-500">NIP {{ $classroom->teacher->nomor_induk }}</div>
            @endif
        </div>
    </div>

    <h2 class="text-lg font-semibold border-b border-slate-300 pb-2 mb-3 flex justify-between">
        <span>Siswa</span>
        <span class="text-sm font-normal text-slate-500">{{ $students->count() }} siswa</span>
    </h2>

    @forelse($students as $student)
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-3">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-medium">
                    {{ strtoupper(substr($student->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <div class="break-words font-medium">{{ $student->name }}</div>
                    @if($student->nomor_induk)
                        <div class="text-xs text-slate-500">NIS {{ $student->nomor_induk }}</div>
                    @endif
                </div>
            </div>
            @if($classroom->canManage(auth()->user()))
                <form method="POST" action="{{ route('classrooms.students.remove', [$classroom, $student]) }}" class="shrink-0"
                      onsubmit="return confirm('Keluarkan {{ $student->name }} dari kelas?')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-slate-400 hover:text-red-600">Keluarkan</button>
                </form>
            @endif
        </div>
    @empty
        <p class="text-sm text-slate-500 py-4">Belum ada siswa. Bagikan kode kelas agar siswa bisa bergabung.</p>
    @endforelse
</div>
@endsection
