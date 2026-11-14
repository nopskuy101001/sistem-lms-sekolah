<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherProfile extends Model
{
    protected $fillable = [
        'user_id', 'nip_original', 'source_sequence', 'service_years', 'service_months',
        'employment_mutation_notes', 'remarks',
    ];

    protected function casts(): array
    {
        return ['service_years' => 'integer', 'service_months' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rankHistories(): HasMany
    {
        return $this->hasMany(TeacherRankHistory::class);
    }

    public function positionHistories(): HasMany
    {
        return $this->hasMany(TeacherPositionHistory::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(TeacherTraining::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(TeacherEducation::class);
    }
}
