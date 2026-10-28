<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClassroom;
use App\Models\Assignment;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    use AuthorizesClassroom;

    public function store(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeManage($classroom);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'due_at' => ['nullable', 'date'],
            'points' => ['required', 'integer', 'min:1', 'max:1000'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $assignment = new Assignment([
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'points' => $data['points'],
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $assignment->file_path = $file->store('assignments', 'local');
            $assignment->file_name = $file->getClientOriginalName();
        }

        $classroom->assignments()->save($assignment);

        return redirect()->route('assignments.show', $assignment)->with('success', 'Tugas dibuat.');
    }

    public function show(Assignment $assignment): View
    {
        $classroom = $assignment->classroom;
        $this->authorizeAccess($classroom);

        $user = auth()->user();
        $isManager = $classroom->canManage($user);

        if ($isManager) {
            $students = $classroom->students()->orderBy('name')->get();
            $submissions = $assignment->submissions()->get()->keyBy('user_id');

            return view('assignments.show', compact('assignment', 'classroom', 'isManager', 'students', 'submissions'));
        }

        $submission = $assignment->submissions()->where('user_id', $user->id)->first();

        return view('assignments.show', compact('assignment', 'classroom', 'isManager', 'submission'));
    }

    public function download(Assignment $assignment): StreamedResponse
    {
        $this->authorizeAccess($assignment->classroom);
        abort_unless($assignment->file_path && Storage::disk('local')->exists($assignment->file_path), 404);

        return Storage::disk('local')->download($assignment->file_path, $assignment->file_name);
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        $classroom = $assignment->classroom;
        $this->authorizeManage($classroom);

        // hapus file lampiran guru dan semua file pengumpulan siswa
        if ($assignment->file_path) {
            Storage::disk('local')->delete($assignment->file_path);
        }
        foreach ($assignment->submissions as $submission) {
            if ($submission->file_path) {
                Storage::disk('local')->delete($submission->file_path);
            }
        }
        $assignment->delete();

        return redirect()->route('classrooms.classwork', $classroom)->with('success', 'Tugas dihapus.');
    }
}
