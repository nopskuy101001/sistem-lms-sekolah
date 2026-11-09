@extends('layouts.app')
@section('title', 'Profil Saya')

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="lms-page-heading">
        <div>
            <p class="lms-eyebrow">Akun pengguna</p>
            <h1 class="lms-page-title">Profil Saya</h1>
            <p class="lms-page-description">Kelola foto profil dan email akun. NIS, role, dan identitas siswa tetap dikelola sekolah.</p>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1.25fr)_minmax(260px,.75fr)]">
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-bold text-slate-900">Foto profil</h2>
            <p class="mt-1 text-sm text-slate-500">Gunakan foto JPG, PNG, atau WebP maksimal 3 MB, lalu geser dan perbesar hingga posisinya sesuai.</p>

            <form method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data" class="mt-6" data-profile-photo-form>
                @csrf @method('PUT')
                <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-start">
                    <div class="lms-profile-photo-preview" data-profile-photo-preview tabindex="0" aria-label="Pratinjau foto profil. Geser foto untuk mengatur posisi.">
                        @if($user->profile_photo_path)
                            <img src="{{ asset('storage/'.$user->profile_photo_path) }}?v={{ $user->updated_at?->timestamp }}" alt="Foto profil {{ $user->name }}" data-profile-photo-image style="{{ $user->profilePhotoStyle() }}">
                        @else
                            <img alt="Pratinjau foto profil" data-profile-photo-image hidden>
                            <span data-profile-photo-initial>{{ mb_strtoupper(mb_substr(trim($user->name), 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="w-full min-w-0 flex-1">
                        <label for="profile_photo" class="block text-sm font-semibold text-slate-800">Pilih foto baru</label>
                        <input id="profile_photo" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" @required(!$user->profile_photo_path) data-profile-photo-input class="mt-2 block w-full rounded-xl border border-slate-300 bg-slate-50 p-2 text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-100 file:px-3 file:py-2 file:font-semibold file:text-indigo-700">
                        <p class="mt-2 text-xs leading-5 text-slate-500">Tarik foto pada kotak pratinjau untuk memindahkannya, atau gunakan pengatur di bawah. Bagian di luar kotak akan terpotong.</p>
                        @error('profile_photo')<p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="lms-profile-photo-controls mt-6">
                    <label>Zoom <output data-profile-zoom-output>{{ old('profile_photo_zoom', $user->profile_photo_zoom ?? 100) }}%</output>
                        <input type="range" name="profile_photo_zoom" min="100" max="300" step="5" value="{{ old('profile_photo_zoom', $user->profile_photo_zoom ?? 100) }}" data-profile-photo-zoom>
                    </label>
                    <label>Posisi horizontal <output data-profile-x-output>{{ old('profile_photo_position_x', $user->profile_photo_position_x ?? 50) }}%</output>
                        <input type="range" name="profile_photo_position_x" min="0" max="100" value="{{ old('profile_photo_position_x', $user->profile_photo_position_x ?? 50) }}" data-profile-photo-x>
                    </label>
                    <label>Posisi vertikal <output data-profile-y-output>{{ old('profile_photo_position_y', $user->profile_photo_position_y ?? 50) }}%</output>
                        <input type="range" name="profile_photo_position_y" min="0" max="100" value="{{ old('profile_photo_position_y', $user->profile_photo_position_y ?? 50) }}" data-profile-photo-y>
                    </label>
                </div>
                @foreach(['profile_photo_zoom', 'profile_photo_position_x', 'profile_photo_position_y'] as $field)
                    @error($field)<p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                @endforeach
                <button class="lms-button lms-button-primary mt-5">Simpan foto dan posisi</button>
            </form>

            @if($user->profile_photo_path)
                <form method="POST" action="{{ route('profile.photo.destroy') }}" class="mt-5 border-t border-slate-100 pt-5" onsubmit="return confirm('Hapus foto profil saat ini?')">
                    @csrf @method('DELETE')
                    <button class="text-sm font-semibold text-rose-600 hover:underline">Hapus foto profil</button>
                </form>
            @endif
        </section>

        <aside class="rounded-2xl border border-slate-200 bg-white p-6">
            <div class="flex items-center gap-3">
                <x-user-avatar :user="$user" class="lms-avatar lms-profile-summary-avatar" />
                <div class="min-w-0"><strong class="block break-words text-slate-900">{{ $user->name }}</strong><span class="text-xs text-slate-500">{{ $user->roleLabel() }}</span></div>
            </div>
            <dl class="mt-6 grid gap-4 text-sm">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email</dt><dd class="mt-1 break-all text-slate-700">{{ $user->email ?: 'Belum diatur' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $user->isSiswa() ? 'NIS' : 'NIP / Nomor induk' }}</dt><dd class="mt-1 text-slate-700">{{ $user->nomor_induk ?: 'Belum diisi' }}</dd></div>
            </dl>
            <a href="{{ route('password.change') }}" class="lms-button mt-6 w-full border border-slate-300 bg-white text-slate-700">Ganti password</a>
        </aside>
    </div>

    <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-6" aria-labelledby="profile-email-heading">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 id="profile-email-heading" class="text-lg font-bold text-slate-900">Email akun</h2>
                <p class="mt-1 text-sm text-slate-500">Email digunakan untuk login dan notifikasi. NIS/NIP tidak akan ikut berubah.</p>
                <p class="mt-3 break-all text-sm font-semibold text-slate-800">{{ $user->email ?: 'Belum diisi' }}</p>
                <p class="mt-1 text-xs {{ $user->email_verified_at ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $user->email_verified_at ? 'Email sudah terverifikasi.' : 'Email belum terverifikasi (termasuk email sementara yang dibuat sistem).' }}
                </p>
            </div>
            <button type="button" id="toggle-profile-email" aria-controls="profile-email-form" aria-expanded="{{ $errors->has('email') || $errors->has('current_password') ? 'true' : 'false' }}"
                class="lms-button lms-button-primary self-start" onclick="var form=document.getElementById('profile-email-form'); form.hidden=!form.hidden; this.setAttribute('aria-expanded',String(!form.hidden));">
                {{ str_ends_with((string) $user->email, '@akun.ruangkelas.local') || blank($user->email) ? 'Tambahkan Email Asli' : 'Edit / Ganti Email' }}
            </button>
        </div>
        <form id="profile-email-form" method="POST" action="{{ route('profile.email.request') }}" class="mt-5 space-y-4 border-t border-slate-100 pt-5" @if(!$errors->has('email') && !$errors->has('current_password')) hidden @endif>
            @csrf
            <div>
                <label for="profile_new_email" class="block text-sm font-semibold text-slate-800">Email baru</label>
                <input id="profile_new_email" type="email" name="email" autocomplete="email" inputmode="email" required maxlength="255"
                    value="{{ old('email') }}" placeholder="nama@gmail.com" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white p-3 text-sm">
                @error('email')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="profile_current_password" class="block text-sm font-semibold text-slate-800">Konfirmasi password akun saat ini</label>
                <input id="profile_current_password" type="password" name="current_password" autocomplete="current-password" required
                    class="mt-2 block w-full rounded-xl border border-slate-300 bg-white p-3 text-sm">
                @error('current_password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs leading-5 text-slate-500">Sistem mengirim tautan verifikasi ke email baru yang berlaku 30 menit. Email lama tetap aktif hingga konfirmasi berhasil. Jika SMTP belum diaktifkan, perubahan tidak dilakukan agar akun tetap aman.</p>
            <button type="submit" class="lms-button lms-button-primary">Kirim tautan verifikasi email</button>
        </form>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/profile-photo.js') }}?v=1"></script>
@endpush
