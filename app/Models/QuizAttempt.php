<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class QuizAttempt extends Model
{
    protected $fillable = [
        'quiz_id', 'user_id', 'started_at', 'question_order', 'finished_at',
        'score', 'raw_score', 'penalty_score', 'is_graded',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'question_order' => 'array',
            'finished_at' => 'datetime',
            'score' => 'decimal:2',
            'raw_score' => 'decimal:2',
            'penalty_score' => 'decimal:2',
            'is_graded' => 'boolean',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(QuizSecurityEvent::class);
    }

    public function securityViolationCount(): int
    {
        return $this->securityEvents()
            ->whereIn('event_type', QuizSecurityEvent::VIOLATION_TYPES)
            ->count();
    }

    public function integrityLabel(?int $violationCount = null): string
    {
        $count = $violationCount ?? $this->securityViolationCount();

        return match (true) {
            $count === 0 => 'Aman',
            $count <= 2 => 'Perlu ditinjau',
            default => 'Risiko tinggi',
        };
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /** Urutan soal yang konsisten untuk satu siswa, termasuk setelah halaman dimuat ulang. */
    public function orderedQuestions(): Collection
    {
        $questions = $this->quiz->questions;
        $order = collect($this->question_order ?? [])->map(fn ($id) => (int) $id);

        if ($order->isEmpty()) {
            return $questions;
        }

        $questionsById = $questions->keyBy('id');
        $ordered = $order
            ->map(fn ($id) => $questionsById->get($id))
            ->filter()
            ->values();

        // Soal baru yang ditambahkan setelah percobaan dimulai tetap tampil di bagian akhir.
        return $ordered
            ->concat($questions->whereNotIn('id', $order->all()))
            ->values();
    }

    /** Hitung ulang nilai mentah, penalti integritas, nilai akhir, dan status penilaian. */
    public function recalculate(): void
    {
        $rawScore = round((float) $this->answers()->sum('score'), 2);
        $penaltyScore = $this->calculateViolationPenalty($rawScore);

        $this->raw_score = $rawScore;
        $this->penalty_score = $penaltyScore;
        $this->score = max(0, round($rawScore - $penaltyScore, 2));
        $this->is_graded = ! $this->answers()->whereNull('score')->exists();
        $this->save();
    }

    private function calculateViolationPenalty(float $rawScore): float
    {
        $quiz = $this->quiz;

        if (! $quiz->penalty_enabled || $rawScore <= 0) {
            return 0;
        }

        $totalPoints = $quiz->totalPoints();
        if ($totalPoints <= 0) {
            return 0;
        }

        $violationCount = $this->securityViolationCount();
        $penaltyPercent = min(
            $violationCount * $quiz->penalty_percent_per_violation,
            Quiz::MAX_VIOLATION_PENALTY_PERCENT
        );

        return round(min($rawScore, $totalPoints * ($penaltyPercent / 100)), 2);
    }
}
