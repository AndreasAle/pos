<?php

namespace App\Models\PlatformFinance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dokumen biaya Yoka dengan alur persetujuan. Hanya ExpenseWorkflow yang mengubah statusnya. */
class Expense extends Model
{
    public const STATUSES = ['draft', 'submitted', 'approved', 'posted', 'rejected', 'cancelled'];

    protected $table = 'platform_fin_expenses';

    protected $fillable = ['status', 'expense_date', 'vendor_id', 'account_code', 'paid_from', 'amount', 'description', 'invoice_no',
        'evidence_path', 'evidence_name', 'created_by_type', 'created_by_id', 'approved_by', 'approved_at', 'self_approved',
        'rejection_reason', 'platform_fin_entry_id', 'due_date', 'payment_status', 'paid_at', 'paid_with', 'payment_entry_id', 'cost_center'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2', 'approved_at' => 'datetime', 'self_approved' => 'boolean', 'due_date' => 'date', 'paid_at' => 'date'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'platform_fin_entry_id');
    }
}
