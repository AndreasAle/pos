<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiTelegramIdentity extends Model
{
    protected $fillable = ['platform_admin_id', 'telegram_user_id', 'telegram_username', 'link_code_hash', 'link_expires_at', 'status', 'verified_at', 'revoked_at'];

    protected $hidden = ['link_code_hash'];

    protected function casts(): array
    {
        return ['link_expires_at' => 'datetime', 'verified_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function admin()
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }
}
