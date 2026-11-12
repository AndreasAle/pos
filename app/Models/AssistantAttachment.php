<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantAttachment extends Model
{
    protected $fillable = ['business_id', 'user_id', 'assistant_conversation_id', 'assistant_message_id', 'original_name', 'mime', 'kind', 'size', 'path', 'extracted_text'];
}
