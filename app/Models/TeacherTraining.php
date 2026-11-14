<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherTraining extends Model
{
    protected $fillable = ['teacher_profile_id', 'training_name', 'training_month', 'training_year', 'duration_hours'];

    protected function casts(): array
    {
        return ['training_month' => 'integer', 'training_year' => 'integer', 'duration_hours' => 'integer'];
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }
}
