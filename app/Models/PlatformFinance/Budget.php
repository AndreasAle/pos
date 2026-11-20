<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    protected $table = 'platform_fin_budgets';

    protected $fillable = ['period', 'platform_fin_account_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'platform_fin_account_id');
    }
}
