<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, Loggable;

    protected $fillable = [
        'business_id', 'outlet_id', 'product_category_id', 'name', 'sku',
        'description', 'image', 'price', 'cost_price', 'is_active',
        'is_stock_tracked', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price'            => 'decimal:2',
            'cost_price'       => 'decimal:2',
            'is_active'        => 'boolean',
            'is_stock_tracked' => 'boolean',
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

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function addons()
    {
        return $this->hasMany(ProductAddon::class)->orderBy('sort_order');
    }

    public function recipe()
    {
        return $this->hasOne(Recipe::class);
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image
            ? asset('storage/' . $this->image)
            : asset('images/default-product.png');
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOutletOrGlobal($query, $outletId)
    {
        return $query->where(function ($q) use ($outletId) {
            $q->whereNull('outlet_id')->orWhere('outlet_id', $outletId);
        });
    }
}
