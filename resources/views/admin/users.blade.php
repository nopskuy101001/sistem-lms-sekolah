@extends('layouts.app')
@section('title', 'Kelola Pengguna')

@section('content')
<div class="lms-page-heading">
    <div>
        <p class="lms-eyebrow">Administrasi sekolah</p>
        <h1 class="lms-page-title">Kelola Pengguna</h1>
        <p class="lms-page-description">Buat akun baru, cari pengguna, atau hapus akun yang tidak lagi digunakan.</p>
    </div>
</div>

<div class="mb-6 rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3 flex items-start gap-3">
    <svg viewBox="0 0 24 24" width="19" height="19" class="text-indigo-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="M12 8v4M12 16h.01"/></svg>
    <div>
        <p class="text-sm font-semibold text-indigo-900">Role pengguna bersifat read-only</p>
        <p class="text-xs leading-5 text-indigo-700/80 mt-1">Role hanya ditentukan saat akun dibuat. Jika role perlu diganti, hapus akun lama lalu buat akun baru dengan role yang sesuai.</p>
    </div>
</div>

<details class="mb-6 bg-white border border-slate-200 rounded-xl">
    <summary class="cursor-pointer px-4 py-3 font-medium text-indigo-700">+ Tambah pengguna (guru / admin / siswa)</summary>
    <form method="POST" action="{{ route('admin.users.store') }}" class="p-4 grid md:grid-cols-2 gap-4 border-t border-slate-200">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nama *</label>
            <input type="text" name="name" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Email *</label>
            <input type="email" name="email" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Role *</label>
            <select name="role" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="guru">Guru</option>
                <option value="siswa">Siswa</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">NIP / NIS</label>
            <input type="text" name="nomor_induk" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">Password awal * (min. 8 karakter)</label>
            <input type="text" name="password" required minlength="8" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
        <div class="md:col-span-2">
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium">Simpan</button>
        </div>
    </form>
</details>

<section class="mb-6 overflow-hidden rounded-2xl border border-emerald-200 bg-white">
    <div class="grid gap-5 p-5 lg:grid-cols-[1fr_380px]">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Import siswa batch</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">Buat banyak akun dari CSV atau Excel</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Unduh template, isi data siswa, lalu unggah kembali. Kolom wajib adalah NIS, Nama, dan Tanggal Lahir.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.users.import.template.xlsx') }}" class="lms-button lms-button-primary">Unduh template Excel</a>
                <a href="{{ route('admin.users.import.template.csv') }}" class="lms-button border border-slate-300 bg-white text-slate-700">Unduh template CSV</a>
            </div>
            <div class="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-900">
                <b>Password awal:</b> tanggal lahir dengan format <code>ddmmyyyy</code>. Contoh 22/03/2005 menjadi <code>22032005</code>. Siswa wajib mengganti password saat login pertama.
            </div>
        </div>
        <form method="POST" action="{{ route('admin.users.import') }}" enctype="multipart/form-data" class="rounded-xl border border-slate-200 bg-slate-50 p-4 self-start">
            @csrf
            <label for="student_file" class="block text-sm font-semibold text-slate-800">File data siswa</label>
            <input id="student_file" type="file" name="student_file" accept=".csv,.xlsx" required class="mt-2 block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-100 file:px-3 file:py-2 file:font-semibold file:text-emerald-800">
            <p class="mt-2 text-xs text-slate-500">CSV atau XLSX, maksimal 5 MB dan 1.000 siswa.</p>
            @error('student_file')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            <button class="mt-4 w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Import akun siswa</button>
        </form>
    </div>
</section>

<form method="GET" class="mb-4 grid gap-2 sm:flex sm:flex-wrap">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama / email / NIS"
           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:w-auto sm:flex-1">
    <select name="role" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:w-auto">
        <option value="">Semua role</option>
        @foreach(['admin' => 'Admin', 'guru' => 'Guru', 'siswa' => 'Siswa'] as $value => $label)
            <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="bg-slate-800 text-white rounded-lg px-4 py-2 text-sm">Filter</button>
</form>

<div class="mobile-data-table overflow-x-auto bg-white rounded-xl border border-slate-200">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-slate-600">
            <tr>
                <th class="text-left px-4 py-3 font-medium">Nama</th>
                <th class="text-left px-4 py-3 font-medium">Email</th>
                <th class="text-left px-4 py-3 font-medium">NIP/NIS</th>
                <th class="px-4 py-3 font-medium">Role</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-3">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                    <td class="px-4 py-3 text-slate-600"><span class="block">{{ $user->nomor_induk ?? '-' }}</span>@if($user->date_of_birth)<span class="text-[10px] text-slate-400">Lahir {{ $user->date_of_birth->format('d/m/Y') }}</span>@endif</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full
                            {{ $user->role === 'admin' ? 'bg-violet-50 text-violet-700' : ($user->role === 'guru' ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700') }}">
                            <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                            {{ $user->roleLabel() }}{{ $user->id === auth()->id() ? ' · Anda' : '' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                  onsubmit="return confirm('Hapus pengguna {{ $user->name }}? Kelas yang ia ajar ikut terhapus.')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-slate-400 hover:text-red-600">Hapus</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $users->links() }}</div>
@endsection
