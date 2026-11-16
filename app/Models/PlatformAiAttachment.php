<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiAttachment extends Model
{
    protected $fillable = [
        'platform_ai_agent_id', 'platform_ai_message_id', 'platform_admin_id',
        'original_name', 'mime', 'kind', 'size', 'path', 'extracted_text',
    ];

    public function agent()
    {
        return $this->belongsTo(PlatformAiAgent::class, 'platform_ai_agent_id');
    }

    public function message()
    {
        return $this->belongsTo(PlatformAiMessage::class, 'platform_ai_message_id');
    }
}
