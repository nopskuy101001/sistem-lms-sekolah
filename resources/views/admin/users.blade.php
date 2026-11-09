@extends('layouts.app')
@section('title', 'Kelola Pengguna')

@section('content')
<div class="lms-page-heading">
    <div>
        <p class="lms-eyebrow">Administrasi sekolah</p>
        <h1 class="lms-page-title">Kelola Pengguna</h1>
        <p class="lms-page-description">Buat akun baru, cari pengguna, reset password guru/siswa, atau hapus akun yang tidak lagi digunakan.</p>
    </div>
</div>

<div class="mb-6 rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3 flex items-start gap-3">
    <svg viewBox="0 0 24 24" width="19" height="19" class="text-indigo-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="M12 8v4M12 16h.01"/></svg>
    <div>
        <p class="text-sm font-semibold text-indigo-900">Role pengguna bersifat read-only</p>
        <p class="text-xs leading-5 text-indigo-700/80 mt-1">Role hanya ditentukan saat akun dibuat. Jika role perlu diganti, hapus akun lama lalu buat akun baru dengan role yang sesuai.</p>
    </div>
</div>

<div class="mb-6 grid gap-4 xl:grid-cols-2">
    <details class="bg-white border border-emerald-200 rounded-xl" @if($errors->getBag('studentCreate')->any() || old('account_form') === 'student') open @endif>
        <summary class="cursor-pointer px-4 py-3 font-semibold text-emerald-700">+ Tambah siswa manual</summary>
        <form method="POST" action="{{ route('admin.users.students.store') }}" class="p-4 grid sm:grid-cols-2 gap-4 border-t border-slate-200">
            @csrf
            <input type="hidden" name="account_form" value="student">
            <div>
                <label for="student_name" class="block text-sm font-medium mb-1">Nama lengkap *</label>
                <input id="student_name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('name', 'studentCreate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="student_nis" class="block text-sm font-medium mb-1">NIS *</label>
                <input id="student_nis" type="text" name="nomor_induk" value="{{ old('nomor_induk') }}" required maxlength="50" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Contoh: 000123">
                @error('nomor_induk', 'studentCreate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="student_birth_date" class="block text-sm font-medium mb-1">Tanggal lahir *</label>
                <input id="student_birth_date" type="text" name="date_of_birth" value="{{ old('date_of_birth') }}" required inputmode="numeric" autocomplete="bday" maxlength="10" pattern="[0-9]{2}/[0-9]{2}/[0-9]{4}" placeholder="dd/mm/yyyy" aria-describedby="student_birth_date_help" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <p id="student_birth_date_help" class="mt-1 text-xs text-slate-500">Contoh: 22/03/2005. Password awal otomatis menjadi 22032005.</p>
                @error('date_of_birth', 'studentCreate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="student_email" class="block text-sm font-medium mb-1">Email <span class="font-normal text-slate-400">(opsional)</span></label>
                <input id="student_email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Dibuat otomatis jika kosong">
                @error('email', 'studentCreate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="student_gender" class="block text-sm font-medium mb-1">Jenis kelamin</label>
                <select id="student_gender" name="gender" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <option value="">Tidak diisi</option>
                    <option value="L" @selected(old('gender') === 'L')>Laki-laki</option>
                    <option value="P" @selected(old('gender') === 'P')>Perempuan</option>
                </select>
                @error('gender', 'studentCreate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2 rounded-lg bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">
                Password awal otomatis memakai tanggal lahir format <code>ddmmyyyy</code>. Siswa wajib menggantinya saat login pertama.
            </div>
            <div class="sm:col-span-2">
                <button class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg px-4 py-2 text-sm font-medium">Simpan akun siswa</button>
            </div>
        </form>
    </details>

    <details class="bg-white border border-indigo-200 rounded-xl" @if($errors->getBag('staffCreate')->any() || old('account_form') === 'staff') open @endif>
        <summary class="cursor-pointer px-4 py-3 font-semibold text-indigo-700">+ Tambah guru atau admin</summary>
        <form method="POST" action="{{ route('admin.users.store') }}" class="p-4 grid sm:grid-cols-2 gap-4 border-t border-slate-200">
            @csrf
            <input type="hidden" name="account_form" value="staff">
            <div>
                <label for="staff_name" class="block text-sm font-medium mb-1">Nama lengkap *</label>
                <input id="staff_name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('name', 'staffCreate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="staff_email" class="block text-sm font-medium mb-1">Email *</label>
                <input id="staff_email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('email', 'staffCreate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="staff_role" class="block text-sm font-medium mb-1">Role *</label>
                <select id="staff_role" name="role" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <option value="guru" @selected(old('role', 'guru') === 'guru')>Guru</option>
                    <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                </select>
                @error('role', 'staffCreate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="staff_number" class="block text-sm font-medium mb-1">NIP / nomor induk</label>
                <input id="staff_number" type="text" name="nomor_induk" value="{{ old('nomor_induk') }}" maxlength="50" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('nomor_induk', 'staffCreate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="staff_password" class="block text-sm font-medium mb-1">Password awal *</label>
                <input id="staff_password" type="password" name="password" required minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('password', 'staffCreate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="staff_password_confirmation" class="block text-sm font-medium mb-1">Ulangi password *</label>
                <input id="staff_password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
            <div class="sm:col-span-2 text-xs leading-5 text-slate-500">Guru/admin juga wajib mengganti password awal saat login pertama.</div>
            <div class="sm:col-span-2">
                <button class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2 text-sm font-medium">Simpan akun staf</button>
            </div>
        </form>
    </details>
</div>

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

@if($resetUser)
    <section id="reset-password" class="mb-6 overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
        <div class="border-b border-amber-100 bg-amber-50 px-5 py-4">
            <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Reset password oleh admin</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $resetUser->name }}</h2>
            <p class="mt-1 text-sm text-slate-600">
                {{ $resetUser->roleLabel() }} · {{ $resetUser->nomor_induk ?: $resetUser->email }}
            </p>
        </div>
        <form method="POST" action="{{ route('admin.users.password.reset', $resetUser) }}" class="grid gap-4 p-5 md:grid-cols-2" data-confirm="{{ 'Reset password '.$resetUser->name.' sekarang?' }}" onsubmit="return confirm(this.dataset.confirm)">
            @csrf @method('PUT')
            <div class="md:col-span-2 rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-xs leading-5 text-amber-900">
                Password lama akan langsung tidak berlaku, seluruh sesi aktif akun ini akan dicabut, dan pengguna wajib mengganti password sementara saat login berikutnya. Sampaikan password sementara melalui jalur yang aman.
            </div>
            <div>
                <label for="reset_password" class="block text-sm font-medium text-slate-800">Password sementara baru *</label>
                <input id="reset_password" type="password" name="password" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Minimal 8 karakter">
                @error('password', 'passwordReset')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="reset_password_confirmation" class="block text-sm font-medium text-slate-800">Ulangi password sementara *</label>
                <input id="reset_password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
            <div class="md:col-span-2">
                <label for="admin_password" class="block text-sm font-medium text-slate-800">Konfirmasi password admin Anda *</label>
                <input id="admin_password" type="password" name="admin_password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 md:max-w-md" placeholder="Untuk mengesahkan tindakan ini">
                @error('admin_password', 'passwordReset')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2 flex flex-wrap gap-2">
                <button class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Reset password sekarang</button>
                <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</a>
            </div>
        </form>
    </section>
@endif

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
                    <td class="px-4 py-3"><span class="flex items-center gap-2"><x-user-avatar :user="$user" class="lms-mini-avatar" /><span>{{ $user->name }}</span></span></td>
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
                        <div class="flex flex-wrap items-center justify-end gap-3">
                            @if(in_array($user->role, ['guru', 'siswa'], true))
                                <a href="{{ route('admin.users.index', ['reset_user' => $user->id]) }}#reset-password" class="text-xs font-semibold text-amber-700 hover:text-amber-900">Reset password</a>
                            @endif
                            @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                  data-confirm="{{ 'Hapus pengguna '.$user->name.'? Kelas yang ia ajar ikut terhapus.' }}"
                                  onsubmit="return confirm(this.dataset.confirm)">
                                @csrf @method('DELETE')
                                <button class="text-xs text-slate-400 hover:text-red-600">Hapus</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $users->links() }}</div>
@endsection
