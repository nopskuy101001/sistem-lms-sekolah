@extends('layouts.app')
@section('title', 'Bank Soal')

@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Koleksi Guru</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-900">Bank Soal</h1>
        <p class="mt-2 max-w-2xl text-sm text-slate-500">Simpan paket soal dari kuis lama dan gunakan kembali sebagai kuis draft pada kelas mana pun yang Anda ajar.</p>
    </div>
    <a href="{{ route('dashboard') }}#kelas" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-indigo-300 hover:text-indigo-700">Pilih kelas untuk membuat soal</a>
</div>

@if($questionBanks->isEmpty())
    <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-indigo-50 text-xl text-indigo-600">▤</div>
        <h2 class="mt-4 font-semibold text-slate-900">Bank soal masih kosong</h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">Buka salah satu kuis, kemudian gunakan panel “Simpan ke Bank Soal”. Seluruh format TKA dan stimulus akan ikut disimpan.</p>
    </section>
@else
    <div class="grid gap-5 lg:grid-cols-2">
        @foreach($questionBanks as $bank)
            @php $groupCount = count($bank->payload['groups'] ?? []); @endphp
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold text-indigo-700">Paket soal TKA</span>
                            <h2 class="mt-3 break-words text-lg font-bold text-slate-900">{{ $bank->title }}</h2>
                            <p class="mt-1 text-xs text-slate-500">Sumber: {{ $bank->source_classroom ?: 'Kelas tidak dicatat' }} · disimpan {{ $bank->created_at->translatedFormat('d M Y, H:i') }}</p>
                            <p class="mt-1 text-xs text-slate-500">Durasi: {{ filled($bank->payload['quiz']['duration_minutes'] ?? null) ? $bank->payload['quiz']['duration_minutes'].' menit' : 'Tanpa batas' }}</p>
                        </div>
                        <form method="POST" action="{{ route('question-banks.destroy', $bank) }}" onsubmit="return confirm('Hapus paket bank soal ini? Kuis yang pernah dibuat darinya tidak akan ikut terhapus.')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-semibold text-red-600 hover:underline">Hapus</button>
                        </form>
                    </div>
                    @if($bank->description)<p class="mt-3 line-clamp-2 text-sm text-slate-600">{{ $bank->description }}</p>@endif
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-slate-50 p-3"><strong class="block text-lg text-slate-900">{{ $bank->question_count }}</strong><span class="text-[11px] text-slate-500">Soal</span></div>
                        <div class="rounded-xl bg-slate-50 p-3"><strong class="block text-lg text-slate-900">{{ $bank->total_points }}</strong><span class="text-[11px] text-slate-500">Poin</span></div>
                        <div class="rounded-xl bg-slate-50 p-3"><strong class="block text-lg text-slate-900">{{ $groupCount }}</strong><span class="text-[11px] text-slate-500">Stimulus</span></div>
                    </div>
                    <a href="{{ route('question-banks.show', $bank) }}" class="mt-4 flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:border-indigo-300 hover:bg-indigo-100">
                        <span aria-hidden="true">☷</span>
                        Lihat isi soal
                    </a>
                    <a href="{{ route('question-banks.excel', $bank) }}" class="mt-2 flex min-h-11 w-full items-center justify-center rounded-xl border border-emerald-200 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">Unduh Excel</a>
                    @if(collect($bank->payload['groups'] ?? [])->contains(fn ($group) => filled($group['media_path'] ?? null)))
                        <p class="mt-2 text-xs text-amber-700">Gambar stimulus perlu disimpan terpisah; file Excel memuat soal, teks, dan tabel.</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('question-banks.quizzes.store', $bank) }}" class="space-y-3 bg-slate-50/70 p-5">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700">Gunakan pada kelas</label>
                        <select name="classroom_id" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            <option value="">Pilih kelas tujuan</option>
                            @foreach($classrooms as $classroom)
                                <option value="{{ $classroom->id }}">{{ $classroom->name }}@if($classroom->section) · {{ $classroom->section }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700">Judul kuis baru</label>
                        <input name="title" value="{{ $bank->payload['quiz']['title'] ?? $bank->title }}" maxlength="200" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                    </div>
                    <button @disabled($classrooms->isEmpty()) class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">Buat kuis draft dari paket ini</button>
                    @if($classrooms->isEmpty())<p class="text-xs text-amber-700">Buat kelas terlebih dahulu sebelum menggunakan bank soal.</p>@endif
                </form>
            </article>
        @endforeach
    </div>
@endif
@endsection
