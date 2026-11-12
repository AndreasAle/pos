<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceivablePayment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function receivable()
    {
        return $this->belongsTo(Receivable::class);
    }
}
