<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Classroom extends Model
{
    public const COLORS = ['indigo', 'emerald', 'rose', 'amber', 'sky', 'violet'];

    protected $fillable = [
        'teacher_id', 'name', 'section', 'subject', 'room', 'description', 'color', 'code', 'theme_image_path',
        'theme_zoom', 'theme_position_x', 'theme_position_y',
    ];

    protected $casts = [
        'theme_zoom' => 'integer',
        'theme_position_x' => 'integer',
        'theme_position_y' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom) {
            if (empty($classroom->code)) {
                $classroom->code = static::generateUniqueCode();
            }
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** Ganti kode bergabung tanpa mengeluarkan siswa yang sudah menjadi anggota. */
    public function regenerateCode(): string
    {
        do {
            $newCode = static::generateUniqueCode();
        } while ($newCode === $this->code);

        $this->update(['code' => $newCode]);

        return $newCode;
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_user')->withTimestamps();
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function videoLessons(): HasMany
    {
        return $this->hasMany(VideoLesson::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /** Guru pemilik kelas atau admin boleh mengelola. */
    public function canManage(User $user): bool
    {
        return $user->isAdmin() || $user->id === $this->teacher_id;
    }

    public function hasStudent(User $user): bool
    {
        return $this->students()->where('users.id', $user->id)->exists();
    }

    public function canAccess(User $user): bool
    {
        return $this->canManage($user) || $this->hasStudent($user);
    }

    /** Kelas warna banner (kelas penuh agar terbaca Tailwind). */
    public function bannerClass(): string
    {
        return match ($this->color) {
            'emerald' => 'bg-emerald-600',
            'rose' => 'bg-rose-600',
            'amber' => 'bg-amber-600',
            'sky' => 'bg-sky-600',
            'violet' => 'bg-violet-600',
            default => 'bg-indigo-600',
        };
    }

    public function themeImageStyle(): string
    {
        $zoom = min(250, max(100, (int) ($this->theme_zoom ?? 100))) / 100;
        $x = min(100, max(0, (int) ($this->theme_position_x ?? 50)));
        $y = min(100, max(0, (int) ($this->theme_position_y ?? 50)));
        $shiftX = round((50 - $x) * ($zoom - 1), 2);
        $shiftY = round((50 - $y) * ($zoom - 1), 2);

        return "object-position: {$x}% {$y}%; transform: translate({$shiftX}%, {$shiftY}%) scale({$zoom});";
    }
}
