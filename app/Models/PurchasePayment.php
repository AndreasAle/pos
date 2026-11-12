<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
