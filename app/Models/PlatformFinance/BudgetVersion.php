<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetVersion extends Model
{
    public const SCENARIOS = ['conservative' => 'Konservatif', 'base' => 'Dasar', 'growth' => 'Growth'];

    protected $table = 'platform_fin_budget_versions';

    protected $fillable = ['name', 'scenario', 'period_from', 'months', 'status', 'assumptions', 'notes', 'created_by_type', 'created_by_id', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['assumptions' => 'array', 'approved_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'platform_fin_budget_version_id');
    }
}
