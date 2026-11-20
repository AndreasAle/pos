<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Entry extends Model
{
    protected $table = 'platform_fin_entries';

    protected $fillable = ['entry_date', 'description', 'source', 'idempotency_key', 'reference', 'evidence', 'reversal_of_id', 'reversed_by_id', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    protected static function booted(): void
    {
        // Satu-satunya perubahan yang diizinkan: menautkan jurnal pembaliknya.
        static::updating(function (Entry $e) {
            if (array_diff(array_keys($e->getDirty()), ['reversed_by_id', 'updated_at']) !== []) {
                throw new LogicException('Jurnal yang sudah diposting tidak boleh diubah. Buat jurnal pembalik.');
            }
        });
        static::deleting(fn () => throw new LogicException('Jurnal tidak boleh dihapus. Buat jurnal pembalik.'));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(Line::class, 'platform_fin_entry_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'reversal_of_id');
    }
}
