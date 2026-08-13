<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Loggable;

class ProductBundle extends Model
{
    use HasFactory, Loggable;

    protected $fillable = ['business_id', 'name', 'description', 'price', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function business() { return $this->belongsTo(Business::class); }

    public function items()
    {
        return $this->hasMany(ProductBundleItem::class);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getCostPriceAttribute(): float
    {
        return $this->items->sum(fn($item) =>
            (float) optional($item->product)->cost_price * (float) $item->qty
        );
    }
}
