<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $fillable = ['product_id', 'business_id', 'notes'];

    public function product() { return $this->belongsTo(Product::class); }
    public function business() { return $this->belongsTo(Business::class); }
    public function items()   { return $this->hasMany(RecipeItem::class); }
}
