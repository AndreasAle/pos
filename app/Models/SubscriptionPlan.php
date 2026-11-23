<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'price', 'period_prices', 'max_outlets', 'max_users', 'features', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price'         => 'decimal:2',
            'period_prices' => 'array',
            'features'      => 'array',
            'is_active'     => 'boolean',
        ];
    }

    public function subscriptions()
    {
        return $this->hasMany(BusinessSubscription::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Harga resmi per durasi, urut dari terpendek: [bulan => rupiah].
     * 1 bulan selalu = price; durasi lain hanya yang tercatat di period_prices.
     *
     * @return array<int,int>
     */
    public function periods(): array
    {
        $out = [1 => (int) round((float) $this->price)];
        foreach ((array) $this->period_prices as $months => $amount) {
            $months = (int) $months;
            if ($months > 1 && $months <= 24 && (int) $amount > 0) {
                $out[$months] = (int) $amount;
            }
        }
        ksort($out);

        return $out;
    }

    public function priceFor(int $months): ?int
    {
        return $this->periods()[$months] ?? null;
    }
}
