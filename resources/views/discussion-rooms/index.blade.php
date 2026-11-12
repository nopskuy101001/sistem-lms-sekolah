@extends('layouts.app')
@section('title', 'Diskusi Guru')

@section('content')
<header class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-indigo-600">Kolaborasi pengajar</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-900">Ruang Diskusi Guru</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Diskusikan perangkat ajar, materi, dan evaluasi bersama guru pengampu mata pelajaran yang sama. Pesan dan lampiran hanya dapat dibuka anggota serta admin.</p>
    </div>
    @if($subjects->isNotEmpty())
        <a href="#buat-ruang-diskusi" class="lms-button lms-button-primary">+ Buat ruang</a>
    @endif
</header>

<div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
    <main class="min-w-0">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div><h2 class="font-semibold text-slate-900">Ruang yang dapat diakses</h2><p class="mt-1 text-xs text-slate-500">{{ $rooms->count() }} ruang diskusi</p></div>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse($rooms as $room)
                <a href="{{ route('discussion-rooms.show', $room) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $room->subject }}</span>
                        @if(auth()->user()->isAdmin())<span class="text-[11px] font-medium text-slate-400">Pantauan admin</span>@endif
                    </div>
                    <h3 class="mt-4 break-words font-bold text-slate-900 group-hover:text-indigo-700">{{ $room->name }}</h3>
                    @if($room->description)<p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-500">{{ $room->description }}</p>@endif
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4 text-xs text-slate-500">
                        <span>{{ $room->members_count }} anggota · {{ $room->messages_count }} pesan</span>
                        <span>{{ $room->updated_at->diffForHumans() }}</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">Dibuat oleh {{ $room->creator->name }}</p>
                </a>
            @empty
                <div class="md:col-span-2 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-indigo-50 text-xl text-indigo-700">&#128172;</div>
                    <h3 class="mt-4 font-semibold text-slate-900">Belum ada ruang diskusi</h3>
                    <p class="mt-1 text-sm text-slate-500">Buat ruang pertama untuk mulai berkolaborasi dengan guru satu mata pelajaran.</p>
                </div>
            @endforelse
        </div>
    </main>

    <aside id="buat-ruang-diskusi" class="min-w-0 xl:self-start">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="grid h-10 w-10 place-items-center rounded-xl bg-indigo-600 text-white">+</div>
                <div><h2 class="font-semibold text-slate-900">Buat ruang baru</h2><p class="mt-0.5 text-xs text-slate-500">Khusus guru pengampu yang relevan.</p></div>
            </div>

            @if($subjects->isEmpty())
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800">
                    {{ auth()->user()->isAdmin() ? 'Belum ada mata pelajaran pada data kelas.' : 'Isi mata pelajaran pada minimal satu kelas yang Anda ampu terlebih dahulu.' }}
                </div>
            @else
                <form method="POST" action="{{ route('discussion-rooms.store') }}" class="mt-5 grid gap-4" data-discussion-room-form>
                    @csrf
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Nama ruang
                        <input name="name" value="{{ old('name') }}" required maxlength="150" placeholder="Contoh: MGMP Matematika XI" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                    </label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Mata pelajaran
                        <select name="subject" required class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm" data-discussion-subject>
                            <option value="">Pilih mata pelajaran</option>
                            @foreach($subjects as $subject)<option value="{{ $subject }}" @selected(old('subject') === $subject)>{{ $subject }}</option>@endforeach
                        </select>
                    </label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Tujuan diskusi
                        <textarea name="description" rows="3" maxlength="3000" placeholder="Topik dan tujuan ruang ini..." class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('description') }}</textarea>
                    </label>

                    <fieldset>
                        <legend class="text-sm font-medium text-slate-700">Guru anggota</legend>
                        <p class="mt-1 text-xs text-slate-500">Daftar otomatis mengikuti mata pelajaran yang dipilih.</p>
                        <div class="mt-2 max-h-64 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-2" data-discussion-teachers>
                            @foreach($teachers as $teacher)
                                @php $teacherSubjects = $teacher->discussion_subjects; @endphp
                                <label class="discussion-teacher-option flex cursor-pointer items-start gap-3 rounded-lg p-2.5 hover:bg-slate-50" data-subjects="{{ $teacherSubjects->map(fn ($item) => mb_strtolower($item))->values()->toJson() }}">
                                    <input type="checkbox" name="member_ids[]" value="{{ $teacher->id }}" @checked(in_array($teacher->id, old('member_ids', []))) class="mt-1 rounded border-slate-300 text-indigo-600">
                                    <x-user-avatar :user="$teacher" class="lms-avatar shrink-0" />
                                    <span class="min-w-0"><b class="block break-words text-sm text-slate-800">{{ $teacher->name }}</b><span class="block break-words text-xs text-slate-500">{{ $teacherSubjects->join(', ') ?: 'Mata pelajaran belum dicatat' }}</span></span>
                                </label>
                            @endforeach
                            <p class="hidden p-3 text-center text-xs text-slate-500" data-discussion-no-teacher>Tidak ada guru lain untuk mata pelajaran ini.</p>
                        </div>
                    </fieldset>
                    <button class="lms-button lms-button-primary justify-center">Buat ruang diskusi</button>
                </form>
            @endif
        </section>
    </aside>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-discussion-room-form]');
    if (!form) return;
    var subject = form.querySelector('[data-discussion-subject]');
    var options = Array.from(form.querySelectorAll('.discussion-teacher-option'));
    var empty = form.querySelector('[data-discussion-no-teacher]');
    function filterTeachers() {
        var selected = (subject.value || '').trim().toLocaleLowerCase('id');
        var visible = 0;
        options.forEach(function (option) {
            var subjects = JSON.parse(option.dataset.subjects || '[]');
            var matches = selected !== '' && subjects.includes(selected);
            option.classList.toggle('hidden', !matches);
            if (!matches) option.querySelector('input').checked = false;
            if (matches) visible++;
        });
        empty.classList.toggle('hidden', visible !== 0 || selected === '');
    }
    subject.addEventListener('change', filterTeachers);
    filterTeachers();
});
</script>
@endpush
