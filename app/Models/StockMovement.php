<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'ingredient_id', 'business_id', 'outlet_id', 'order_id', 'user_id',
        'type', 'qty', 'stock_before', 'stock_after', 'unit_cost', 'reference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty'          => 'decimal:3',
            'stock_before' => 'decimal:3',
            'stock_after'  => 'decimal:3',
            'unit_cost'    => 'decimal:2',
        ];
    }

    public function ingredient() { return $this->belongsTo(Ingredient::class); }
    public function business()   { return $this->belongsTo(Business::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function order()      { return $this->belongsTo(Order::class); }
}
