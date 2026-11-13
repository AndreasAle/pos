<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tautan data penjualan (CSV) untuk Google Sheets via IMPORTDATA.
 * Token acak per bisnis; dibuat ulang untuk mencabut tautan lama.
 */
class SheetFeed extends Model
{
    protected $fillable = ['business_id', 'token_hash', 'token', 'last_accessed_at'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'token'            => 'encrypted',
            'last_accessed_at' => 'datetime',
        ];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function issueFor(int $businessId): self
    {
        $token = Str::random(48);

        return static::updateOrCreate(
            ['business_id' => $businessId],
            ['token' => $token, 'token_hash' => static::hash($token), 'last_accessed_at' => null]
        );
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', static::hash($token))->first();
    }
}
