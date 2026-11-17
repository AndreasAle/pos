<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiRun extends Model
{
    protected $fillable = ['platform_ai_agent_id', 'platform_ai_task_id', 'platform_ai_discussion_id', 'operation', 'model', 'status', 'input_tokens', 'output_tokens', 'tool_calls', 'latency_ms', 'estimated_cost_usd', 'error', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'estimated_cost_usd' => 'decimal:6'];
    }
}
