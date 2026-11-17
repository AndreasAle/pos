<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiDiscussion extends Model
{
    protected $fillable = ['created_by', 'chair_agent_id', 'objective', 'status', 'limits', 'synthesis', 'error'];

    protected function casts(): array
    {
        return ['limits' => 'array'];
    }

    public function contributions()
    {
        return $this->hasMany(PlatformAiDiscussionContribution::class);
    }
}
