<?php

namespace App\Models;

use App\Support\Lateness;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    protected $fillable = [
        'assignment_id', 'user_id', 'content', 'file_path', 'file_name',
        'submitted_at', 'late_seconds', 'score', 'feedback',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'late_seconds' => 'integer',
            'score' => 'decimal:2',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLate(?CarbonInterface $deadline = null): bool
    {
        return $this->lateBySeconds($deadline) > 0;
    }

    public function lateBySeconds(?CarbonInterface $deadline = null): int
    {
        if ($this->late_seconds !== null) {
            return (int) $this->late_seconds;
        }

        return Lateness::seconds($deadline ?? $this->assignment->due_at, $this->submitted_at);
    }

    public function lateByLabel(?CarbonInterface $deadline = null): ?string
    {
        return Lateness::label($this->lateBySeconds($deadline));
    }
}
