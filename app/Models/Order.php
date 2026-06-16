<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory, Loggable;

    // Override trait method, bukan property (hindari conflict)
    public function getLogExcludeFields(): array
    {
        return ['updated_at', 'created_at', 'cashier_shift_id', 'user_id', 'password'];
    }

    public function getLogLabel(): string { return $this->order_number ?? '#'.$this->id; }

    protected $fillable = [
        'business_id', 'outlet_id', 'user_id', 'cashier_shift_id',
        'customer_id', 'promotion_id', 'order_number',
        'order_type', 'delivery_platform', 'delivery_fee',
        'customer_address', 'delivery_notes', 'platform_order_number',
        'subtotal', 'discount_amount', 'tax_amount', 'service_amount',
        'grand_total', 'paid_amount', 'change_amount',
        'payment_method', 'payment_status', 'status', 'kitchen_status',
        'payment_token', 'qris_url', 'is_split_payment',
        'notes', 'cancel_reason', 'refund_reason', 'refunded_at', 'ordered_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'         => 'decimal:2',
            'discount_amount'  => 'decimal:2',
            'tax_amount'       => 'decimal:2',
            'service_amount'   => 'decimal:2',
            'grand_total'      => 'decimal:2',
            'paid_amount'      => 'decimal:2',
            'change_amount'    => 'decimal:2',
            'delivery_fee'     => 'decimal:2',
            'is_split_payment' => 'boolean',
            'ordered_at'       => 'datetime',
            'refunded_at'      => 'datetime',
        ];
    }

    public function business()   { return $this->belongsTo(Business::class); }
    public function outlet()     { return $this->belongsTo(Outlet::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function cashier()    { return $this->belongsTo(User::class, 'user_id'); }
    public function shift()      { return $this->belongsTo(CashierShift::class, 'cashier_shift_id'); }
    public function customer()   { return $this->belongsTo(Customer::class); }
    public function promotion()  { return $this->belongsTo(Promotion::class); }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function getEstimatedProfitAttribute(): float
    {
        return $this->items->sum(fn($item) =>
            ((float)$item->price - (float)$item->cost_price) * (float)$item->qty
        );
    }
}
