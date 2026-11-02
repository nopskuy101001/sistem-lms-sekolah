<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'nomor_induk',
        'date_of_birth',
        'gender',
        'phone',
        'address',
        'profile_photo_path',
        'profile_photo_zoom',
        'profile_photo_position_x',
        'profile_photo_position_y',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'must_change_password' => 'boolean',
            'profile_photo_zoom' => 'integer',
            'profile_photo_position_x' => 'integer',
            'profile_photo_position_y' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isGuru(): bool
    {
        return $this->role === 'guru';
    }

    public function isSiswa(): bool
    {
        return $this->role === 'siswa';
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Admin',
            'guru' => 'Guru',
            default => 'Siswa',
        };
    }

    public function profilePhotoStyle(): string
    {
        $zoom = min(300, max(100, (int) ($this->profile_photo_zoom ?? 100))) / 100;
        $x = min(100, max(0, (int) ($this->profile_photo_position_x ?? 50)));
        $y = min(100, max(0, (int) ($this->profile_photo_position_y ?? 50)));
        $shiftX = round((50 - $x) * ($zoom - 1), 2);
        $shiftY = round((50 - $y) * ($zoom - 1), 2);

        return "object-position: {$x}% {$y}%; transform: translate({$shiftX}%, {$shiftY}%) scale({$zoom});";
    }

    /** Kelas yang diajar (guru). */
    public function teachingClassrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'teacher_id');
    }

    /** Kelas yang diikuti (siswa). */
    public function joinedClassrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_user')->withTimestamps();
    }
}
