<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelIntegration extends Model
{
    public const PROVIDERS = [
        'gofood'     => [
            'name' => 'GoFood',
            'id_label' => 'ID merchant GoBiz',
            'id_hint' => 'Contoh: G951233971',
            'color' => 'bg-red-500',
        ],
        'shopeefood' => [
            'name' => 'ShopeeFood',
            'id_label' => 'ID toko ShopeeFood',
            'id_hint' => 'Ada di aplikasi Shopee Partner',
            'color' => 'bg-orange-500',
        ],
    ];

    protected $fillable = [
        'business_id', 'outlet_id', 'provider', 'merchant_id', 'merchant_name',
        'status', 'consented_at', 'consented_by',
    ];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime'];
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
