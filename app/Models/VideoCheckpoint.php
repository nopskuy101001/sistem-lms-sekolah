<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoCheckpoint extends Model
{
    protected $fillable = ['time_seconds', 'question_text', 'choices', 'correct_index', 'explanation'];
    protected $casts = ['choices' => 'array', 'time_seconds' => 'integer', 'correct_index' => 'integer'];
    public function lesson(): BelongsTo { return $this->belongsTo(VideoLesson::class, 'video_lesson_id'); }
    public function answers(): HasMany { return $this->hasMany(VideoAnswer::class); }
}
