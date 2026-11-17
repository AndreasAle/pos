<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiDecision extends Model
{
    protected $fillable = [
        'statement', 'business_area', 'reason', 'conditions', 'related_metrics', 'decided_by',
        'platform_ai_agent_id', 'source_message_id', 'platform_ai_task_id', 'status', 'superseded_by', 'version',
    ];

    protected function casts(): array
    {
        return ['conditions' => 'array', 'related_metrics' => 'array'];
    }
}
