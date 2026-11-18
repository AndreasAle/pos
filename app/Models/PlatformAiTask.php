<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiTask extends Model
{
    protected $fillable = ['platform_ai_agent_id', 'parent_task_id', 'delegated_by_agent_id', 'delegation_depth', 'type', 'title', 'summary', 'recommendation', 'status', 'risk', 'requires_approval', 'approval_version', 'idempotency_key', 'payload', 'result', 'error', 'scheduled_for', 'approved_by', 'approved_at', 'executed_at'];

    protected function casts(): array
    {
        return ['requires_approval' => 'boolean', 'payload' => 'array', 'result' => 'array', 'scheduled_for' => 'datetime', 'approved_at' => 'datetime', 'executed_at' => 'datetime'];
    }

    public function agent()
    {
        return $this->belongsTo(PlatformAiAgent::class, 'platform_ai_agent_id');
    }

    public function delegatedByAgent()
    {
        return $this->belongsTo(PlatformAiAgent::class, 'delegated_by_agent_id');
    }

    public function executions()
    {
        return $this->hasMany(PlatformAiTaskExecution::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }
}
