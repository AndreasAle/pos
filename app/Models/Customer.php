<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id', 'name', 'phone', 'email',
        'total_transactions', 'total_spending', 'loyalty_points', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_spending' => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }

    public function business() { return $this->belongsTo(Business::class); }
    public function orders()   { return $this->hasMany(Order::class); }
    public function points()   { return $this->hasMany(CustomerPoint::class); }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
