<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionRoom extends Model
{
    public const THEME_COLORS = [
        'indigo' => ['label' => 'Indigo', 'primary' => '#4f46e5', 'secondary' => '#7c3aed', 'soft' => '#eef2ff'],
        'sky' => ['label' => 'Langit', 'primary' => '#0284c7', 'secondary' => '#0891b2', 'soft' => '#e0f2fe'],
        'emerald' => ['label' => 'Zamrud', 'primary' => '#059669', 'secondary' => '#0d9488', 'soft' => '#d1fae5'],
        'rose' => ['label' => 'Mawar', 'primary' => '#e11d48', 'secondary' => '#db2777', 'soft' => '#ffe4e6'],
        'amber' => ['label' => 'Amber', 'primary' => '#d97706', 'secondary' => '#ea580c', 'soft' => '#fef3c7'],
        'violet' => ['label' => 'Violet', 'primary' => '#7c3aed', 'secondary' => '#9333ea', 'soft' => '#ede9fe'],
        'slate' => ['label' => 'Slate', 'primary' => '#334155', 'secondary' => '#475569', 'soft' => '#e2e8f0'],
    ];

    public const THEME_STYLES = [
        'aurora' => 'Aurora',
        'waves' => 'Gelombang',
        'grid' => 'Grid',
        'minimal' => 'Minimal',
    ];

    protected $fillable = ['creator_id', 'name', 'subject', 'description', 'theme_color', 'theme_style'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'discussion_room_user')
            ->withPivot('last_read_message_id', 'last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DiscussionMessage::class);
    }

    public function canAccess(User $user): bool
    {
        return $user->isAdmin()
            || $this->creator_id === $user->id
            || $this->members()->whereKey($user->id)->exists();
    }

    public function canManage(User $user): bool
    {
        return $user->isAdmin() || $this->creator_id === $user->id;
    }

    /** @return array{label:string,primary:string,secondary:string,soft:string} */
    public function themePalette(): array
    {
        return self::THEME_COLORS[$this->theme_color] ?? self::THEME_COLORS['indigo'];
    }

    public function themeStyle(): string
    {
        return array_key_exists((string) $this->theme_style, self::THEME_STYLES)
            ? (string) $this->theme_style
            : 'aurora';
    }

    public function themeVariables(): string
    {
        $palette = $this->themePalette();

        return '--discussion-primary: '.$palette['primary'].'; --discussion-secondary: '.$palette['secondary'].'; --discussion-soft: '.$palette['soft'].';';
    }
}
