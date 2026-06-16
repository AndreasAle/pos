<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'business_id', 'outlet_id', 'name', 'email', 'password',
        'role', 'is_active', 'phone', 'avatar',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
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

    public function shifts()
    {
        return $this->hasMany(CashierShift::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function activeShift()
    {
        return $this->hasOne(CashierShift::class)->where('status', 'open')
            ->when($this->outlet_id, fn($q) => $q->where('outlet_id', $this->outlet_id));
    }

    public function isOwner(): bool { return $this->role === 'owner'; }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isCashier(): bool { return $this->role === 'cashier'; }
    public function isKitchen(): bool { return $this->role === 'kitchen'; }
    public function isWarehouse(): bool { return $this->role === 'warehouse'; }

    public function canAccess(string $permission): bool
    {
        return match($this->role) {
            'owner'     => true,
            'admin'     => !in_array($permission, ['saas']),
            'cashier'   => in_array($permission, ['pos', 'shift', 'receipt']),
            'kitchen'   => in_array($permission, ['kitchen']),
            'warehouse' => in_array($permission, ['inventory']),
            default     => false,
        };
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
