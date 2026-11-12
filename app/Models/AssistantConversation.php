<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantConversation extends Model
{
    protected $fillable = ['business_id', 'outlet_id', 'user_id', 'title', 'context_version', 'context', 'summary', 'last_message_at'];

    protected function casts(): array
    {
        return ['context' => 'array', 'last_message_at' => 'datetime'];
    }

    public function messages()
    {
        return $this->hasMany(AssistantMessage::class);
    }
}
