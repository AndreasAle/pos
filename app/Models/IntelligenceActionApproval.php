<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntelligenceActionApproval extends Model
{
    protected $fillable = ['proposal_id', 'approver_id', 'decision', 'reason', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }
}
