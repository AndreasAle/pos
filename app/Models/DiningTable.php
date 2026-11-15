<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiningTable extends Model
{
    protected $fillable = ['business_id', 'outlet_id', 'dining_area_id', 'name', 'public_token', 'status', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function area()
    {
        return $this->belongsTo(DiningArea::class, 'dining_area_id');
    }

    public function sessions()
    {
        return $this->hasMany(TableSession::class);
    }

    public function openSession()
    {
        return $this->hasOne(TableSession::class)->where('status', 'open')->latestOfMany();
    }

    public function scopeForBusiness($query, $id)
    {
        return $query->where('business_id', $id);
    }
}
