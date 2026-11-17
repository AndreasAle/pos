<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiTelegramGroup extends Model
{
    protected $fillable = ['platform_admin_id', 'telegram_chat_id', 'title', 'enabled', 'mode', 'topic_routes', 'notification_settings', 'last_activity_at'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'topic_routes' => 'array', 'notification_settings' => 'array', 'last_activity_at' => 'datetime'];
    }

    public function admin()
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }
}
