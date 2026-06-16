<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id', 'outlet_id', 'name', 'sku', 'unit',
        'current_stock', 'minimum_stock', 'average_cost', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'average_cost'  => 'decimal:2',
            'is_active'     => 'boolean',
        ];
    }

    public function business()  { return $this->belongsTo(Business::class); }
    public function outlet()    { return $this->belongsTo(Outlet::class); }
    public function movements() { return $this->hasMany(StockMovement::class); }
    public function recipeItems() { return $this->hasMany(RecipeItem::class); }

    public function isLowStock(): bool
    {
        return (float)$this->current_stock <= (float)$this->minimum_stock;
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('current_stock', '<=', 'minimum_stock');
    }
}
