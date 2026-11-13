<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiTask extends Model
{
    protected $fillable = ['platform_ai_agent_id', 'type', 'title', 'summary', 'recommendation', 'status', 'risk', 'requires_approval', 'payload', 'result', 'error', 'scheduled_for', 'approved_by', 'approved_at', 'executed_at'];

    protected function casts(): array
    {
        return ['requires_approval' => 'boolean', 'payload' => 'array', 'result' => 'array', 'scheduled_for' => 'datetime', 'approved_at' => 'datetime', 'executed_at' => 'datetime'];
    }

    public function agent()
    {
        return $this->belongsTo(PlatformAiAgent::class, 'platform_ai_agent_id');
    }
}
