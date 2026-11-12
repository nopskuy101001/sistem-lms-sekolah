<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionMessage extends Model
{
    protected $fillable = [
        'discussion_room_id',
        'user_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
    ];

    protected function casts(): array
    {
        return ['attachment_size' => 'integer'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(DiscussionRoom::class, 'discussion_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->attachment_mime, 'image/');
    }

    public function attachmentSizeLabel(): string
    {
        $bytes = (int) $this->attachment_size;

        return $bytes >= 1_048_576
            ? number_format($bytes / 1_048_576, 1, ',', '.').' MB'
            : number_format(max(1, $bytes) / 1024, 0, ',', '.').' KB';
    }
}
