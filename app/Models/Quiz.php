<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    public const MAX_VIOLATION_PENALTY_PERCENT = 20;

    protected $fillable = [
        'classroom_id', 'title', 'description', 'duration_minutes',
        'start_at', 'end_at', 'is_published', 'monitoring_enabled', 'require_fullscreen',
        'penalty_enabled', 'penalty_percent_per_violation', 'shuffle_questions',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_published' => 'boolean',
            'monitoring_enabled' => 'boolean',
            'require_fullscreen' => 'boolean',
            'penalty_enabled' => 'boolean',
            'penalty_percent_per_violation' => 'integer',
            'shuffle_questions' => 'boolean',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('id');
    }

    public function questionGroups(): HasMany
    {
        return $this->hasMany(QuestionGroup::class)->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function totalPoints(): int
    {
        return (int) $this->questions()->sum('points');
    }

    /** Apakah kuis sedang bisa dikerjakan siswa? */
    public function isOpen(): bool
    {
        if (! $this->is_published) {
            return false;
        }
        if ($this->start_at && now()->lessThan($this->start_at)) {
            return false;
        }
        if ($this->end_at && now()->greaterThan($this->end_at)) {
            return false;
        }

        return true;
    }

    public function statusLabel(): string
    {
        if (! $this->is_published) {
            return 'Draft';
        }
        if ($this->start_at && now()->lessThan($this->start_at)) {
            return 'Belum dibuka';
        }
        if ($this->end_at && now()->greaterThan($this->end_at)) {
            return 'Ditutup';
        }

        return 'Dibuka';
    }
}
