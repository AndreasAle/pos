<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantConversation extends Model
{
    protected $fillable = ['business_id', 'user_id', 'title'];

    public function messages() { return $this->hasMany(AssistantMessage::class); }
}
