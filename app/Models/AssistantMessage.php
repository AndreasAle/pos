<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantMessage extends Model
{
    protected $fillable = ['assistant_conversation_id', 'role', 'content', 'tool_name', 'meta', 'response', 'token_usage', 'request_id', 'duration_ms'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'response' => 'array', 'token_usage' => 'array'];
    }
}
