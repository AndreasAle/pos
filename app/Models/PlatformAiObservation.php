<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiObservation extends Model
{
    protected $fillable = ['metric', 'value', 'fingerprint', 'observed_at'];

    protected function casts(): array
    {
        return ['value' => 'array', 'observed_at' => 'datetime'];
    }
}
