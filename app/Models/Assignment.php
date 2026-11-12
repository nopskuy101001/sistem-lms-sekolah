<?php

namespace App\Models;

use App\Support\Lateness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = [
        'classroom_id', 'title', 'instructions', 'due_at', 'points', 'file_path', 'file_name',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && now()->greaterThan($this->due_at);
    }

    public function overdueByLabel(): ?string
    {
        return $this->isOverdue() ? Lateness::label(Lateness::seconds($this->due_at, now())) : null;
    }
}
