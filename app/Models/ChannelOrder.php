<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pesanan mentah dari GoFood / ShopeeFood. Disimpan apa adanya dan idempoten per
 * (provider, external_order_id), karena platform mengirim ulang bila kita gagal membalas.
 */
class ChannelOrder extends Model
{
    protected $fillable = [
        'business_id', 'outlet_id', 'channel_integration_id', 'provider',
        'external_order_id', 'short_code', 'status', 'payload', 'order_id', 'received_at',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'received_at' => 'datetime'];
    }

    public function integration()
    {
        return $this->belongsTo(ChannelIntegration::class, 'channel_integration_id');
    }
}
