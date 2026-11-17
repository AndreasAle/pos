<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiTelegramBot extends Model
{
    protected $fillable = ['platform_ai_agent_id', 'public_key', 'telegram_bot_id', 'username', 'credential_key', 'webhook_secret_hash', 'status', 'webhook_status', 'last_health_at', 'last_activity_at', 'last_error'];

    protected function casts(): array
    {
        return ['last_health_at' => 'datetime', 'last_activity_at' => 'datetime'];
    }

    public function agent()
    {
        return $this->belongsTo(PlatformAiAgent::class, 'platform_ai_agent_id');
    }
}
