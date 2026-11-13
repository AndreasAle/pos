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
        'access_token', 'refresh_token', 'token_expires_at', 'scopes', 'connected_at', 'last_error',
    ];

    // Token tidak boleh ikut ke JSON / log.
    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'consented_at'     => 'datetime',
            'connected_at'     => 'datetime',
            'token_expires_at' => 'datetime',
            'access_token'     => 'encrypted',
            'refresh_token'    => 'encrypted',
        ];
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
