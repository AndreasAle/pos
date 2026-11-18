<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kotak masuk/keluar WhatsApp resmi tim Yoka (bukan WhatsApp milik toko). */
class PlatformAiWhatsAppMessage extends Model
{
    protected $table = 'platform_ai_whatsapp_messages';

    protected $fillable = ['direction', 'phone', 'name', 'body', 'business_id', 'platform_ai_task_id', 'fingerprint'];

    public function task()
    {
        return $this->belongsTo(PlatformAiTask::class, 'platform_ai_task_id');
    }
}
