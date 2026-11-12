@extends('layouts.app')
@section('title', $discussionRoom->name)

@section('content')
<div class="mb-5 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <a href="{{ route('discussion-rooms.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">&larr; Semua ruang diskusi</a>
        <div class="mt-4 flex flex-wrap items-center gap-2"><span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $discussionRoom->subject }}</span><span class="text-xs text-slate-500">{{ $discussionRoom->members->count() }} anggota</span></div>
        <h1 class="mt-2 break-words text-2xl font-bold text-slate-900">{{ $discussionRoom->name }}</h1>
        @if($discussionRoom->description)<p class="mt-2 max-w-3xl whitespace-pre-wrap break-words text-sm leading-6 text-slate-500">{{ $discussionRoom->description }}</p>@endif
    </div>
    <a href="{{ route('discussion-rooms.show', $discussionRoom) }}#pesan-terbaru" class="lms-button border border-slate-200 bg-white text-slate-700">Muat pesan terbaru</a>
</div>

<div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_330px]">
    <main class="min-w-0">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-3"><h2 class="text-sm font-semibold text-slate-800">Percakapan</h2><p class="mt-0.5 text-xs text-slate-500">100 pesan terbaru ditampilkan.</p></div>
            <div class="max-h-[62vh] min-h-[22rem] space-y-4 overflow-y-auto p-4 sm:p-5" data-discussion-messages>
                @forelse($messages as $message)
                    @php $mine = $message->user_id === auth()->id(); @endphp
                    <article class="flex min-w-0 gap-3 {{ $mine ? 'flex-row-reverse' : '' }}">
                        <x-user-avatar :user="$message->user" class="lms-avatar shrink-0" />
                        <div class="min-w-0 max-w-[88%] sm:max-w-[75%]">
                            <div class="mb-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-400 {{ $mine ? 'justify-end' : '' }}"><b class="text-slate-600">{{ $message->user->name }}</b><time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->translatedFormat('d M Y, H:i') }}</time></div>
                            <div class="rounded-2xl px-4 py-3 {{ $mine ? 'rounded-tr-md bg-indigo-600 text-white' : 'rounded-tl-md bg-slate-100 text-slate-800' }}">
                                @if($message->body)<p class="whitespace-pre-wrap break-words text-sm leading-6">{{ $message->body }}</p>@endif
                                @if($message->attachment_path)
                                    @if($message->isImage())
                                        <a href="{{ route('discussion-messages.attachment', $message) }}" target="_blank" rel="noopener" class="mt-2 block overflow-hidden rounded-xl bg-black/10">
                                            <img src="{{ route('discussion-messages.attachment', $message) }}" alt="Lampiran {{ $message->attachment_name }}" loading="lazy" class="max-h-96 w-full object-contain">
                                        </a>
                                    @else
                                        <a href="{{ route('discussion-messages.attachment', ['discussionMessage' => $message, 'download' => 1]) }}" class="mt-2 flex min-w-0 items-center gap-3 rounded-xl bg-white/15 p-3 ring-1 ring-inset ring-current/10">
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white/20 text-xs font-bold">FILE</span>
                                            <span class="min-w-0"><b class="block truncate text-xs">{{ $message->attachment_name }}</b><span class="mt-0.5 block text-[11px] opacity-75">{{ $message->attachmentSizeLabel() }} · Unduh</span></span>
                                        </a>
                                    @endif
                                @endif
                            </div>
                            @if(auth()->user()->isAdmin() || $mine || $discussionRoom->creator_id === auth()->id())
                                <form method="POST" action="{{ route('discussion-messages.destroy', $message) }}" class="mt-1 {{ $mine ? 'text-right' : '' }}" onsubmit="return confirm('Hapus pesan ini?')">@csrf @method('DELETE')<button class="text-[11px] text-red-600 hover:underline">Hapus</button></form>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="grid min-h-[18rem] place-items-center text-center"><div><div class="text-3xl">&#128172;</div><p class="mt-3 text-sm font-semibold text-slate-700">Belum ada percakapan</p><p class="mt-1 text-xs text-slate-500">Kirim pesan pertama untuk memulai diskusi.</p></div></div>
                @endforelse
                <div id="pesan-terbaru"></div>
            </div>

            <form method="POST" action="{{ route('discussion-messages.store', $discussionRoom) }}" enctype="multipart/form-data" class="border-t border-slate-200 bg-white p-4 sm:p-5">
                @csrf
                <label for="discussion-body" class="sr-only">Tulis pesan</label>
                <textarea id="discussion-body" name="body" rows="3" maxlength="10000" placeholder="Tulis pesan untuk anggota ruang..." class="w-full resize-y rounded-xl border border-slate-300 px-3 py-2.5 text-sm">{{ old('body') }}</textarea>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <label class="min-w-0 cursor-pointer text-xs font-medium text-indigo-700"><span class="inline-flex items-center rounded-lg bg-indigo-50 px-3 py-2">&#128206;&nbsp; Tambahkan foto atau file</span><input type="file" name="attachment" class="mt-2 block w-full max-w-sm text-xs text-slate-500" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip"></label>
                    <button class="lms-button lms-button-primary">Kirim pesan</button>
                </div>
                <p class="mt-2 text-[11px] text-slate-400">Foto, PDF, dokumen Office, teks, CSV, atau ZIP · maksimal 20 MB.</p>
            </form>
        </section>
    </main>

    <aside class="min-w-0 space-y-5 xl:self-start">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex items-center justify-between gap-3"><h2 class="font-semibold text-slate-900">Anggota</h2><span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ $discussionRoom->members->count() }}</span></div>
            <div class="mt-4 space-y-3">
                @foreach($discussionRoom->members as $member)
                    <div class="flex min-w-0 items-center gap-3"><x-user-avatar :user="$member" class="lms-avatar shrink-0" /><div class="min-w-0"><b class="block truncate text-sm text-slate-800">{{ $member->name }}</b><span class="block text-xs text-slate-500">{{ $member->id === $discussionRoom->creator_id ? 'Pembuat ruang' : 'Guru anggota' }}</span></div></div>
                @endforeach
                @if($discussionRoom->creator->isAdmin())
                    <div class="flex min-w-0 items-center gap-3"><x-user-avatar :user="$discussionRoom->creator" class="lms-avatar shrink-0" /><div class="min-w-0"><b class="block truncate text-sm text-slate-800">{{ $discussionRoom->creator->name }}</b><span class="block text-xs text-slate-500">Admin pembuat ruang</span></div></div>
                @endif
            </div>
        </section>

        @if($canManage)
            <details class="rounded-2xl border border-slate-200 bg-white p-5">
                <summary class="cursor-pointer font-semibold text-slate-900">Kelola anggota</summary>
                <form method="POST" action="{{ route('discussion-rooms.members.update', $discussionRoom) }}" class="mt-4">@csrf @method('PUT')
                    <div class="max-h-72 space-y-2 overflow-y-auto">
                        @foreach($teachers as $teacher)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-slate-50">
                                <input type="checkbox" name="member_ids[]" value="{{ $teacher->id }}" @checked($discussionRoom->members->contains($teacher->id)) class="rounded border-slate-300 text-indigo-600">
                                <x-user-avatar :user="$teacher" class="lms-avatar shrink-0" /><span class="min-w-0 truncate text-sm text-slate-700">{{ $teacher->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button class="lms-button lms-button-primary mt-4 w-full justify-center">Simpan anggota</button>
                </form>
            </details>

            <section class="rounded-2xl border border-red-200 bg-red-50 p-5">
                <h2 class="font-semibold text-red-800">Hapus ruang</h2><p class="mt-1 text-xs leading-5 text-red-700">Seluruh pesan dan lampiran akan dihapus permanen.</p>
                <form method="POST" action="{{ route('discussion-rooms.destroy', $discussionRoom) }}" class="mt-3" onsubmit="return confirm('Hapus ruang diskusi beserta semua pesan dan file?')">@csrf @method('DELETE')<button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">Hapus ruang</button></form>
            </section>
        @endif
    </aside>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var messages = document.querySelector('[data-discussion-messages]');
    if (messages) messages.scrollTop = messages.scrollHeight;
});
</script>
@endpush
