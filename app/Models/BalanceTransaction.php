<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceTransaction extends Model
{
    protected $fillable = [
        'business_id', 'type', 'amount', 'platform_fee', 'net_amount',
        'balance_before', 'balance_after', 'reference', 'description',
        'source', 'sourceable_type', 'sourceable_id',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'platform_fee'   => 'decimal:2',
        'net_amount'     => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after'  => 'decimal:2',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function sourceable()
    {
        return $this->morphTo();
    }
}
