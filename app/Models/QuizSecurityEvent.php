<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizSecurityEvent extends Model
{
    public const VIOLATION_TYPES = [
        'tab_hidden',
        'window_blur',
        'fullscreen_exit',
    ];

    protected $fillable = [
        'quiz_attempt_id', 'event_type', 'duration_seconds', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function label(): string
    {
        return match ($this->event_type) {
            'tab_hidden' => 'Keluar dari tab kuis',
            'window_blur' => 'Jendela kehilangan fokus',
            'fullscreen_exit' => 'Keluar dari layar penuh',
            'focus_restored' => 'Kembali ke halaman kuis',
            default => 'Aktivitas tidak dikenal',
        };
    }

    public function isViolation(): bool
    {
        return in_array($this->event_type, self::VIOLATION_TYPES, true);
    }
}
