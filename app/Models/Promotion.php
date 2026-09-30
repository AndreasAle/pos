<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id', 'outlet_id', 'name', 'code', 'type', 'value',
        'buy_qty', 'get_qty', 'product_category_id',
        'min_order', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value'     => 'decimal:2',
            'buy_qty'   => 'integer',
            'get_qty'   => 'integer',
            'min_order' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at'   => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function business() { return $this->belongsTo(Business::class); }
    public function outlet()   { return $this->belongsTo(Outlet::class); }
    public function category() { return $this->belongsTo(ProductCategory::class, 'product_category_id'); }

    /**
     * @param  array<int, array{category_id: ?int, price: float, qty: float}>  $lines
     *         The order's product lines; only "beli X gratis Y" needs them.
     */
    public function calculateDiscount(float $subtotal, array $lines = []): float
    {
        if ($subtotal < (float)$this->min_order) return 0;

        return match ($this->type) {
            'percent' => round($subtotal * ((float)$this->value / 100), 2),
            'buy_get' => $this->buyGetDiscount($lines),
            default   => (float)$this->value,
        };
    }

    /**
     * Every full group of (buy + get) eligible units makes its `get` cheapest
     * units free. Units are sorted most expensive first, so the shop never
     * gives away a pricier item than the ones the customer paid for.
     */
    private function buyGetDiscount(array $lines): float
    {
        $buy   = max(1, (int) $this->buy_qty);
        $get   = max(1, (int) $this->get_qty);
        $group = $buy + $get;

        $units = [];
        foreach ($lines as $line) {
            if ($this->product_category_id && (int) ($line['category_id'] ?? 0) !== (int) $this->product_category_id) {
                continue;
            }
            // Only whole units count; half a coffee earns nothing free.
            for ($i = 0; $i < (int) floor((float) $line['qty']); $i++) {
                $units[] = (float) $line['price'];
            }
        }

        rsort($units);

        $free = 0.0;
        foreach (array_chunk($units, $group) as $chunk) {
            if (count($chunk) === $group) {
                $free += array_sum(array_slice($chunk, $buy));
            }
        }

        return round($free, 2);
    }

    public function label(): string
    {
        return match ($this->type) {
            'percent' => rtrim(rtrim(number_format((float) $this->value, 2, ',', '.'), '0'), ',') . '%',
            'buy_get' => 'Beli ' . $this->buy_qty . ' Gratis ' . $this->get_qty,
            default   => 'Rp ' . number_format((float) $this->value, 0, ',', '.'),
        };
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
