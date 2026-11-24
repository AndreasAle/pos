<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneOtp extends Model
{
    protected $fillable = ['user_id', 'phone', 'code_hash', 'expires_at', 'attempts', 'consumed_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'consumed_at' => 'datetime',
            'attempts'    => 'integer',
        ];
    }
}
