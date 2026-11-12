<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntelligenceActionProposal extends Model
{
    protected $fillable = ['business_id', 'outlet_id', 'actor_id', 'action_type', 'payload', 'preview', 'payload_hash', 'expected_version', 'idempotency_key', 'status', 'expires_at', 'executed_at', 'result'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'preview' => 'array', 'result' => 'array', 'expires_at' => 'datetime', 'executed_at' => 'datetime'];
    }

    public function approvals()
    {
        return $this->hasMany(IntelligenceActionApproval::class, 'proposal_id');
    }
}
