<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Akun buku besar Yoka (platform). Bukan data toko klien. */
class Account extends Model
{
    protected $table = 'platform_fin_accounts';

    protected $fillable = ['code', 'name', 'type', 'subtype', 'is_cash', 'behavior', 'report_group', 'is_active'];

    protected function casts(): array
    {
        return ['is_cash' => 'boolean', 'is_active' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(Line::class, 'platform_fin_account_id');
    }

    /** Saldo normal debit: aset, beban, dan akun kontra (prive, refund pendapatan). */
    public function isDebitNormal(): bool
    {
        if ($this->subtype === 'contra_asset') {
            return false; // akumulasi penyusutan: saldo normal kredit, mengurangi aset
        }

        return in_array($this->type, ['asset', 'expense'], true) || in_array($this->subtype, ['drawing', 'contra_revenue'], true);
    }
}
