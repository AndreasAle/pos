<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PlatformAdmin extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'last_login_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function activityLogs()
    {
        return $this->hasMany(PlatformActivityLog::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function canManageSubscriptions(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canManageWithdrawals(): bool
    {
        return in_array($this->role, ['super_admin', 'finance'], true);
    }

    public function canImpersonate(): bool
    {
        return in_array($this->role, ['super_admin', 'support'], true);
    }

    public function canViewFinancials(): bool
    {
        return in_array($this->role, ['super_admin', 'finance'], true);
    }
}
