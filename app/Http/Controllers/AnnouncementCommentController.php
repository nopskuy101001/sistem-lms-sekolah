<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClassroom;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Support\AcademicNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnnouncementCommentController extends Controller
{
    use AuthorizesClassroom;

    public function store(Request $request, Announcement $announcement): RedirectResponse
    {
        $classroom = $announcement->classroom;
        $this->authorizeAccess($classroom);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $announcement->comments()->create([
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ]);
        $url = route('classrooms.show', $classroom, false).'#announcement-'.$announcement->id;
        if ($request->user()->isSiswa()) {
            AcademicNotifier::teacher(
                $classroom,
                'Balasan pengumuman baru',
                $request->user()->name.' membalas pengumuman di '.$classroom->name.'.',
                $url,
                'announcement',
            );
        } else {
            AcademicNotifier::students(
                $classroom,
                'Guru membalas diskusi kelas',
                $request->user()->name.' menulis balasan di '.$classroom->name.'.',
                $url,
                'announcement',
            );
        }

        return back()->with('success', 'Pesan diskusi berhasil dikirim.')->withFragment('announcement-'.$announcement->id);
    }

    public function destroy(Request $request, AnnouncementComment $announcementComment): RedirectResponse
    {
        $announcementComment->loadMissing('announcement.classroom');
        $classroom = $announcementComment->announcement->classroom;
        $this->authorizeAccess($classroom);
        abort_unless(
            $classroom->canManage($request->user()) || $announcementComment->user_id === $request->user()->id,
            403,
        );
        $announcementId = $announcementComment->announcement_id;
        $announcementComment->delete();

        return back()->with('success', 'Pesan diskusi dihapus.')->withFragment('announcement-'.$announcementId);
    }
}
