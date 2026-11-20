<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Line extends Model
{
    protected $table = 'platform_fin_lines';

    public $timestamps = false;

    protected $fillable = ['platform_fin_entry_id', 'platform_fin_account_id', 'debit', 'credit', 'memo', 'cost_center'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        // Baris jurnal yang sudah diposting tidak boleh diubah atau dihapus (koreksi = jurnal pembalik).
        static::updating(fn () => throw new LogicException('Baris jurnal tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Baris jurnal tidak boleh dihapus.'));
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'platform_fin_account_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'platform_fin_entry_id');
    }
}
