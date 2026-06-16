<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'business_id', 'type', 'channel', 'recipient',
        'subject', 'sent', 'sent_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'sent'    => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
