<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionRoom extends Model
{
    protected $fillable = ['creator_id', 'name', 'subject', 'description'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'discussion_room_user')->withTimestamps();
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
}
