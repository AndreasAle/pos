<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntelligenceAgentRun extends Model
{
    protected $fillable = ['agent_id', 'run_key', 'status', 'scheduled_for', 'started_at', 'finished_at', 'evidence', 'output', 'error_code', 'attempt'];

    protected function casts(): array
    {
        return ['scheduled_for' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'evidence' => 'array', 'output' => 'array'];
    }

    public function agent()
    {
        return $this->belongsTo(IntelligenceAgent::class, 'agent_id');
    }
}
