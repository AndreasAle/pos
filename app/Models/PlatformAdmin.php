<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PlatformAdmin extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'is_active', 'last_login_at', 'recovery_codes',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_code_hash', 'recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'recovery_codes' => 'array',
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

    /** Buat 8 kode pemulihan sekali pakai. Yang disimpan hanya hash-nya; kode asli dikembalikan sekali. */
    public function generateRecoveryCodes(): array
    {
        $codes = collect(range(1, 8))->map(fn () => strtoupper(\Illuminate\Support\Str::random(5)).'-'.strtoupper(\Illuminate\Support\Str::random(5)))->all();
        $this->forceFill(['recovery_codes' => array_map(fn ($c) => \Illuminate\Support\Facades\Hash::make($c), $codes)])->save();

        return $codes;
    }

    /** Pakai satu kode pemulihan: cocok -> dihapus (sekali pakai) dan return true. */
    public function consumeRecoveryCode(string $input): bool
    {
        $input = strtoupper(trim($input));
        $hashes = (array) $this->recovery_codes;
        foreach ($hashes as $i => $hash) {
            if (\Illuminate\Support\Facades\Hash::check($input, $hash)) {
                unset($hashes[$i]);
                $this->forceFill(['recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }
}
