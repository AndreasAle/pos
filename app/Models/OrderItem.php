<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'product_bundle_id',
        'product_name', 'variant_name', 'price', 'cost_price',
        'qty', 'subtotal', 'notes', 'kitchen_status',
    ];

    protected function casts(): array
    {
        return [
            'price'      => 'decimal:2',
            'cost_price' => 'decimal:2',
            'qty'        => 'decimal:3',
            'subtotal'   => 'decimal:2',
        ];
    }

    public function order()   { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function bundle()  { return $this->belongsTo(ProductBundle::class, 'product_bundle_id'); }

    public function addons()
    {
        return $this->hasMany(OrderItemAddon::class);
    }

    public function getTotalWithAddonsAttribute(): float
    {
        $addonTotal = $this->addons->sum('price');
        return ((float)$this->price + $addonTotal) * (float)$this->qty;
    }
}
