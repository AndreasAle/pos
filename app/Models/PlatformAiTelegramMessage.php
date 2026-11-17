<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiTelegramMessage extends Model
{
    protected $fillable = ['platform_ai_telegram_group_id', 'platform_ai_telegram_bot_id', 'platform_ai_agent_id', 'platform_admin_id', 'platform_ai_task_id', 'platform_ai_discussion_id', 'interaction_id', 'direction', 'channel', 'telegram_message_id', 'thread_id', 'content', 'delivery_status', 'metadata', 'delivered_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'delivered_at' => 'datetime'];
    }

    public function agent()
    {
        return $this->belongsTo(PlatformAiAgent::class, 'platform_ai_agent_id');
    }
}
