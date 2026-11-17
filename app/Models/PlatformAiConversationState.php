<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiConversationState extends Model
{
    protected $fillable = ['platform_admin_id', 'platform_ai_agent_id', 'channel', 'context_key', 'active_topic', 'mood', 'state', 'context_revised_at'];

    protected function casts(): array
    {
        return ['state' => 'array', 'context_revised_at' => 'datetime'];
    }

    public function admin()
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }

    public function agent()
    {
        return $this->belongsTo(PlatformAiAgent::class, 'platform_ai_agent_id');
    }
}
