<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClassroom;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizSecurityEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class QuizController extends Controller
{
    use AuthorizesClassroom;

    public function store(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->authorizeManage($classroom);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
        ]);

        $quiz = $classroom->quizzes()->create($data);

        return redirect()->route('quizzes.show', $quiz)->with('success', 'Kuis dibuat. Tambahkan soal, lalu publikasikan.');
    }

    public function show(Quiz $quiz): View
    {
        $classroom = $quiz->classroom;
        $this->authorizeAccess($classroom);

        $user = auth()->user();
        $isManager = $classroom->canManage($user);

        if ($isManager) {
            $quiz->load(['questions.group', 'questionGroups']);
            $attempts = $quiz->attempts()
                ->with('user')
                ->withCount(['securityEvents as violation_count' => fn ($query) => $query
                    ->whereIn('event_type', QuizSecurityEvent::VIOLATION_TYPES)])
                ->orderByDesc('finished_at')
                ->get();

            return view('quizzes.show-tka', compact('quiz', 'classroom', 'isManager', 'attempts'));
        }

        // siswa tidak boleh melihat kuis yang belum dipublikasikan
        abort_unless($quiz->is_published, 404);

        $attempt = $quiz->attempts()->where('user_id', $user->id)->first();
        $questionCount = $quiz->questions()->count();

        return view('quizzes.show-tka', compact('quiz', 'classroom', 'isManager', 'attempt', 'questionCount'));
    }

    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeManage($quiz->classroom);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
        ]);

        $quiz->update($data);

        return back()->with('success', 'Pengaturan kuis diperbarui.');
    }

    public function updateSecurity(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeManage($quiz->classroom);

        $request->validate([
            'penalty_percent_per_violation' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $monitoringEnabled = $request->boolean('monitoring_enabled');
        $fullscreenRequired = $request->boolean('require_fullscreen');
        $shuffleQuestions = $request->boolean('shuffle_questions');
        $penaltyEnabled = $request->boolean('penalty_enabled');
        $penaltyPercent = (int) $request->input(
            'penalty_percent_per_violation',
            $quiz->penalty_percent_per_violation
        );

        if ($penaltyEnabled && ! $monitoringEnabled && ! $fullscreenRequired) {
            return back()->withErrors([
                'penalty_enabled' => 'Aktifkan pantau fokus atau wajib layar penuh agar pelanggaran dapat dicatat.',
            ]);
        }

        if ($quiz->attempts()->exists() && $quiz->shuffle_questions !== $shuffleQuestions) {
            return back()->withErrors([
                'shuffle_questions' => 'Pengacakan soal tidak dapat diubah karena sudah ada siswa yang mulai mengerjakan.',
            ]);
        }

        if ($quiz->attempts()->exists() && (
            $quiz->penalty_enabled !== $penaltyEnabled
            || $quiz->penalty_percent_per_violation !== $penaltyPercent
        )) {
            return back()->withErrors([
                'penalty_enabled' => 'Kebijakan potongan nilai tidak dapat diubah karena sudah ada siswa yang mulai mengerjakan.',
            ]);
        }

        $quiz->update([
            'monitoring_enabled' => $monitoringEnabled,
            'require_fullscreen' => $fullscreenRequired,
            'penalty_enabled' => $penaltyEnabled,
            'penalty_percent_per_violation' => $penaltyPercent,
            'shuffle_questions' => $shuffleQuestions,
        ]);

        return back()->with('success', 'Pengaturan keamanan kuis diperbarui.');
    }

    /** Publikasikan / tarik kembali kuis. */
    public function togglePublish(Quiz $quiz): RedirectResponse
    {
        $this->authorizeManage($quiz->classroom);

        if (! $quiz->is_published && $quiz->questions()->count() === 0) {
            return back()->withErrors(['publish' => 'Tambahkan minimal satu soal sebelum mempublikasikan kuis.']);
        }

        $quiz->update(['is_published' => ! $quiz->is_published]);

        return back()->with('success', $quiz->is_published ? 'Kuis dipublikasikan.' : 'Kuis ditarik kembali (draft).');
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        $classroom = $quiz->classroom;
        $this->authorizeManage($classroom);

        $quiz->questionGroups()
            ->whereNotNull('media_path')
            ->pluck('media_path')
            ->each(fn ($path) => Storage::disk('local')->delete($path));
        $quiz->delete();

        return redirect()->route('classrooms.classwork', $classroom)->with('success', 'Kuis dihapus.');
    }
}
