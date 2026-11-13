<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiRecord extends Model
{
    protected $fillable = ['kind', 'title', 'status', 'source', 'amount', 'score', 'data', 'occurred_at', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'score' => 'integer', 'data' => 'array', 'occurred_at' => 'datetime'];
    }
}
