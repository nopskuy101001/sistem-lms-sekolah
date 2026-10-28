<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClassroom;
use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    use AuthorizesClassroom;

    /** Siswa mengumpulkan (atau mengumpulkan ulang) tugas. */
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isSiswa() && $assignment->classroom->hasStudent($user), 403);

        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:10000'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        if (blank($data['content'] ?? null) && ! $request->hasFile('file')) {
            return back()->withErrors(['content' => 'Isi jawaban atau lampirkan file.'])->withInput();
        }

        $submission = Submission::firstOrNew([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
        ]);

        if ($submission->exists && $submission->score !== null) {
            return back()->withErrors(['content' => 'Tugas sudah dinilai, tidak bisa dikumpulkan ulang.']);
        }

        if ($request->hasFile('file')) {
            if ($submission->file_path) {
                Storage::disk('local')->delete($submission->file_path);
            }
            $file = $request->file('file');
            $submission->file_path = $file->store('submissions', 'local');
            $submission->file_name = $file->getClientOriginalName();
        }

        $submission->content = $data['content'] ?? null;
        $submission->submitted_at = now();
        $submission->save();

        return back()->with('success', 'Tugas berhasil dikumpulkan.');
    }

    /** Guru memberi nilai dan komentar. */
    public function grade(Request $request, Submission $submission): RedirectResponse
    {
        $assignment = $submission->assignment;
        $this->authorizeManage($assignment->classroom);

        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$assignment->points],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission->update($data);

        return back()->with('success', 'Nilai disimpan.');
    }

    public function download(Submission $submission): StreamedResponse
    {
        $user = auth()->user();
        $classroom = $submission->assignment->classroom;

        abort_unless($classroom->canManage($user) || $submission->user_id === $user->id, 403);
        abort_unless($submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);

        return Storage::disk('local')->download($submission->file_path, $submission->file_name);
    }
}
