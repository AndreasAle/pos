<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSubscription extends Model
{
    protected $fillable = [
        'business_id', 'subscription_plan_id', 'starts_at', 'ends_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at'   => 'date',
        ];
    }

    public function business() { return $this->belongsTo(Business::class); }
    public function plan()     { return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id'); }

    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active'])
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
