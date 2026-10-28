<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionBank extends Model
{
    protected $fillable = [
        'teacher_id',
        'title',
        'description',
        'source_classroom',
        'question_count',
        'total_points',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'question_count' => 'integer',
            'total_points' => 'integer',
            'payload' => 'array',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
