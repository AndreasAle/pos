<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;

class Depreciation extends Model
{
    protected $table = 'platform_fin_depreciations';

    protected $fillable = ['platform_fin_asset_id', 'period', 'amount', 'platform_fin_entry_id'];
}
