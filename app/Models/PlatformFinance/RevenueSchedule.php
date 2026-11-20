<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;

class RevenueSchedule extends Model
{
    protected $table = 'platform_fin_revenue_schedules';

    protected $fillable = ['business_subscription_id', 'period', 'amount', 'platform_fin_entry_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
