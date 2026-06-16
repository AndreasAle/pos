<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'name', 'price_adjustment', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price_adjustment' => 'decimal:2',
            'is_active'        => 'boolean',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getFinalPriceAttribute(): float
    {
        return (float) $this->product->price + (float) $this->price_adjustment;
    }
}
