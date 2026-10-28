<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoAnswer extends Model
{
    protected $fillable = ['video_checkpoint_id', 'selected_index', 'is_correct'];
    protected $casts = ['selected_index' => 'integer', 'is_correct' => 'boolean'];
    public function checkpoint(): BelongsTo { return $this->belongsTo(VideoCheckpoint::class, 'video_checkpoint_id'); }
    public function attempt(): BelongsTo { return $this->belongsTo(VideoAttempt::class, 'video_attempt_id'); }
}
