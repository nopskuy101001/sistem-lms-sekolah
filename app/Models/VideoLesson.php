<?php

namespace App\Models;

use App\Support\Lateness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoLesson extends Model
{
    protected $fillable = ['classroom_id', 'creator_id', 'title', 'description', 'due_at', 'source_type', 'source_url', 'file_path', 'is_published', 'allow_seeking', 'skip_seconds'];

    protected $casts = ['due_at' => 'datetime', 'is_published' => 'boolean', 'allow_seeking' => 'boolean', 'skip_seconds' => 'integer'];

    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'creator_id'); }
    public function checkpoints(): HasMany { return $this->hasMany(VideoCheckpoint::class)->orderBy('time_seconds')->orderBy('id'); }
    public function attempts(): HasMany { return $this->hasMany(VideoAttempt::class); }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && now()->greaterThan($this->due_at);
    }

    public function overdueByLabel(): ?string
    {
        return $this->isOverdue() ? Lateness::label(Lateness::seconds($this->due_at, now())) : null;
    }

    public function getPlaybackUrlAttribute(): string
    {
        return $this->file_path ? asset('storage/'.$this->file_path) : $this->source_url;
    }
}
