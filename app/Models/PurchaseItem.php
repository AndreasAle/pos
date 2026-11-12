<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'unit_cost' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
