<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableSession extends Model
{
    protected $fillable = ['business_id', 'outlet_id', 'dining_table_id', 'guest_token', 'status', 'guest_name', 'guest_phone', 'opened_at', 'expires_at', 'closed_at'];

    protected $casts = ['opened_at' => 'datetime', 'expires_at' => 'datetime', 'closed_at' => 'datetime'];

    public function table()
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function diningTable()
    {
        return $this->table();
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
