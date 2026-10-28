@extends('layouts.app')
@section('title', $quiz->title)

@php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); @endphp

@section('content')
<div class="mb-4 text-sm"><a href="{{ route('classrooms.classwork', $classroom) }}" class="text-indigo-700 hover:underline">← {{ $classroom->name }}</a></div>

@if($isManager)
    <header class="rounded-2xl bg-gradient-to-br from-indigo-700 to-violet-600 p-6 text-white mb-6 shadow-lg shadow-indigo-100">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-[.18em] text-indigo-100 mb-2">Asesmen format TKA</div>
                <h1 class="text-2xl font-bold">{{ $quiz->title }}</h1>
                @if($quiz->description)<p class="text-sm text-indigo-100 mt-2 whitespace-pre-line">{{ $quiz->description }}</p>@endif
                <p class="text-sm text-indigo-100 mt-3">{{ $quiz->questions->count() }} soal · {{ $quiz->totalPoints() }} poin · {{ $quiz->duration_minutes ? $quiz->duration_minutes.' menit' : 'tanpa batas waktu' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-white/15 px-3 py-1.5 text-xs">{{ $quiz->statusLabel() }}</span>
                <form method="POST" action="{{ route('quizzes.publish', $quiz) }}">@csrf<button class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-indigo-700">{{ $quiz->is_published ? 'Tarik ke draft' : 'Publikasikan' }}</button></form>
                <form method="POST" action="{{ route('quizzes.destroy', $quiz) }}" onsubmit="return confirm('Hapus kuis beserta semua hasilnya?')">@csrf @method('DELETE')<button class="rounded-lg border border-white/30 px-4 py-2 text-sm">Hapus</button></form>
            </div>
        </div>
    </header>

    <div class="quiz-teacher-layout grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,380px)]">
        <main class="min-w-0 space-y-6">
            <section class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex gap-3">
                    <div class="rounded-xl bg-sky-50 text-sky-700 w-10 h-10 grid place-items-center font-bold">TKA</div>
                    <div><h2 class="font-semibold">Struktur soal TKA</h2><p class="text-sm text-slate-500 mt-1">Gunakan stimulus untuk teks, data, tabel, atau kasus yang dipakai oleh beberapa soal. Saat diacak, seluruh soal dalam satu stimulus tetap berdekatan.</p></div>
                </div>
                @if(!$quiz->attempts()->exists())
                    <form method="POST" action="{{ route('question-groups.store', $quiz) }}" enctype="multipart/form-data" class="mt-4 grid gap-3">@csrf
                        <input name="title" value="{{ old('title') }}" placeholder="Judul stimulus (opsional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <textarea name="stimulus" rows="6" data-quiz-auto-grow placeholder="Teks stimulus: bacaan, kasus, atau informasi pengantar (opsional jika memakai gambar/tabel)..." class="quiz-auto-grow rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('stimulus') }}</textarea>
                        @error('stimulus')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 p-3">
                                <label class="block text-xs font-semibold text-slate-700">Gambar, grafik, atau diagram</label>
                                <input type="file" name="stimulus_image" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-indigo-700">
                                <p class="mt-1 text-xs text-slate-500">JPG, PNG, atau WebP. Maksimal 4 MB.</p>
                                @error('stimulus_image')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                <input name="media_alt" value="{{ old('media_alt') }}" placeholder="Keterangan gambar untuk aksesibilitas" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                            </div>
                            <div class="rounded-xl border border-slate-200 p-3">
                                <label class="block text-xs font-semibold text-slate-700">Tabel data</label>
                                <textarea name="table_text" rows="5" data-quiz-auto-grow placeholder="Tempel tabel dari Excel/Sheets di sini. Baris pertama menjadi judul kolom." class="quiz-auto-grow mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">{{ old('table_text') }}</textarea>
                                <p class="mt-1 text-xs text-slate-500">Mendukung tab, koma, atau titik koma. Maks. 30 baris × 12 kolom.</p>
                                @error('table_text')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div><button class="lms-button lms-button-primary">Tambah stimulus</button></div>
                    </form>
                @endif
                @if($quiz->questionGroups->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        @foreach($quiz->questionGroups as $group)
                            <div class="quiz-stimulus-card rounded-xl border border-slate-200 p-3 flex justify-between gap-3">
                                <div class="min-w-0 flex-1"><b class="quiz-readable-text block text-sm">{{ $group->title ?: 'Stimulus tanpa judul' }}</b>@if($group->stimulus)<p class="quiz-readable-text text-xs text-slate-500 mt-1">{{ $group->stimulus }}</p>@endif @include('quizzes.partials.stimulus-media', ['group' => $group, 'compact' => true])</div>
                                @if(!$quiz->attempts()->exists())<form method="POST" action="{{ route('question-groups.destroy', $group) }}" onsubmit="return confirm('Hapus stimulus ini? Soalnya tetap tersimpan.')">@csrf @method('DELETE')<button class="text-xs text-red-600">Hapus</button></form>@endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <div class="flex items-end justify-between mb-3"><div><h2 class="text-lg font-semibold">Daftar soal</h2><p class="text-xs text-slate-500">Tiga bentuk objektif inti tersedia untuk asesmen TKA.</p></div></div>
                <div class="space-y-3">
                    @forelse($quiz->questions as $i => $question)
                        @if($question->group && ($i === 0 || $quiz->questions[$i - 1]->question_group_id !== $question->question_group_id))
                            <section class="quiz-stimulus-card rounded-2xl border border-indigo-200 bg-indigo-50/70 p-5">
                                <div class="mb-2 text-xs font-bold uppercase tracking-wider text-indigo-600">Stimulus untuk kelompok soal berikut</div>
                                @if($question->group->title)<h3 class="quiz-readable-text mb-2 font-semibold text-slate-900">{{ $question->group->title }}</h3>@endif
                                @if($question->group->stimulus)<div class="quiz-readable-text text-sm leading-7 text-slate-700">{{ $question->group->stimulus }}</div>@endif
                                @include('quizzes.partials.stimulus-media', ['group' => $question->group])
                            </section>
                        @endif
                        <article class="quiz-question-card bg-white rounded-2xl border border-slate-200 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap gap-2 text-xs mb-2">
                                        <span class="rounded-full bg-indigo-50 text-indigo-700 px-2 py-1">{{ $question->typeLabel() }}</span>
                                        <span class="rounded-full bg-slate-100 px-2 py-1">{{ $question->points }} poin</span>
                                        @if($question->cognitiveLevelLabel())<span class="rounded-full bg-amber-50 text-amber-700 px-2 py-1">{{ $question->cognitiveLevelLabel() }}</span>@endif
                                    </div>
                                    @if($question->group)<p class="text-xs font-medium text-sky-700 mb-2">Stimulus: {{ $question->group->title ?: 'Tanpa judul' }}</p>@endif
                                    <p class="quiz-readable-text text-sm"><b>{{ $i + 1 }}.</b> {{ $question->body }}</p>
                                    @if($question->competency)<p class="text-xs text-slate-500 mt-2">Kompetensi: {{ $question->competency }}@if($question->subcompetency) · {{ $question->subcompetency }}@endif</p>@endif
                                </div>
                                @if(!$quiz->attempts()->exists())<form method="POST" action="{{ route('questions.destroy', $question) }}" onsubmit="return confirm('Hapus soal ini?')">@csrf @method('DELETE')<button class="text-xs text-red-600">Hapus</button></form>@endif
                            </div>
                            @if($question->isPilihanGanda() || $question->isPilihanGandaKompleks())
                                <ul class="mt-3 grid gap-1 text-sm">
                                    @foreach($question->options() as $key => $text)
                                        @php $isKey = $question->isPilihanGanda() ? $key === $question->correct_option : in_array($key, $question->correct_options ?? [], true); @endphp
                                        <li class="quiz-readable-text rounded-lg px-3 py-2 {{ $isKey ? 'bg-emerald-50 text-emerald-800 font-medium' : 'bg-slate-50 text-slate-600' }}">{{ strtoupper($key) }}. {{ $text }} @if($isKey) ✓ kunci @endif</li>
                                    @endforeach
                                </ul>
                            @elseif($question->isKategori())
                                <div class="mt-3 space-y-1 text-sm">@foreach($question->category_items ?? [] as $item)<div class="quiz-readable-text rounded-lg bg-slate-50 px-3 py-2"><span class="font-medium text-indigo-700">{{ ucfirst($item['answer']) }}</span> · {{ $item['statement'] }}</div>@endforeach</div>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">Belum ada soal. Gunakan form di samping untuk mulai menyusun asesmen.</div>
                    @endforelse
                </div>
            </section>
        </main>

        <aside class="min-w-0 space-y-6">
            @if(auth()->user()->isGuru() && !$quiz->attempts()->exists())
                <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <div class="flex items-start gap-3">
                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-600 font-bold text-white">CSV</div>
                        <div>
                            <h2 class="font-semibold text-emerald-950">Import Soal Massal</h2>
                            <p class="mt-1 text-xs leading-5 text-emerald-800">Tambahkan hingga 500 soal sekaligus dari CSV atau Excel. Mendukung PG, PG kompleks, kategori, esai, serta stimulus teks dan tabel.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                        <a href="{{ route('questions.import.template.xlsx') }}" class="rounded-lg bg-white px-3 py-2 text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100">Template Excel</a>
                        <a href="{{ route('questions.import.template.csv') }}" class="rounded-lg bg-white px-3 py-2 text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100">Template CSV</a>
                    </div>
                    <form method="POST" action="{{ route('questions.import', $quiz) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <input type="file" name="question_file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-2 file:font-semibold file:text-white">
                        @if($errors->has('question_file'))
                            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                                <p class="font-semibold">Import belum dapat diproses:</p>
                                <ul class="mt-1 list-disc space-y-1 pl-4">
                                    @foreach($errors->get('question_file') as $message)<li>{{ $message }}</li>@endforeach
                                </ul>
                            </div>
                        @endif
                        <button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Import ke kuis ini</button>
                    </form>
                    <p class="mt-3 text-[11px] leading-4 text-emerald-700">Unduh dan ikuti template. Import dibatalkan seluruhnya bila ada baris yang tidak valid.</p>
                </section>
            @endif
            @if(auth()->user()->isGuru())
                <section class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5">
                    <h2 class="font-semibold text-indigo-950">Simpan ke Bank Soal</h2>
                    <p class="mt-1 text-xs leading-5 text-indigo-700">Simpan seluruh soal, stimulus, gambar, tabel, kunci, dan poin sebagai paket yang dapat digunakan lagi tahun depan.</p>
                    @if($quiz->questions->isNotEmpty())
                        <form method="POST" action="{{ route('question-banks.store', $quiz) }}" class="mt-4 space-y-3">
                            @csrf
                            <label class="block text-xs font-medium text-slate-700">Nama paket
                                <input name="title" value="{{ old('title', $quiz->title) }}" maxlength="200" required class="mt-1 w-full rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm">
                            </label>
                            <button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Simpan paket soal</button>
                        </form>
                    @else
                        <p class="mt-3 rounded-lg bg-white/70 p-3 text-xs text-slate-600">Tambahkan minimal satu soal terlebih dahulu.</p>
                    @endif
                    <a href="{{ route('question-banks.index') }}" class="mt-3 inline-block text-xs font-semibold text-indigo-700 hover:underline">Buka semua bank soal →</a>
                </section>
            @endif
            @if(!$quiz->attempts()->exists())
                <form method="POST" action="{{ route('questions.store', $quiz) }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5" id="tka-question-form">@csrf
                    <div><h2 class="font-semibold">Tambah soal TKA</h2><p class="text-xs text-slate-500 mt-1">Pilih bentuk soal dan lengkapi kunci.</p></div>
                    <div><label class="block text-xs font-medium mb-1">Bentuk soal</label><select name="type" id="tka-type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="pilihan_ganda">Pilihan Ganda Sederhana</option><option value="pilihan_ganda_kompleks">PG Kompleks - Multi Jawaban</option><option value="kategori">PG Kompleks - Kategori</option><option value="esai">Esai (format lama)</option></select></div>
                    <div><label class="block text-xs font-medium mb-1">Stimulus</label><select name="question_group_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">Tanpa stimulus</option>@foreach($quiz->questionGroups as $group)<option value="{{ $group->id }}">{{ $group->title ?: 'Stimulus #'.$group->id }}</option>@endforeach</select></div>
                    <div><label class="block text-xs font-medium mb-1">Pertanyaan</label><textarea name="body" rows="6" data-quiz-auto-grow required class="quiz-auto-grow w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('body') }}</textarea></div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><div><label class="block text-xs font-medium mb-1">Level kognitif</label><select name="cognitive_level" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm"><option value="">Pilih</option><option value="pemahaman">Pemahaman</option><option value="penerapan">Penerapan</option><option value="penalaran">Penalaran</option></select></div><div><label class="block text-xs font-medium mb-1">Poin</label><input type="number" name="points" value="10" min="1" max="1000" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div></div>
                    <div class="grid gap-2"><input name="competency" placeholder="Kompetensi (opsional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><input name="subcompetency" placeholder="Subkompetensi (opsional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>

                    <div id="option-fields" class="space-y-2">
                        @foreach(['a','b','c','d'] as $key)<div class="flex gap-2 items-start"><span class="w-5 pt-2 text-sm font-semibold">{{ strtoupper($key) }}</span><textarea name="option_{{ $key }}" rows="2" data-quiz-auto-grow placeholder="Opsi {{ strtoupper($key) }}" class="quiz-auto-grow quiz-auto-grow-compact min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea></div>@endforeach
                    </div>
                    <div id="single-key"><label class="block text-xs font-medium mb-1">Kunci jawaban</label><select name="correct_option" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach(['a','b','c','d'] as $key)<option value="{{ $key }}">{{ strtoupper($key) }}</option>@endforeach</select></div>
                    <div id="multi-key" class="hidden"><label class="block text-xs font-medium mb-2">Semua jawaban benar (minimal 2)</label><div class="flex gap-4">@foreach(['a','b','c','d'] as $key)<label class="text-sm"><input type="checkbox" name="correct_options[]" value="{{ $key }}"> {{ strtoupper($key) }}</label>@endforeach</div></div>
                    <div id="category-fields" class="hidden space-y-2"><p class="text-xs text-slate-500">Isi minimal dua pernyataan.</p>@for($i=0;$i<4;$i++)<div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_90px]"><textarea name="category_statements[]" rows="2" data-quiz-auto-grow placeholder="Pernyataan {{ $i + 1 }}" class="quiz-auto-grow quiz-auto-grow-compact rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea><select name="category_answers[]" class="rounded-lg border border-slate-300 px-2 py-2 text-sm"><option value="benar">Benar</option><option value="salah">Salah</option></select></div>@endfor</div>
                    <button class="lms-button lms-button-primary w-full">Simpan soal</button>
                </form>
            @else
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Bank soal dikunci karena sudah ada siswa yang mulai mengerjakan.</div>
            @endif
        </aside>
    </div>

    <form method="POST" action="{{ route('quizzes.security.update', $quiz) }}" class="bg-white rounded-2xl border border-slate-200 p-5 mt-7">@csrf @method('PUT')
        <div class="grid gap-5 lg:grid-cols-[1fr_360px]">
            <div>
                <h2 class="font-semibold">Keamanan, penalti, dan pengacakan</h2>
                <p class="text-xs text-slate-500 mt-1">Siswa diberi tahu sebelum mulai. Nilai mentah dan potongan disimpan terpisah agar dapat diaudit.</p>
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                    Potongan dihitung dari total poin kuis dan dibatasi maksimal {{ \App\Models\Quiz::MAX_VIOLATION_PENALTY_PERCENT }}%.
                    @if($quiz->attempts()->exists()) Kebijakan penalti dikunci karena sudah ada siswa yang mulai mengerjakan. @endif
                </div>
            </div>
            <div class="grid gap-3 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" name="monitoring_enabled" value="1" @checked($quiz->monitoring_enabled)> Pantau perpindahan tab dan fokus</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="require_fullscreen" value="1" @checked($quiz->require_fullscreen)> Wajib layar penuh</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="shuffle_questions" value="1" @checked($quiz->shuffle_questions)> Acak soal per siswa</label>
                <div class="rounded-xl border border-slate-200 p-3">
                    <label class="flex items-center gap-2 font-medium"><input type="checkbox" name="penalty_enabled" value="1" @checked($quiz->penalty_enabled)> Kurangi nilai otomatis</label>
                    <label class="mt-3 block text-xs text-slate-600">Potongan per peringatan</label>
                    <div class="mt-1 flex items-center gap-2"><input type="number" name="penalty_percent_per_violation" value="{{ old('penalty_percent_per_violation', $quiz->penalty_percent_per_violation) }}" min="1" max="10" required class="w-20 rounded-lg border border-slate-300 px-3 py-2"><span>% dari total poin</span></div>
                    @error('penalty_percent_per_violation')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('penalty_enabled')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <button class="lms-button lms-button-primary">Simpan pengaturan</button>
            </div>
        </div>
    </form>

    <section class="mt-7"><h2 class="text-lg font-semibold mb-3">Hasil siswa</h2><div class="mobile-data-table overflow-x-auto bg-white rounded-2xl border border-slate-200"><table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="text-left px-4 py-3">Siswa</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Nilai</th><th class="px-4 py-3">Integritas</th><th></th></tr></thead><tbody>
        @forelse($attempts as $attempt)<tr class="border-t"><td class="px-4 py-3">{{ $attempt->user->name }}</td><td class="px-4 py-3 text-center">{{ !$attempt->isFinished() ? 'Sedang mengerjakan' : (!$attempt->is_graded ? 'Menunggu nilai esai' : 'Selesai') }}</td><td class="px-4 py-3 text-center">{{ $attempt->isFinished() ? $fmt($attempt->score).' / '.$quiz->totalPoints() : '–' }}</td><td class="px-4 py-3 text-center">@if($quiz->monitoring_enabled || $quiz->require_fullscreen)<span class="integrity-badge {{ $attempt->violation_count === 0 ? 'integrity-safe' : ($attempt->violation_count <= 2 ? 'integrity-review' : 'integrity-risk') }}">{{ $attempt->integrityLabel((int)$attempt->violation_count) }} · {{ $attempt->violation_count }}</span>@else Nonaktif @endif</td><td class="px-4 py-3">@if($attempt->isFinished())<a class="text-indigo-700" href="{{ route('attempts.show',$attempt) }}">Lihat / nilai</a>@endif</td></tr>
        @empty<tr><td colspan="5" class="p-6 text-center text-slate-500">Belum ada siswa yang mengerjakan.</td></tr>@endforelse
    </tbody></table></div></section>
@else
    <div class="max-w-2xl bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="bg-gradient-to-br from-indigo-700 to-violet-600 p-6 text-white"><div class="text-xs uppercase tracking-widest text-indigo-100 mb-2">Asesmen format TKA</div><h1 class="text-2xl font-bold">{{ $quiz->title }}</h1>@if($quiz->description)<p class="text-indigo-100 mt-2 text-sm">{{ $quiz->description }}</p>@endif</div>
        <div class="p-6"><div class="grid grid-cols-2 gap-3 text-sm mb-5"><div class="rounded-xl bg-slate-50 p-3"><span class="text-slate-500">Jumlah soal</span><b class="block text-lg">{{ $questionCount }}</b></div><div class="rounded-xl bg-slate-50 p-3"><span class="text-slate-500">Waktu</span><b class="block text-lg">{{ $quiz->duration_minutes ? $quiz->duration_minutes.' menit' : 'Tanpa batas' }}</b></div></div>
            <div class="space-y-2 text-sm text-slate-600 mb-6">@if($quiz->shuffle_questions)<p>• Urutan soal diacak; blok stimulus tetap utuh.</p>@endif @if($quiz->monitoring_enabled)<p>• Perpindahan tab dan fokus dicatat untuk ditinjau guru.</p>@endif @if($quiz->require_fullscreen)<p>• Kuis wajib dikerjakan dalam mode layar penuh; keluar dari layar penuh menambah peringatan.</p>@endif<p>• Kesempatan mengerjakan satu kali.</p></div>
            @if($quiz->penalty_enabled)<div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"><b>Penalti nilai aktif.</b> Setiap peringatan mengurangi {{ $quiz->penalty_percent_per_violation }}% dari total poin, maksimal {{ \App\Models\Quiz::MAX_VIOLATION_PENALTY_PERCENT }}%.</div>@endif
            @if($attempt && $attempt->isFinished())<a href="{{ route('attempts.show',$attempt) }}" class="lms-button lms-button-primary">Lihat hasil</a>@elseif($attempt)<a href="{{ route('attempts.take',$attempt) }}" class="lms-button lms-button-primary">Lanjutkan mengerjakan</a>@elseif($quiz->isOpen())<form method="POST" action="{{ route('attempts.start',$quiz) }}" onsubmit="return confirm('Mulai kuis sekarang? Waktu langsung berjalan.')">@csrf<button class="lms-button lms-button-primary">Mulai asesmen</button></form>@else<p class="text-amber-700 text-sm">Kuis saat ini: {{ $quiz->statusLabel() }}.</p>@endif
        </div>
    </div>
@endif

@if($isManager && !$quiz->attempts()->exists())
<script>
(() => {
    const type = document.getElementById('tka-type');
    if (!type) return;
    const autoGrowFields = [...document.querySelectorAll('[data-quiz-auto-grow]')];
    const resizeField = field => {
        if (field.offsetParent === null) return;
        field.style.height = 'auto';
        field.style.height = `${Math.max(field.scrollHeight + 2, field.classList.contains('quiz-auto-grow-compact') ? 58 : 112)}px`;
    };
    const update = () => {
        const value = type.value;
        document.getElementById('option-fields').classList.toggle('hidden', !['pilihan_ganda','pilihan_ganda_kompleks'].includes(value));
        document.getElementById('single-key').classList.toggle('hidden', value !== 'pilihan_ganda');
        document.getElementById('multi-key').classList.toggle('hidden', value !== 'pilihan_ganda_kompleks');
        document.getElementById('category-fields').classList.toggle('hidden', value !== 'kategori');
        requestAnimationFrame(() => autoGrowFields.filter(field => field.offsetParent !== null).forEach(resizeField));
    };
    autoGrowFields.forEach(field => {
        field.addEventListener('input', () => resizeField(field));
        resizeField(field);
    });
    let resizeFrame;
    const resizeVisibleFields = () => {
        cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(() => autoGrowFields.forEach(resizeField));
    };
    // Observe width only, so changing the height does not trigger a resize loop.
    if ('ResizeObserver' in window) {
        const widths = new WeakMap();
        const observer = new ResizeObserver(entries => {
            let changed = false;
            entries.forEach(({ target, contentRect }) => {
                if (widths.get(target) !== contentRect.width) {
                    widths.set(target, contentRect.width);
                    changed = true;
                }
            });
            if (changed) resizeVisibleFields();
        });
        autoGrowFields.forEach(field => observer.observe(field));
    }
    window.addEventListener('resize', resizeVisibleFields);
    document.fonts?.ready.then(resizeVisibleFields);
    type.addEventListener('change', update); update();
})();
</script>
@endif
@endsection
