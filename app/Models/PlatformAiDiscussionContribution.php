<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiDiscussionContribution extends Model
{
    protected $fillable = ['platform_ai_discussion_id', 'platform_ai_agent_id', 'round', 'content', 'context'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function agent()
    {
        return $this->belongsTo(PlatformAiAgent::class, 'platform_ai_agent_id');
    }
}
