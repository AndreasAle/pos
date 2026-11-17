<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiEvent extends Model
{
    protected $fillable = ['platform_ai_agent_id', 'type', 'severity', 'fingerprint', 'evidence', 'status', 'notified_at', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'notified_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }
}
