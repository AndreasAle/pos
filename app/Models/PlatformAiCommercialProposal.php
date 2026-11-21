<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Proposal harga non-standar. Versi yang sudah diputuskan tidak boleh diubah; perubahan = versi baru. */
class PlatformAiCommercialProposal extends Model
{
    protected $fillable = ['platform_ai_prospect_id', 'version', 'status', 'decision_state', 'requested_price', 'official_plan', 'official_price',
        'terms', 'analysis', 'terms_hash', 'platform_ai_task_id', 'decided_by', 'decided_at', 'decision_note'];

    protected function casts(): array
    {
        return ['terms' => 'array', 'analysis' => 'array', 'decided_at' => 'datetime', 'requested_price' => 'decimal:2', 'official_price' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $p) {
            if (in_array($p->getOriginal('status'), ['approved', 'rejected', 'changes_requested', 'superseded'], true)) {
                throw new \LogicException('Proposal yang sudah diputuskan tidak bisa diubah. Buat versi baru.');
            }
            if ($p->isDirty(['terms', 'terms_hash', 'requested_price', 'version'])) {
                throw new \LogicException('Isi proposal tidak bisa diubah setelah dibuat. Buat versi baru.');
            }
        });
    }

    public function prospect()
    {
        return $this->belongsTo(PlatformAiProspect::class, 'platform_ai_prospect_id');
    }
}
