<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id', 'outlet_id', 'name', 'code', 'type', 'value',
        'min_order', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value'     => 'decimal:2',
            'min_order' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at'   => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function business() { return $this->belongsTo(Business::class); }
    public function outlet()   { return $this->belongsTo(Outlet::class); }

    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal < (float)$this->min_order) return 0;

        return $this->type === 'percent'
            ? round($subtotal * ((float)$this->value / 100), 2)
            : (float)$this->value;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()));
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
