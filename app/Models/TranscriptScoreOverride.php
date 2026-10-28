<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranscriptScoreOverride extends Model
{
    protected $fillable = [
        'classroom_id', 'user_id', 'activity_type', 'activity_id', 'score', 'reason', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['score' => 'decimal:2'];
    }
}
