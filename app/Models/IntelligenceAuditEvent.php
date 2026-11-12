<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntelligenceAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['business_id', 'actor_id', 'object_type', 'object_id', 'event', 'before', 'after', 'happened_at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'happened_at' => 'datetime'];
    }
}
