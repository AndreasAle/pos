<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessInsight extends Model
{
    protected $fillable = [
        'business_id', 'outlet_id', 'fingerprint', 'type', 'severity', 'title',
        'summary', 'data', 'action_label', 'action_route', 'detected_at',
        'expires_at', 'read_at', 'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array', 'detected_at' => 'datetime', 'expires_at' => 'datetime',
            'read_at' => 'datetime', 'dismissed_at' => 'datetime',
        ];
    }

    public function scopeVisible($query)
    {
        return $query->whereNull('dismissed_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
