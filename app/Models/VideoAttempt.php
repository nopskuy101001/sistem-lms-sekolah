<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoAttempt extends Model
{
    protected $fillable = ['video_lesson_id', 'user_id', 'score', 'completed_at'];
    protected $casts = ['score' => 'decimal:2', 'completed_at' => 'datetime'];
    public function lesson(): BelongsTo { return $this->belongsTo(VideoLesson::class, 'video_lesson_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function answers(): HasMany { return $this->hasMany(VideoAnswer::class); }
}
