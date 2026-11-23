<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCreditTopup extends Model
{
    protected $fillable = ['business_id', 'user_id', 'pack', 'credits', 'amount', 'status', 'payment_token', 'payment_url', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'credits' => 'integer', 'paid_at' => 'datetime'];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
