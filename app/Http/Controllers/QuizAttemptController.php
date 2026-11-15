<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClassroom;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizSecurityEvent;
use App\Support\AcademicNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class QuizAttemptController extends Controller
{
    use AuthorizesClassroom;

    /** Siswa mulai mengerjakan kuis (1 kali percobaan). */
    public function start(Request $request, Quiz $quiz): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isSiswa() && $quiz->classroom->hasStudent($user), 403);

        $existing = $quiz->attempts()->where('user_id', $user->id)->first();
        if ($existing) {
            return $existing->isFinished()
                ? redirect()->route('attempts.show', $existing)
                : redirect()->route('attempts.take', $existing);
        }

        if (! $quiz->isOpen()) {
            return back()->withErrors(['quiz' => 'Kuis belum dibuka atau sudah ditutup.']);
        }

        if ($quiz->questions()->count() === 0) {
            return back()->withErrors(['quiz' => 'Kuis ini belum memiliki soal.']);
        }

        $attempt = $quiz->attempts()->create([
            'user_id' => $user->id,
            'started_at' => now(),
            'question_order' => $quiz->shuffle_questions ? $this->generateQuestionOrder($quiz) : null,
        ]);

        return redirect()->route('attempts.take', $attempt);
    }

    /** Halaman mengerjakan. */
    public function take(QuizAttempt $attempt): View|RedirectResponse
    {
        abort_unless($attempt->user_id === auth()->id(), 403);

        if ($attempt->isFinished()) {
            return redirect()->route('attempts.show', $attempt);
        }

        $quiz = $attempt->quiz->load('questions.group');
        if ($quiz->shuffle_questions && empty($attempt->question_order)) {
            $attempt->update(['question_order' => $this->generateQuestionOrder($quiz)]);
        }
        $quiz->setRelation('questions', $attempt->orderedQuestions());
        $deadline = $this->deadline($attempt);
        $savedAnswers = $attempt->answers()->get()->keyBy('question_id');
        $violationCount = $attempt->securityViolationCount();

        return view('quizzes.take-secure', compact('attempt', 'quiz', 'deadline', 'savedAnswers', 'violationCount'));
    }

    /** Simpan jawaban selama siswa mengerjakan agar aman dari gangguan koneksi. */
    public function saveAnswers(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->authorizeActiveAttempt($attempt);

        $deadline = $this->deadline($attempt);
        if ($deadline && now()->greaterThan($deadline)) {
            return response()->json(['message' => 'Waktu pengerjaan sudah habis.'], 409);
        }

        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable'],
            'answers.*.*' => ['nullable', 'string', 'max:10000'],
        ]);

        $this->persistAnswers($attempt, $data['answers'], true);

        return response()->json([
            'saved' => true,
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    /** Catat sinyal integritas dari browser. Ini adalah indikator, bukan vonis kecurangan. */
    public function securityEvent(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->authorizeActiveAttempt($attempt);

        $data = $request->validate([
            'event_type' => ['required', 'in:tab_hidden,window_blur,fullscreen_exit,focus_restored'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'metadata' => ['nullable', 'array'],
        ]);

        $canRecord = $attempt->quiz->monitoring_enabled
            || ($attempt->quiz->require_fullscreen && $data['event_type'] === 'fullscreen_exit');

        if (! $canRecord) {
            return response()->json([
                'recorded' => false,
                'violation_count' => $attempt->securityViolationCount(),
            ]);
        }

        $isDuplicate = $attempt->securityEvents()
            ->where('event_type', $data['event_type'])
            ->where('occurred_at', '>=', now()->subSeconds(2))
            ->exists();

        if (! $isDuplicate) {
            $metadata = collect($data['metadata'] ?? [])
                ->only(['reason', 'visibility_state', 'fullscreen', 'viewport'])
                ->map(fn ($value) => mb_substr((string) $value, 0, 200))
                ->all();

            $attempt->securityEvents()->create([
                'event_type' => $data['event_type'],
                'duration_seconds' => $data['duration_seconds'] ?? null,
                'metadata' => $metadata ?: null,
                'occurred_at' => now(),
            ]);
        }

        return response()->json([
            'recorded' => ! $isDuplicate,
            'violation_count' => $attempt->securityViolationCount(),
        ]);
    }

    /** Kumpulkan jawaban dan nilai otomatis untuk pilihan ganda. */
    public function submit(Request $request, QuizAttempt $attempt): RedirectResponse
    {
        abort_unless($attempt->user_id === auth()->id(), 403);

        if ($attempt->isFinished()) {
            return redirect()->route('attempts.show', $attempt);
        }

        $deadline = $this->deadline($attempt);
        $isExpired = $deadline && now()->greaterThan($deadline->copy()->addSeconds(5));

        if (! $isExpired) {
            $this->persistAnswers($attempt, (array) $request->input('answers', []), false);
        }

        $this->ensureAnswerRows($attempt);

        $attempt->finished_at = now();
        $attempt->save();
        $attempt->recalculate();

        AcademicNotifier::teacher(
            $attempt->quiz->classroom,
            'Kuis selesai dikerjakan',
            $request->user()->name.' menyelesaikan kuis '.$attempt->quiz->title.'.',
            route('attempts.show', $attempt, false),
            'submission',
        );

        return redirect()->route('attempts.show', $attempt)->with('success', 'Jawaban dikumpulkan.');
    }

    /** Hasil pengerjaan (siswa pemilik atau guru). */
    public function show(QuizAttempt $attempt): View|RedirectResponse
    {
        $quiz = $attempt->quiz;
        $classroom = $quiz->classroom;
        $user = auth()->user();
        $isManager = $classroom->canManage($user);

        abort_unless($isManager || $attempt->user_id === $user->id, 403);

        if (! $attempt->isFinished()) {
            return $isManager
                ? back()->withErrors(['attempt' => 'Siswa belum selesai mengerjakan.'])
                : redirect()->route('attempts.take', $attempt);
        }

        $attempt->load('user');
        $quiz->load('questions.group');
        $questions = $attempt->orderedQuestions();
        $answers = $attempt->answers()->get()->keyBy('question_id');
        $totalPoints = $quiz->totalPoints();
        $securityEvents = $attempt->securityEvents()->latest('occurred_at')->get();
        $violationCount = $securityEvents->whereIn('event_type', QuizSecurityEvent::VIOLATION_TYPES)->count();

        return view('quizzes.attempt-tka', compact(
            'attempt', 'quiz', 'classroom', 'questions', 'answers', 'totalPoints', 'isManager',
            'securityEvents', 'violationCount'
        ));
    }

    /** Ruang koreksi cepat untuk seluruh jawaban esai dalam satu kuis. */
    public function essayGrading(Request $request, Quiz $quiz): View|RedirectResponse
    {
        $quiz->load(['classroom', 'questions.group']);
        $this->authorizeManage($quiz->classroom);

        $essayQuestions = $quiz->questions
            ->where('type', Question::TYPE_ESAI)
            ->values();

        if ($essayQuestions->isEmpty()) {
            return redirect()->route('quizzes.show', $quiz)
                ->withErrors(['grading' => 'Mode koreksi esai hanya tersedia untuk kuis yang memiliki soal esai.']);
        }

        $essayQuestionIds = $essayQuestions->pluck('id');
        $attempts = $quiz->attempts()
            ->whereNotNull('finished_at')
            ->with('user')
            ->withCount(['answers as pending_essay_count' => fn ($query) => $query
                ->whereIn('question_id', $essayQuestionIds)
                ->whereNull('score')])
            ->orderBy('is_graded')
            ->orderBy('finished_at')
            ->get();

        $pendingCount = $attempts->where('pending_essay_count', '>', 0)->count();
        $statusFilter = in_array($request->query('status'), ['pending', 'graded', 'all'], true)
            ? $request->query('status')
            : ($pendingCount > 0 ? 'pending' : 'all');

        $listedAttempts = (match ($statusFilter) {
            'pending' => $attempts->where('pending_essay_count', '>', 0),
            'graded' => $attempts->where('pending_essay_count', 0),
            default => $attempts,
        })->values();

        $selectedAttempt = null;
        if ($request->filled('attempt')) {
            $selectedAttempt = $attempts->firstWhere('id', $request->integer('attempt'));
            abort_unless($selectedAttempt, 404);

            if (! $listedAttempts->contains('id', $selectedAttempt->id)) {
                $statusFilter = 'all';
                $listedAttempts = $attempts;
            }
        } else {
            $selectedAttempt = $listedAttempts->first();
        }

        if ($selectedAttempt) {
            $selectedAttempt->load(['answers' => fn ($query) => $query
                ->whereIn('question_id', $essayQuestionIds)]);
        }

        $answers = $selectedAttempt?->answers->keyBy('question_id') ?? collect();
        $gradedCount = $attempts->count() - $pendingCount;

        return view('quizzes.essay-grading', compact(
            'quiz', 'essayQuestions', 'attempts', 'listedAttempts', 'selectedAttempt',
            'answers', 'pendingCount', 'gradedCount', 'statusFilter'
        ));
    }

    /** Guru menilai jawaban esai. */
    public function grade(Request $request, QuizAttempt $attempt): RedirectResponse
    {
        $this->authorizeManage($attempt->quiz->classroom);

        $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['nullable', 'numeric', 'min:0'],
            'grading_mode' => ['sometimes', 'boolean'],
            'navigation' => ['sometimes', 'in:stay,next'],
        ]);

        if ($request->boolean('grading_mode')) {
            $essayAnswerIds = $attempt->answers()
                ->whereHas('question', fn ($query) => $query->where('type', Question::TYPE_ESAI))
                ->pluck('id');
            $submittedScores = $request->input('scores', []);
            $hasMissingScore = $essayAnswerIds->contains(fn ($answerId) => ! array_key_exists($answerId, $submittedScores)
                || $submittedScores[$answerId] === null
                || $submittedScores[$answerId] === '');

            if ($hasMissingScore) {
                return back()->withInput()->withErrors([
                    'scores' => 'Isi nilai untuk seluruh jawaban esai sebelum berpindah ke siswa berikutnya.',
                ]);
            }
        }

        $questions = $attempt->quiz->questions->keyBy('id');

        foreach ($request->input('scores') as $answerId => $score) {
            if ($score === null || $score === '') {
                continue;
            }

            $answer = $attempt->answers()->with('question')->find($answerId);
            if (! $answer) {
                continue;
            }

            $max = $questions[$answer->question_id]->points ?? 0;
            $answer->update(['score' => min((float) $score, $max)]);
        }

        $attempt->recalculate();

        AcademicNotifier::user(
            $attempt->user,
            'Nilai kuis diperbarui',
            'Penilaian '.$attempt->quiz->title.' sudah diperbarui oleh guru.',
            route('attempts.show', $attempt, false),
            'grade',
        );

        if ($request->boolean('grading_mode')) {
            $nextAttempt = null;
            if ($request->input('navigation') === 'next') {
                $nextAttempt = $attempt->quiz->attempts()
                    ->whereNotNull('finished_at')
                    ->where('id', '!=', $attempt->id)
                    ->where('is_graded', false)
                    ->orderBy('finished_at')
                    ->first();
            }

            $allReviewed = $request->input('navigation') === 'next' && ! $nextAttempt;
            $targetAttempt = $nextAttempt ?? $attempt;

            return redirect()->route('quizzes.essay-grading', [
                'quiz' => $attempt->quiz,
                'attempt' => $targetAttempt,
                'status' => $nextAttempt ? 'pending' : 'all',
            ])->with('success', $allReviewed
                ? 'Nilai esai disimpan. Semua jawaban yang masuk sudah diperiksa.'
                : 'Nilai esai disimpan.');
        }

        return back()->with('success', 'Nilai esai disimpan.');
    }

    /** Batas waktu pengerjaan (null jika tanpa batas). */
    private function deadline(QuizAttempt $attempt): ?Carbon
    {
        $quiz = $attempt->quiz;
        $deadline = null;

        if ($quiz->duration_minutes) {
            $deadline = $attempt->started_at->copy()->addMinutes($quiz->duration_minutes);
        }
        if ($quiz->end_at && ($deadline === null || $quiz->end_at->lessThan($deadline))) {
            $deadline = $quiz->end_at;
        }

        return $deadline;
    }

    private function authorizeActiveAttempt(QuizAttempt $attempt): void
    {
        abort_unless($attempt->user_id === auth()->id(), 403);
        abort_if($attempt->isFinished(), 409, 'Kuis sudah selesai.');
    }

    /** @param array<int|string, mixed> $input */
    private function persistAnswers(QuizAttempt $attempt, array $input, bool $onlyProvided): void
    {
        $questions = $attempt->quiz->questions;

        foreach ($questions as $question) {
            if ($onlyProvided && ! array_key_exists($question->id, $input)) {
                continue;
            }

            $rawAnswer = $input[$question->id] ?? null;
            $answer = null;

            $isCorrect = null;
            $score = null;

            if ($question->isPilihanGanda()) {
                $answer = is_string($rawAnswer) && array_key_exists($rawAnswer, $question->options())
                    ? $rawAnswer
                    : null;
                $isCorrect = $answer !== null && $answer === $question->correct_option;
                $score = $isCorrect ? $question->points : 0;
            } elseif ($question->isPilihanGandaKompleks()) {
                $selected = collect(is_array($rawAnswer) ? $rawAnswer : [])
                    ->filter(fn ($key) => is_string($key) && array_key_exists($key, $question->options()))
                    ->unique()->sort()->values()->all();
                $correct = collect($question->correct_options ?? [])->sort()->values()->all();
                $answer = json_encode($selected);
                $isCorrect = $selected === $correct;
                $score = $isCorrect ? $question->points : 0;
            } elseif ($question->isKategori()) {
                $items = collect($question->category_items ?? [])->values();
                $selected = [];
                $correctCount = 0;

                foreach ($items as $index => $item) {
                    $value = is_array($rawAnswer) ? ($rawAnswer[$index] ?? null) : null;
                    $selected[$index] = in_array($value, ['benar', 'salah'], true) ? $value : null;
                    if ($selected[$index] !== null && $selected[$index] === ($item['answer'] ?? null)) {
                        $correctCount++;
                    }
                }

                $answer = json_encode($selected);
                $isCorrect = $items->isNotEmpty() && $correctCount === $items->count();
                $score = $items->isEmpty()
                    ? 0
                    : round($question->points * ($correctCount / $items->count()), 2);
            } else {
                $answer = is_string($rawAnswer) ? mb_substr($rawAnswer, 0, 10000) : null;
            }

            QuizAnswer::updateOrCreate(
                ['quiz_attempt_id' => $attempt->id, 'question_id' => $question->id],
                ['answer' => $answer, 'is_correct' => $isCorrect, 'score' => $score]
            );
        }
    }

    private function ensureAnswerRows(QuizAttempt $attempt): void
    {
        foreach ($attempt->quiz->questions as $question) {
            QuizAnswer::firstOrCreate(
                ['quiz_attempt_id' => $attempt->id, 'question_id' => $question->id],
                [
                    'answer' => null,
                    'is_correct' => $question->isObjective() ? false : null,
                    'score' => $question->isObjective() ? 0 : null,
                ]
            );
        }
    }

    /** @return array<int, int> */
    private function generateQuestionOrder(Quiz $quiz): array
    {
        $questionBlocks = $quiz->questions()
            ->get(['id', 'question_group_id'])
            ->groupBy(fn (Question $question) => $question->question_group_id
                ? 'group-'.$question->question_group_id
                : 'question-'.$question->id)
            ->values()
            ->map(fn ($questions) => $questions->pluck('id')->map(fn ($id) => (int) $id)->values());

        $questionIds = $questionBlocks->flatten()->values();

        if ($questionIds->count() < 2) {
            return $questionIds->all();
        }

        $usedOrders = $quiz->attempts()
            ->whereNotNull('question_order')
            ->get(['question_order'])
            ->map(fn (QuizAttempt $attempt) => implode(',', $attempt->question_order ?? []))
            ->flip();

        $candidateBlocks = $questionBlocks->shuffle()->values();
        $candidate = $candidateBlocks->flatten()->values();
        if (! $usedOrders->has($candidate->implode(','))) {
            return $candidate->all();
        }

        // Coba rotasi untuk menghindari urutan identik pada siswa berikutnya.
        for ($offset = 1; $offset < $candidateBlocks->count(); $offset++) {
            $rotated = $candidateBlocks->slice($offset)
                ->concat($candidateBlocks->take($offset))
                ->flatten()->values();
            if (! $usedOrders->has($rotated->implode(','))) {
                return $rotated->all();
            }
        }

        // Jika semua rotasi sudah dipakai, cari kombinasi acak lain.
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $candidate = $questionBlocks->shuffle()->flatten()->values();
            if (! $usedOrders->has($candidate->implode(','))) {
                return $candidate->all();
            }
        }

        // Duplikasi tidak terhindarkan jika jumlah siswa melebihi jumlah kemungkinan susunan.
        return $candidate->all();
    }
}
