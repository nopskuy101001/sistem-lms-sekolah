<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'profile_photo_zoom' => ['sometimes', 'integer', 'between:100,300'],
            'profile_photo_position_x' => ['sometimes', 'integer', 'between:0,100'],
            'profile_photo_position_y' => ['sometimes', 'integer', 'between:0,100'],
        ]);

        $user = $request->user();
        $oldPath = $user->profile_photo_path;
        if (! $request->hasFile('profile_photo') && ! $oldPath) {
            throw ValidationException::withMessages(['profile_photo' => 'Pilih foto profil terlebih dahulu.']);
        }

        $newPath = $request->hasFile('profile_photo')
            ? $data['profile_photo']->store('profile-photos', 'public')
            : null;
        if ($request->hasFile('profile_photo') && ! $newPath) {
            return back()->withErrors(['profile_photo' => 'Foto profil gagal disimpan. Coba unggah kembali.']);
        }

        try {
            $user->update([
                'profile_photo_path' => $newPath ?: $oldPath,
                'profile_photo_zoom' => (int) ($data['profile_photo_zoom'] ?? $user->profile_photo_zoom ?? 100),
                'profile_photo_position_x' => (int) ($data['profile_photo_position_x'] ?? $user->profile_photo_position_x ?? 50),
                'profile_photo_position_y' => (int) ($data['profile_photo_position_y'] ?? $user->profile_photo_position_y ?? 50),
            ]);
        } catch (\Throwable $exception) {
            if ($newPath) Storage::disk('public')->delete($newPath);
            throw $exception;
        }

        if ($newPath && $oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $oldPath = $user->profile_photo_path;

        if ($oldPath) {
            $user->update([
                'profile_photo_path' => null,
                'profile_photo_zoom' => 100,
                'profile_photo_position_x' => 50,
                'profile_photo_position_y' => 50,
            ]);
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', 'Foto profil dihapus. Avatar kembali menggunakan inisial nama.');
    }
}
