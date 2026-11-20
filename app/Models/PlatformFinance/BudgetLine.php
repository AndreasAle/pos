<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;

class BudgetLine extends Model
{
    public $timestamps = false;

    protected $table = 'platform_fin_budget_lines';

    protected $fillable = ['platform_fin_budget_version_id', 'period', 'account_code', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
