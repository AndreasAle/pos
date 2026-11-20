<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Baris mutasi bank/Midtrans yang diimpor. Nominal, tanggal, dan akun tidak boleh diubah setelah impor. */
class StatementLine extends Model
{
    protected $table = 'platform_fin_statement_lines';

    protected $fillable = ['account_code', 'txn_date', 'description', 'amount', 'reference', 'fingerprint', 'status', 'platform_fin_line_id',
        'match_method', 'note', 'import_batch', 'imported_by', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['txn_date' => 'date', 'amount' => 'decimal:2', 'resolved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $s) {
            if ($s->isDirty(['account_code', 'txn_date', 'amount', 'description', 'reference', 'fingerprint'])) {
                throw new LogicException('Data mutasi yang diimpor tidak boleh diubah.');
            }
        });
        static::deleting(fn () => throw new LogicException('Mutasi yang diimpor tidak boleh dihapus; tandai diabaikan dengan alasan.'));
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class, 'platform_fin_line_id');
    }
}
