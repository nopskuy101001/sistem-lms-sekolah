<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherRankHistory extends Model
{
    protected $fillable = ['teacher_profile_id', 'rank_grade', 'effective_date', 'is_current'];

    protected function casts(): array
    {
        return ['effective_date' => 'date', 'is_current' => 'boolean'];
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }
}
