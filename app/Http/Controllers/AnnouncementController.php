<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClassroom;
use App\Models\Announcement;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    use AuthorizesClassroom;

    public function store(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeManage($classroom);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $classroom->announcements()->create($data + ['user_id' => $request->user()->id]);

        return back()->with('success', 'Pengumuman diposting.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->authorizeManage($announcement->classroom);

        $announcement->delete();

        return back()->with('success', 'Pengumuman dihapus.');
    }
}
