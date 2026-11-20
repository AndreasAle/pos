<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $table = 'platform_fin_vendors';

    protected $fillable = ['name', 'default_account', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
