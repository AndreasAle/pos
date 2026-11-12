<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    /**
     * Starter chart of expense accounts, numbered the way a small-business ledger
     * (SAK EMKM style, 6xxx operating expenses / 7xxx other) is usually laid out.
     */
    public const DEFAULTS = [
        ['6101', 'Beban Gaji & Upah', 'operating'],
        ['6102', 'Beban Sewa', 'operating'],
        ['6103', 'Beban Listrik, Air & Gas', 'operating'],
        ['6104', 'Beban Internet & Telepon', 'operating'],
        ['6105', 'Beban Pemasaran & Promosi', 'operating'],
        ['6106', 'Beban Transportasi & Pengiriman', 'operating'],
        ['6107', 'Beban Perlengkapan & Kemasan', 'operating'],
        ['6108', 'Beban Perawatan & Perbaikan', 'operating'],
        ['6109', 'Beban Perizinan & Administrasi', 'operating'],
        ['6110', 'Beban Biaya Platform & Admin Bank', 'operating'],
        ['6199', 'Beban Operasional Lainnya', 'operating'],
        ['7101', 'Beban Bunga & Denda', 'other_expense'],
        ['7199', 'Beban Lain-lain', 'other_expense'],
        ['2105', 'Setoran Pajak (PB1/PPN/PPh)', 'tax'],
    ];

    public static function ensureDefaults(int $businessId): void
    {
        if (static::where('business_id', $businessId)->exists()) {
            return;
        }
        foreach (self::DEFAULTS as [$code, $name, $group]) {
            static::create(['business_id' => $businessId, 'code' => $code, 'name' => $name, 'group' => $group, 'is_system' => true]);
        }
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
