<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantToolRun extends Model
{
    protected $fillable = [
        'assistant_message_id', 'business_id', 'outlet_id', 'tool_name', 'schema_version',
        'args_hash', 'source_filters', 'evidence', 'duration_ms', 'status', 'error_code',
    ];

    protected function casts(): array
    {
        return ['source_filters' => 'array', 'evidence' => 'array'];
    }
}
