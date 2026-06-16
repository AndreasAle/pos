<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemAddon extends Model
{
    protected $fillable = ['order_item_id', 'product_addon_id', 'addon_name', 'price'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function addon()     { return $this->belongsTo(ProductAddon::class, 'product_addon_id'); }
}
