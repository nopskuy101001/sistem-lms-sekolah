<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherEducation extends Model
{
    protected $table = 'teacher_educations';

    protected $fillable = ['teacher_profile_id', 'education_field', 'graduation_year', 'degree_level', 'is_primary'];

    protected function casts(): array
    {
        return ['graduation_year' => 'integer', 'is_primary' => 'boolean'];
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }
}
