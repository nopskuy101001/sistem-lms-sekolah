<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranscriptState extends Model
{
    protected $fillable = ['classroom_id', 'finalized_by', 'finalized_at', 'snapshot'];

    protected function casts(): array
    {
        return ['finalized_at' => 'datetime', 'snapshot' => 'array'];
    }
}
