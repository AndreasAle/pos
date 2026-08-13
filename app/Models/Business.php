<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'phone', 'email', 'address',
        'logo', 'qris_image', 'qris_merchant_name', 'qris_nmid',
        'timezone', 'currency', 'is_active', 'settings',
        'balance', 'total_earned', 'total_withdrawn',
    ];

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'settings'        => 'array',
            'balance'         => 'decimal:2',
            'total_earned'    => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
        ];
    }

    public function outlets()
    {
        return $this->hasMany(Outlet::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function categories()
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(BusinessSubscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(BusinessSubscription::class)
            ->whereIn('status', ['trial', 'active'])
            // Ordered by id, not created_at: two subscriptions written in the same
            // second would otherwise tie and the winner would be arbitrary.
            ->latest('id');
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function balanceTransactions()
    {
        return $this->hasMany(BalanceTransaction::class);
    }

    public function withdrawalRequests()
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->logo
            ? asset('storage/' . $this->logo)
            : asset('images/default-logo.png');
    }
}
