<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiTaskExecution extends Model
{
    protected $fillable = ['platform_ai_task_id', 'idempotency_key', 'task_version', 'attempt', 'status', 'tool_calls', 'result', 'error', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['tool_calls' => 'array', 'result' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
