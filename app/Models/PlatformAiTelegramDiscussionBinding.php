<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiTelegramDiscussionBinding extends Model
{
    protected $fillable = ['platform_ai_discussion_id', 'platform_ai_telegram_group_id', 'interaction_id', 'trigger_message_id', 'thread_id', 'participant_ids', 'status'];

    protected function casts(): array
    {
        return ['participant_ids' => 'array'];
    }
}
