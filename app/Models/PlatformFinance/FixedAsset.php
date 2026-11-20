<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    protected $table = 'platform_fin_assets';

    protected $fillable = ['name', 'acquired_at', 'cost', 'salvage_value', 'useful_life_months', 'paid_from', 'cost_center', 'acquisition_entry_id', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['acquired_at' => 'date', 'cost' => 'decimal:2', 'salvage_value' => 'decimal:2'];
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(Depreciation::class, 'platform_fin_asset_id');
    }

    /** Penyusutan garis lurus per bulan (Financial Accounting bab 11.3). */
    public function monthlyDepreciation(): float
    {
        return round(((float) $this->cost - (float) $this->salvage_value) / max(1, $this->useful_life_months), 2);
    }
}
