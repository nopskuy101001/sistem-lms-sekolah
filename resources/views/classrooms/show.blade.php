@extends('layouts.app')
@section('title', $classroom->name)

@section('content')
@include('classrooms._header-modern', ['tab' => 'stream'])

<div class="grid md:grid-cols-4 gap-6">
    <aside class="md:col-span-1 space-y-4">
        @if(auth()->user()->isSiswa())
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <h3 class="font-medium mb-2">Tugas mendatang</h3>
                @forelse($upcoming as $assignment)
                    <a href="{{ route('assignments.show', $assignment) }}" class="block text-sm py-1 hover:text-indigo-700">
                        {{ $assignment->title }}
                        <span class="block text-xs text-slate-500">
                            {{ $assignment->due_at ? 'Tenggat '.$assignment->due_at->translatedFormat('d M, H:i') : 'Tanpa tenggat' }}
                        </span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Tidak ada tugas yang menunggu.</p>
                @endforelse
            </div>
        @endif

        @if($classroom->description)
            <div class="bg-white rounded-xl border border-slate-200 p-4 text-sm text-slate-600">
                <h3 class="font-medium text-slate-800 mb-1">Deskripsi</h3>
                {{ $classroom->description }}
            </div>
        @endif
    </aside>

    <section class="md:col-span-3 space-y-4">
        @if($classroom->canManage(auth()->user()))
            <form method="POST" action="{{ route('announcements.store', $classroom) }}" class="bg-white rounded-xl border border-slate-200 p-4">
                @csrf
                <textarea name="body" rows="3" required placeholder="Umumkan sesuatu ke kelas..."
                          class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                <div class="mt-2 text-right">
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium">Posting</button>
                </div>
            </form>
        @endif

        @forelse($announcements as $announcement)
            <article class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="font-medium">{{ $announcement->user->name }}</div>
                        <div class="text-xs text-slate-500">{{ $announcement->created_at->translatedFormat('d M Y, H:i') }}</div>
                    </div>
                    @if($classroom->canManage(auth()->user()))
                        <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" onsubmit="return confirm('Hapus pengumuman ini?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-slate-400 hover:text-red-600">Hapus</button>
                        </form>
                    @endif
                </div>
                <p class="mt-3 whitespace-pre-line text-sm">{{ $announcement->body }}</p>
            </article>
        @empty
            <div class="text-center text-slate-500 py-12 bg-white rounded-xl border border-dashed border-slate-300">
                Belum ada pengumuman.
            </div>
        @endforelse
    </section>
</div>
@endsection
