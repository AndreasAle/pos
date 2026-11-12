<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntelligenceAgent extends Model
{
    protected $fillable = ['business_id', 'outlet_id', 'agent_key', 'frequency', 'schedule_time', 'timezone', 'recipients', 'channels', 'thresholds', 'suppression_minutes', 'enabled', 'version', 'last_run_at', 'next_run_at'];

    protected function casts(): array
    {
        return ['recipients' => 'array', 'channels' => 'array', 'thresholds' => 'array', 'enabled' => 'boolean', 'last_run_at' => 'datetime', 'next_run_at' => 'datetime'];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function runs()
    {
        return $this->hasMany(IntelligenceAgentRun::class, 'agent_id');
    }
}
