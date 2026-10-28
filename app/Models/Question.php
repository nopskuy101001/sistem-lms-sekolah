<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    public const TYPE_PG = 'pilihan_ganda';
    public const TYPE_PGK_MCMA = 'pilihan_ganda_kompleks';
    public const TYPE_PGK_KATEGORI = 'kategori';
    public const TYPE_ESAI = 'esai';

    protected $fillable = [
        'quiz_id', 'question_group_id', 'type', 'body',
        'option_a', 'option_b', 'option_c', 'option_d',
        'correct_option', 'correct_options', 'category_items',
        'competency', 'subcompetency', 'cognitive_level', 'points',
    ];

    protected function casts(): array
    {
        return [
            'correct_options' => 'array',
            'category_items' => 'array',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(QuestionGroup::class, 'question_group_id');
    }

    public function isPilihanGanda(): bool
    {
        return $this->type === self::TYPE_PG;
    }

    public function isPilihanGandaKompleks(): bool
    {
        return $this->type === self::TYPE_PGK_MCMA;
    }

    public function isKategori(): bool
    {
        return $this->type === self::TYPE_PGK_KATEGORI;
    }

    public function isObjective(): bool
    {
        return in_array($this->type, [self::TYPE_PG, self::TYPE_PGK_MCMA, self::TYPE_PGK_KATEGORI], true);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_PG => 'Pilihan Ganda',
            self::TYPE_PGK_MCMA => 'PG Kompleks · Multi Jawaban',
            self::TYPE_PGK_KATEGORI => 'PG Kompleks · Kategori',
            default => 'Esai',
        };
    }

    public function cognitiveLevelLabel(): ?string
    {
        return match ($this->cognitive_level) {
            'pemahaman' => 'Pemahaman',
            'penerapan' => 'Penerapan',
            'penalaran' => 'Penalaran',
            default => null,
        };
    }

    public function decodeAnswer(?string $answer): mixed
    {
        if ($answer === null) {
            return $this->isPilihanGandaKompleks() || $this->isKategori() ? [] : null;
        }

        if ($this->isPilihanGandaKompleks() || $this->isKategori()) {
            return json_decode($answer, true) ?: [];
        }

        return $answer;
    }

    /** @return array<string,string> contoh: ['a' => 'Jakarta', ...] */
    public function options(): array
    {
        return array_filter([
            'a' => $this->option_a,
            'b' => $this->option_b,
            'c' => $this->option_c,
            'd' => $this->option_d,
        ], fn ($text) => filled($text));
    }
}
