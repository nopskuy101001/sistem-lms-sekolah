<?php

namespace App\Services;

use App\Models\VideoAttempt;
use App\Models\VideoLesson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VideoAnswerService
{
    public function submit(VideoLesson $lesson, int $userId, int $checkpointId, int $selectedIndex): array
    {
        return DB::transaction(function () use ($lesson, $userId, $checkpointId, $selectedIndex) {
            $attempt = VideoAttempt::where('video_lesson_id', $lesson->id)
                ->where('user_id', $userId)->lockForUpdate()->first();
            if (! $attempt) {
                throw ValidationException::withMessages(['answer' => 'Buka pemutar video terlebih dahulu.']);
            }

            $checkpoint = $lesson->checkpoints()->whereKey($checkpointId)->firstOrFail();
            $old = $attempt->answers()->where('video_checkpoint_id', $checkpoint->id)->first();
            if ($old) {
                return $this->feedback($lesson, $attempt, $checkpoint, $old);
            }
            if ($attempt->completed_at) {
                throw ValidationException::withMessages(['answer' => 'Video ini sudah diselesaikan.']);
            }
            $answeredIds = $attempt->answers()->pluck('video_checkpoint_id');
            $next = $lesson->checkpoints()->whereNotIn('id', $answeredIds)->first();
            if (! $next || $next->id !== $checkpoint->id) {
                throw ValidationException::withMessages(['answer' => 'Jawab pertanyaan sesuai urutan checkpoint video.']);
            }
            $answer = $attempt->answers()->create([
                'video_checkpoint_id' => $checkpoint->id,
                'selected_index' => $selectedIndex,
                'is_correct' => $selectedIndex === $checkpoint->correct_index,
            ]);
            $count = $lesson->checkpoints()->count();
            if ($count > 0 && $attempt->answers()->count() >= $count) {
                $correct = $attempt->answers()->where('is_correct', true)->count();
                $attempt->update(['score' => round($correct * 100 / $count, 2), 'completed_at' => now()]);
            }
            return $this->feedback($lesson, $attempt, $checkpoint, $answer);
        });
    }

    private function feedback($lesson, $attempt, $checkpoint, $answer): array
    {
        $total = $lesson->checkpoints()->count();
        return [
            'correct' => (bool) $answer->is_correct,
            'correct_index' => $checkpoint->correct_index,
            'explanation' => $checkpoint->explanation,
            'answered_count' => $attempt->answers()->count(),
            'total_count' => $total,
            'completed' => $attempt->completed_at !== null,
            'score' => $attempt->completed_at ? (float) $attempt->score : null,
            'result_url' => route('video-lessons.show', $lesson),
        ];
    }
}
