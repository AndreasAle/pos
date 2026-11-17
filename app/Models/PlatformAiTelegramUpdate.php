<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiTelegramUpdate extends Model
{
    protected $fillable = ['platform_ai_telegram_bot_id', 'platform_ai_telegram_group_id', 'telegram_update_id', 'telegram_chat_id', 'telegram_message_id', 'interaction_id', 'event_fingerprint', 'status', 'payload', 'error', 'processed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }

    public function bot()
    {
        return $this->belongsTo(PlatformAiTelegramBot::class, 'platform_ai_telegram_bot_id');
    }

    public function group()
    {
        return $this->belongsTo(PlatformAiTelegramGroup::class, 'platform_ai_telegram_group_id');
    }
}
