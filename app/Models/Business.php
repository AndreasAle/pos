<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            'is_active' => 'boolean',
            'settings' => 'array',
            'balance' => 'decimal:2',
            'total_earned' => 'decimal:2',
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

    public function owner()
    {
        return $this->hasOne(User::class)->where('role', 'owner')->oldestOfMany();
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

    public function latestSubscription()
    {
        return $this->hasOne(BusinessSubscription::class)->latestOfMany();
    }

    public function platformStatus(): string
    {
        if (! $this->is_active) {
            return 'disabled';
        }

        $subscription = $this->relationLoaded('latestSubscription')
            ? $this->latestSubscription
            : $this->latestSubscription()->with('plan')->first();

        if (! $subscription) {
            return 'stopped';
        }

        if ($subscription->status === 'pending') {
            return 'overdue';
        }

        if ($subscription->status === 'trial' && $subscription->isActive()) {
            return 'trial';
        }

        if ($subscription->status === 'active' && $subscription->isActive()) {
            return 'active';
        }

        return 'stopped';
    }

    /**
     * A shop that sells goods off the shelf (clothing, bags) rather than food.
     * It has no kitchen, recipes or dine-in, so those parts of the app hide.
     */
    public function isRetail(): bool
    {
        return ($this->settings['business_type'] ?? 'fnb') === 'retail';
    }

    // ── Telegram (disimpan di kolom settings, tanpa migrasi) ─────────────────
    public function telegram(): array
    {
        return (array) ($this->settings['telegram'] ?? []);
    }

    public function telegramLinked(): bool
    {
        return ! empty($this->telegram()['chat_id']);
    }

    /** Preferensi notifikasi: daily & alerts default aktif; kabar order baru selalu aktif. */
    public function telegramWants(string $key): bool
    {
        if (! $this->telegramLinked()) {
            return false;
        }
        // Kabar order baru wajib: tidak bisa dimatikan, supaya owner tidak pernah ketinggalan transaksi.
        if ($key === 'orders') {
            return true;
        }

        $defaults = ['daily' => true, 'alerts' => true];

        return (bool) ($this->telegram()['prefs'][$key] ?? $defaults[$key] ?? false);
    }

    /** Simpan data tautan Telegram; null = putuskan. */
    public function setTelegram(?array $data): void
    {
        $settings = (array) $this->settings;
        if ($data === null) {
            // Preferensi tetap disimpan agar tidak hilang saat tersambung ulang.
            $prefs = $settings['telegram']['prefs'] ?? null;
            unset($settings['telegram']);
            if ($prefs) {
                $settings['telegram'] = ['prefs' => $prefs];
            }
        } else {
            $settings['telegram'] = $data;
        }
        $this->update(['settings' => $settings]);
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
            ? asset('storage/'.$this->logo)
            : asset('images/default-logo.png');
    }
}
