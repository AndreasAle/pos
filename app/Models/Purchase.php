<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date', 'due_date' => 'date', 'cancelled_at' => 'datetime', 'stock_received' => 'boolean',
            'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments()
    {
        return $this->hasMany(PurchasePayment::class)->orderBy('paid_at');
    }

    public function getOutstandingAttribute(): float
    {
        return $this->status === 'cancelled' ? 0.0 : max(0, round((float) $this->total - (float) $this->paid_amount, 2));
    }
}
