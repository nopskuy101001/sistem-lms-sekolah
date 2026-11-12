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
            <article id="announcement-{{ $announcement->id }}" class="bg-white rounded-xl border border-slate-200 p-4 scroll-mt-24">
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
                <section class="mt-4 border-t border-slate-200 pt-4">
                    <h3 class="text-sm font-semibold">Diskusi kelas · {{ $announcement->comments_count }} pesan</h3>
                    <div class="mt-3 space-y-2">
                        @forelse($announcement->comments as $comment)
                            <div class="rounded-lg bg-slate-50 p-3 text-sm">
                                <div class="flex justify-between gap-2"><strong>{{ $comment->user->name }}</strong><time class="text-xs text-slate-400">{{ $comment->created_at->diffForHumans() }}</time></div>
                                <p class="mt-1 whitespace-pre-line break-words">{{ $comment->body }}</p>
                                @if($classroom->canManage(auth()->user()) || $comment->user_id === auth()->id())
                                    <form method="POST" action="{{ route('announcement-comments.destroy', $comment) }}" class="mt-1 text-right">@csrf @method('DELETE')<button class="text-xs text-red-600">Hapus</button></form>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-slate-400">Belum ada balasan.</p>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('announcement-comments.store', $announcement) }}" class="mt-3 flex gap-2">@csrf
                        <textarea name="body" rows="2" maxlength="2000" required placeholder="Tulis tanggapan atau pertanyaan..." class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        <button class="self-end rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Kirim</button>
                    </form>
                </section>
            </article>
        @empty
            <div class="text-center text-slate-500 py-12 bg-white rounded-xl border border-dashed border-slate-300">
                Belum ada pengumuman.
            </div>
        @endforelse
    </section>
</div>
@endsection
