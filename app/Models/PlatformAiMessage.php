<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiMessage extends Model
{
    protected $fillable = ['platform_ai_agent_id', 'platform_admin_id', 'role', 'content', 'meta', 'read_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'read_at' => 'datetime'];
    }

    public function agent()
    {
        return $this->belongsTo(PlatformAiAgent::class, 'platform_ai_agent_id');
    }

    public function admin()
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }
}
