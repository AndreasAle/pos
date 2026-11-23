<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCreditWallet extends Model
{
    protected $fillable = ['business_id', 'free_balance', 'free_period', 'paid_balance', 'paid_expires_at'];

    protected function casts(): array
    {
        return ['free_balance' => 'integer', 'paid_balance' => 'integer', 'paid_expires_at' => 'date'];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function total(): int
    {
        return $this->free_balance + $this->paid_balance;
    }
}
