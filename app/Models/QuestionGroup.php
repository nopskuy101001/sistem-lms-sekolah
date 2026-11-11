<?php

namespace App\Models;

use App\Support\VideoSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionGroup extends Model
{
    protected $fillable = [
        'quiz_id', 'title', 'stimulus', 'media_path', 'media_name', 'media_alt', 'table_data',
        'video_type', 'video_url', 'video_path', 'video_name', 'video_caption',
    ];

    protected function casts(): array
    {
        return [
            'table_data' => 'array',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('id');
    }

    public function youtubeVideoId(): ?string
    {
        return $this->video_type === 'youtube'
            ? VideoSource::youtubeId($this->video_url)
            : null;
    }

    public function hasVideo(): bool
    {
        return filled($this->video_path) || filled($this->video_url);
    }
}
