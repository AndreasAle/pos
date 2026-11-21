<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Jejak audit Adit BD (append-only). */
class PlatformAiProspectEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['platform_ai_prospect_id', 'type', 'from_stage', 'to_stage', 'actor', 'reason', 'data', 'idempotency_key'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function prospect()
    {
        return $this->belongsTo(PlatformAiProspect::class, 'platform_ai_prospect_id');
    }
}
