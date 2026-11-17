<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiMemory extends Model
{
    protected $fillable = [
        'scope', 'platform_admin_id', 'platform_ai_agent_id', 'category', 'memory_key', 'content',
        'source_message_id', 'source_type', 'confidence', 'importance', 'confirmed', 'status',
        'last_confirmed_at', 'expires_at', 'superseded_by', 'version',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array', 'confidence' => 'decimal:3', 'confirmed' => 'boolean',
            'last_confirmed_at' => 'datetime', 'expires_at' => 'datetime',
        ];
    }

    public function agent()
    {
        return $this->belongsTo(PlatformAiAgent::class, 'platform_ai_agent_id');
    }

    public function sourceMessage()
    {
        return $this->belongsTo(PlatformAiMessage::class, 'source_message_id');
    }
}
