<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessInsight extends Model
{
    protected $fillable = [
        'business_id', 'outlet_id', 'fingerprint', 'type', 'severity', 'title',
        'summary', 'data', 'evidence', 'confidence', 'sample_size', 'occurrence_count',
        'action_label', 'action_route', 'detected_at', 'first_detected_at', 'last_detected_at',
        'expires_at', 'read_at', 'acknowledged_at', 'snoozed_until', 'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array', 'evidence' => 'array', 'detected_at' => 'datetime',
            'first_detected_at' => 'datetime', 'last_detected_at' => 'datetime', 'expires_at' => 'datetime',
            'read_at' => 'datetime', 'acknowledged_at' => 'datetime', 'snoozed_until' => 'datetime', 'dismissed_at' => 'datetime',
        ];
    }

    public function scopeVisible($query)
    {
        return $query->whereNull('dismissed_at')
            ->where(fn ($q) => $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
