<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CashierShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id', 'outlet_id', 'user_id',
        'opening_cash', 'closing_cash_expected', 'closing_cash_actual', 'cash_difference',
        'opened_at', 'closed_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_cash'            => 'decimal:2',
            'closing_cash_expected'   => 'decimal:2',
            'closing_cash_actual'     => 'decimal:2',
            'cash_difference'         => 'decimal:2',
            'opened_at'               => 'datetime',
            'closed_at'               => 'datetime',
        ];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function getTotalCashSalesAttribute(): float
    {
        return (float) $this->orders()
            ->where('status', 'paid')
            ->where('payment_method', 'cash')
            ->sum('grand_total');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
