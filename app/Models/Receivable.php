<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receivable extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'due_date' => 'date', 'voided_at' => 'datetime', 'amount' => 'decimal:2', 'paid_amount' => 'decimal:2'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function payments()
    {
        return $this->hasMany(ReceivablePayment::class)->orderBy('paid_at');
    }

    public function getOutstandingAttribute(): float
    {
        return $this->status === 'void' ? 0.0 : max(0, round((float) $this->amount - (float) $this->paid_amount, 2));
    }

    public function getPaymentStatusAttribute(): string
    {
        return $this->outstanding <= 0 ? 'paid' : ((float) $this->paid_amount > 0 ? 'partial' : 'unpaid');
    }
}
