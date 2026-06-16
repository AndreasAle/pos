<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBundleItem extends Model
{
    protected $fillable = ['product_bundle_id', 'product_id', 'qty'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function bundle()  { return $this->belongsTo(ProductBundle::class, 'product_bundle_id'); }
    public function product() { return $this->belongsTo(Product::class); }
}
