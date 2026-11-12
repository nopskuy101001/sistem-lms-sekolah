<?php

namespace App\Models;

use App\Support\Lateness;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoAttempt extends Model
{
    protected $fillable = ['video_lesson_id', 'user_id', 'score', 'completed_at', 'late_seconds'];
    protected $casts = ['score' => 'decimal:2', 'completed_at' => 'datetime', 'late_seconds' => 'integer'];
    public function lesson(): BelongsTo { return $this->belongsTo(VideoLesson::class, 'video_lesson_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function answers(): HasMany { return $this->hasMany(VideoAnswer::class); }

    public function lateBySeconds(?CarbonInterface $deadline = null): int
    {
        if ($this->late_seconds !== null) {
            return (int) $this->late_seconds;
        }

        return Lateness::seconds($deadline ?? $this->lesson->due_at, $this->completed_at);
    }

    public function lateByLabel(?CarbonInterface $deadline = null): ?string
    {
        return Lateness::label($this->lateBySeconds($deadline));
    }

    public function isLate(?CarbonInterface $deadline = null): bool
    {
        return $this->lateBySeconds($deadline) > 0;
    }
}
