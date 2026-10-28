<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class QuestionBankService
{
    private const QUESTION_FIELDS = [
        'type',
        'body',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_option',
        'correct_options',
        'category_items',
        'competency',
        'subcompetency',
        'cognitive_level',
        'points',
    ];

    public function saveQuiz(Quiz $quiz, User $teacher, string $title): QuestionBank
    {
        $quiz->loadMissing(['classroom', 'questionGroups', 'questions']);
        $copiedMedia = [];

        try {
            $groupKeys = [];
            $groups = $quiz->questionGroups->values()->map(function ($group, int $index) use (&$copiedMedia, &$groupKeys) {
                $key = 'group-'.($index + 1);
                $groupKeys[$group->id] = $key;
                $mediaPath = $this->copyMedia($group->media_path, 'question-bank-stimuli');
                if ($mediaPath) {
                    $copiedMedia[] = $mediaPath;
                }

                return [
                    'key' => $key,
                    'title' => $group->title,
                    'stimulus' => $group->stimulus,
                    'media_path' => $mediaPath,
                    'media_name' => $group->media_name,
                    'media_alt' => $group->media_alt,
                    'table_data' => $group->table_data,
                ];
            })->all();

            $questions = $quiz->questions->map(function ($question) use ($groupKeys) {
                $data = $question->only(self::QUESTION_FIELDS);
                $data['group_key'] = $question->question_group_id
                    ? ($groupKeys[$question->question_group_id] ?? null)
                    : null;

                return $data;
            })->all();

            return QuestionBank::create([
                'teacher_id' => $teacher->id,
                'title' => $title,
                'description' => $quiz->description,
                'source_classroom' => $quiz->classroom->name,
                'question_count' => count($questions),
                'total_points' => (int) collect($questions)->sum('points'),
                'payload' => [
                    'version' => 1,
                    'quiz' => [
                        'title' => $quiz->title,
                        'description' => $quiz->description,
                        'duration_minutes' => $quiz->duration_minutes,
                        'monitoring_enabled' => $quiz->monitoring_enabled,
                        'require_fullscreen' => $quiz->require_fullscreen,
                        'penalty_enabled' => $quiz->penalty_enabled,
                        'penalty_percent_per_violation' => $quiz->penalty_percent_per_violation,
                        'shuffle_questions' => $quiz->shuffle_questions,
                    ],
                    'groups' => $groups,
                    'questions' => $questions,
                ],
            ]);
        } catch (Throwable $exception) {
            $this->deleteMedia($copiedMedia);
            throw $exception;
        }
    }

    public function createQuiz(QuestionBank $bank, Classroom $classroom, ?string $title = null): Quiz
    {
        $payload = $bank->payload;
        $quizData = $payload['quiz'] ?? [];
        $copiedMedia = [];

        try {
            return DB::transaction(function () use ($bank, $classroom, $title, $payload, $quizData, &$copiedMedia) {
                $quiz = $classroom->quizzes()->create([
                    'title' => filled($title) ? trim($title) : ($quizData['title'] ?? $bank->title),
                    'description' => $quizData['description'] ?? $bank->description,
                    'duration_minutes' => $quizData['duration_minutes'] ?? null,
                    'monitoring_enabled' => (bool) ($quizData['monitoring_enabled'] ?? true),
                    'require_fullscreen' => (bool) ($quizData['require_fullscreen'] ?? true),
                    'penalty_enabled' => (bool) ($quizData['penalty_enabled'] ?? false),
                    'penalty_percent_per_violation' => (int) ($quizData['penalty_percent_per_violation'] ?? 2),
                    'shuffle_questions' => (bool) ($quizData['shuffle_questions'] ?? true),
                    'is_published' => false,
                    'start_at' => null,
                    'end_at' => null,
                ]);

                $groupIds = [];
                foreach ($payload['groups'] ?? [] as $groupData) {
                    $mediaPath = $this->copyMedia($groupData['media_path'] ?? null, 'quiz-stimuli');
                    if ($mediaPath) {
                        $copiedMedia[] = $mediaPath;
                    }

                    $group = $quiz->questionGroups()->create([
                        'title' => $groupData['title'] ?? null,
                        'stimulus' => (string) ($groupData['stimulus'] ?? ''),
                        'media_path' => $mediaPath,
                        'media_name' => $groupData['media_name'] ?? null,
                        'media_alt' => $groupData['media_alt'] ?? null,
                        'table_data' => $groupData['table_data'] ?? null,
                    ]);
                    $groupIds[$groupData['key']] = $group->id;
                }

                foreach ($payload['questions'] ?? [] as $questionData) {
                    $data = collect($questionData)->only(self::QUESTION_FIELDS)->all();
                    $data['question_group_id'] = isset($questionData['group_key'])
                        ? ($groupIds[$questionData['group_key']] ?? null)
                        : null;
                    $quiz->questions()->create($data);
                }

                return $quiz;
            });
        } catch (Throwable $exception) {
            $this->deleteMedia($copiedMedia);
            throw $exception;
        }
    }

    public function delete(QuestionBank $bank): void
    {
        $paths = collect($bank->payload['groups'] ?? [])->pluck('media_path')->filter()->all();
        $bank->delete();
        $this->deleteMedia($paths);
    }

    private function copyMedia(?string $source, string $directory): ?string
    {
        if (! $source || ! Storage::disk('local')->exists($source)) {
            return null;
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION);
        $target = $directory.'/'.Str::uuid().($extension ? '.'.$extension : '');
        if (! Storage::disk('local')->copy($source, $target)) {
            throw new RuntimeException('Gagal menyalin media stimulus bank soal.');
        }

        return $target;
    }

    /** @param array<int, string> $paths */
    private function deleteMedia(array $paths): void
    {
        collect($paths)->filter()->each(fn (string $path) => Storage::disk('local')->delete($path));
    }
}
