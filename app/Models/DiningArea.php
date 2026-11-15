<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiningArea extends Model
{
    protected $fillable = ['business_id', 'outlet_id', 'name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function tables()
    {
        return $this->hasMany(DiningTable::class);
    }

    public function scopeForBusiness($query, $id)
    {
        return $query->where('business_id', $id);
    }
}
