<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\QuizAttempt;
use App\Models\TranscriptScoreOverride;
use App\Models\TranscriptState;
use App\Models\Submission;
use App\Models\VideoAttempt;
use App\Models\User;

class TranscriptReportService
{
    public function build(Classroom $classroom): array
    {
        return $this->buildReport($classroom);
    }

    /** A student's report never exposes classmates, draft activities, or internal correction notes. */
    public function buildForStudent(Classroom $classroom, User $student): array
    {
        abort_unless($student->isSiswa() && $student->joinedClassrooms()->whereKey($classroom->id)->exists(), 403);
        $state = TranscriptState::where('classroom_id', $classroom->id)->first();
        abort_unless($state?->finalized_at && is_array($state->snapshot), 404, 'Transkrip kelas belum divalidasi guru.');
        $report = $this->buildReport($classroom, $student->id);
        $report['rows'] = $report['rows']->map(function ($row) {
            foreach ($row['scores'] as &$score) {
                unset($score['reason']);
            }
            unset($score);
            return $row;
        })->values();
        return $report;
    }

    private function buildReport(Classroom $classroom, ?int $studentId = null): array
    {
        $state = TranscriptState::where('classroom_id', $classroom->id)->first();
        if ($state?->finalized_at && is_array($state->snapshot)) {
            $report = $this->hydrateSnapshot($classroom, $state);
            return $studentId === null ? $report : $this->scopeStudentSnapshot($report, $studentId);
        }

        $overrides = TranscriptScoreOverride::where('classroom_id', $classroom->id)
            ->when($studentId !== null, fn ($query) => $query->where('user_id', $studentId))->get()
            ->keyBy(fn ($item) => $item->user_id.'|'.$item->activity_type.'-'.$item->activity_id);

        $classroom->loadMissing('teacher');
        $students = $classroom->students()->when($studentId !== null, fn ($query) => $query->where('users.id', $studentId))->orderBy('name')->get();
        $assignments = $classroom->assignments()->orderBy('created_at')->get();
        $quizzes = $classroom->quizzes()
            ->when($studentId !== null, fn ($query) => $query->where('is_published', true))
            ->withSum('questions', 'points')
            ->orderBy('created_at')
            ->get();

        $videoLessons = $classroom->videoLessons()
            ->when($studentId !== null, fn ($query) => $query->where('is_published', true))
            ->orderBy('created_at')->get();

        $submissions = Submission::query()
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->whereIn('user_id', $students->pluck('id'))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->keyBy('assignment_id'));

        $attempts = QuizAttempt::query()
            ->whereIn('quiz_id', $quizzes->pluck('id'))
            ->whereIn('user_id', $students->pluck('id'))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->keyBy('quiz_id'));

        $videoAttempts = VideoAttempt::query()
            ->whereIn('video_lesson_id', $videoLessons->pluck('id'))
            ->whereIn('user_id', $students->pluck('id'))
            ->get()->groupBy('user_id')
            ->map(fn ($rows) => $rows->keyBy('video_lesson_id'));

        $activities = $assignments->map(fn ($assignment) => [
            'key' => 'assignment-'.$assignment->id,
            'student_visible' => true,
            'type' => 'Tugas',
            'title' => $assignment->title,
            'maximum' => (float) $assignment->points,
        ])->concat($quizzes->map(fn ($quiz) => [
            'key' => 'quiz-'.$quiz->id,
            'student_visible' => (bool) $quiz->is_published,
            'type' => 'Kuis',
            'title' => $quiz->title,
            'maximum' => (float) ($quiz->questions_sum_points ?? 0),
        ]))->concat($videoLessons->map(fn ($lesson) => [
            'key' => 'video-'.$lesson->id,
            'student_visible' => (bool) $lesson->is_published,
            'type' => 'Video Interaktif',
            'title' => $lesson->title,
            'maximum' => 100.0,
        ]))->values();

        $rows = $students->map(function ($student) use ($assignments, $quizzes, $videoLessons, $submissions, $attempts, $videoAttempts, $overrides) {
            $scores = [];

            foreach ($assignments as $assignment) {
                $submission = $submissions->get($student->id)?->get($assignment->id);
                $score = $submission?->score;
                $maximum = (float) $assignment->points;
                $scores['assignment-'.$assignment->id] = [
                    'score' => $score !== null ? (float) $score : null,
                    'maximum' => $maximum,
                    'percentage' => $score !== null && $maximum > 0 ? round(((float) $score / $maximum) * 100, 2) : null,
                    'status' => $score !== null ? 'Dinilai' : ($submission ? 'Belum dinilai' : 'Belum dikumpulkan'),
                    'final' => $score !== null,
                ];
            }

            foreach ($quizzes as $quiz) {
                $attempt = $attempts->get($student->id)?->get($quiz->id);
                $maximum = (float) ($quiz->questions_sum_points ?? 0);
                $hasScore = $attempt?->isFinished() && $maximum > 0;
                $scores['quiz-'.$quiz->id] = [
                    'score' => $hasScore ? (float) $attempt->score : null,
                    'maximum' => $maximum,
                    'percentage' => $hasScore ? round(((float) $attempt->score / $maximum) * 100, 2) : null,
                    'status' => ! $attempt
                        ? ($quiz->end_at && now()->greaterThan($quiz->end_at) ? 'Tidak mengerjakan (waktu habis)' : 'Belum dikerjakan')
                        : (! $attempt->isFinished()
                            ? ($quiz->end_at && now()->greaterThan($quiz->end_at) ? 'Tidak selesai (waktu habis)' : 'Sedang mengerjakan')
                            : ($attempt->is_graded ? 'Dinilai' : 'Menunggu esai')),
                    'final' => $hasScore && (bool) $attempt->is_graded,
                ];
            }

            foreach ($videoLessons as $lesson) {
                $attempt = $videoAttempts->get($student->id)?->get($lesson->id);
                $finished = $attempt && $attempt->completed_at !== null;
                $scores['video-'.$lesson->id] = [
                    'score' => $finished ? (float) $attempt->score : null,
                    'maximum' => 100.0,
                    'percentage' => $finished ? (float) $attempt->score : null,
                    'status' => ! $attempt ? 'Belum dikerjakan' : ($finished ? 'Dinilai' : 'Sedang mengerjakan'),
                    'final' => (bool) $finished,
                ];
            }

            foreach ($scores as $key => &$item) {
                $manual = $overrides->get($student->id.'|'.$key);
                if ($manual) {
                    $item['score'] = (float) $manual->score;
                    $item['percentage'] = $item['maximum'] > 0
                        ? round(((float) $manual->score / $item['maximum']) * 100, 2)
                        : null;
                    $item['status'] = 'Koreksi guru';
                    $item['final'] = true;
                    $item['manual'] = true;
                    $item['reason'] = $manual->reason;
                } else {
                    $item['manual'] = false;
                    $item['reason'] = null;
                }
            }
            unset($item);

            $finalPercentages = collect($scores)
                ->filter(fn ($item) => $item['final'] && $item['percentage'] !== null)
                ->pluck('percentage');

            return [
                'student' => $student,
                'scores' => $scores,
                'completed' => $finalPercentages->count(),
                'average' => $finalPercentages->isNotEmpty() ? round($finalPercentages->average(), 2) : null,
            ];
        });

        $classAverage = $rows->pluck('average')->filter(fn ($value) => $value !== null)->avg();

        return [
            'classroom' => $classroom,
            'activities' => $activities,
            'rows' => $rows,
            'student_count' => $students->count(),
            'activity_count' => $activities->count(),
            'class_average' => $classAverage !== null ? round($classAverage, 2) : null,
            'finalized' => false,
            'finalized_at' => null,
        ];
    }

    private function scopeStudentSnapshot(array $report, int $studentId): array
    {
        // Older snapshots lack visibility metadata. Only expose their currently published sources.
        $legacyKeys = [];
        if ($report['activities']->contains(fn ($activity) => ! array_key_exists('student_visible', $activity))) {
            $classroom = $report['classroom'];
            $legacyKeys = $classroom->assignments()->pluck('id')->map(fn ($id) => 'assignment-'.$id)
                ->concat($classroom->quizzes()->where('is_published', true)->pluck('id')->map(fn ($id) => 'quiz-'.$id))
                ->concat($classroom->videoLessons()->where('is_published', true)->pluck('id')->map(fn ($id) => 'video-'.$id))
                ->all();
        }
        $report['activities'] = $report['activities']->filter(fn ($activity) =>
            $activity['student_visible'] ?? in_array($activity['key'], $legacyKeys, true)
        )->values();
        $keys = array_flip($report['activities']->pluck('key')->all());
        $report['rows'] = $report['rows']->filter(fn ($row) => (int) $row['student']->id === $studentId)
            ->map(function ($row) use ($keys) {
                $row['scores'] = array_intersect_key($row['scores'], $keys);
                $percentages = collect($row['scores'])->filter(fn ($score) => $score['final'] && $score['percentage'] !== null)->pluck('percentage');
                $row['completed'] = $percentages->count();
                $row['average'] = $percentages->isEmpty() ? null : round($percentages->avg(), 2);
                return $row;
            })->values();
        $report['student_count'] = $report['rows']->count();
        $report['activity_count'] = $report['activities']->count();
        $report['class_average'] = $report['rows']->first()['average'] ?? null;

        return $report;
    }

    /** Save a stable academic report, independent of future source activity edits. */
    public function snapshot(array $report): array
    {
        return [
            'activities' => $report['activities']->all(),
            'rows' => $report['rows']->map(fn ($row) => [
                'student' => [
                    'id' => $row['student']->id,
                    'name' => $row['student']->name,
                    'nomor_induk' => $row['student']->nomor_induk,
                ],
                'scores' => $row['scores'],
                'completed' => $row['completed'],
                'average' => $row['average'],
            ])->all(),
            'student_count' => $report['student_count'],
            'activity_count' => $report['activity_count'],
            'class_average' => $report['class_average'],
        ];
    }

    private function hydrateSnapshot(Classroom $classroom, TranscriptState $state): array
    {
        $data = $state->snapshot;

        return [
            'classroom' => $classroom->loadMissing('teacher'),
            'activities' => collect($data['activities'] ?? [])->map(function ($activity) {
                $activity['maximum'] = (float) ($activity['maximum'] ?? 0);
                return $activity;
            }),
            'rows' => collect($data['rows'] ?? [])->map(function ($row) {
                $row['student'] = (object) $row['student'];
                $row['scores'] = collect($row['scores'] ?? [])->map(function ($score) {
                    foreach (['score', 'maximum', 'percentage'] as $field) {
                        if (array_key_exists($field, $score) && $score[$field] !== null) {
                            $score[$field] = (float) $score[$field];
                        }
                    }

                    return $score;
                })->all();
                $row['average'] = isset($row['average']) ? (float) $row['average'] : null;

                return $row;
            }),
            'student_count' => $data['student_count'] ?? 0,
            'activity_count' => $data['activity_count'] ?? 0,
            'class_average' => isset($data['class_average']) ? (float) $data['class_average'] : null,
            'finalized' => true,
            'finalized_at' => $state->finalized_at,
        ];
    }
}
