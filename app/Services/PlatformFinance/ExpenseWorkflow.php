<?php

namespace App\Services\PlatformFinance;

use App\Models\PlatformAdmin;
use App\Models\PlatformFinance\Account;
use App\Models\PlatformFinance\AuditEvent;
use App\Models\PlatformFinance\Expense;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Alur biaya: draft → submitted → approved → posted (atau rejected / cancelled).
 *
 * Pengendalian (Financial Accounting bab 8):
 *  - dokumen biaya tidak menyentuh buku besar sebelum disetujui manusia dan diposting,
 *  - setiap transisi dikunci baris (lockForUpdate) dan dicatat di jejak audit,
 *  - invoice yang sama dari vendor yang sama ditolak,
 *  - posting idempoten ('expense:{id}'), jadi klik ganda tidak membuat dua jurnal,
 *  - agen AI hanya boleh membuat draf yang diajukan; persetujuan dan posting wajib admin keuangan.
 * Pemisahan tugas: bila penyetuju = pembuat (usaha satu pemilik), tetap boleh tapi ditandai self_approved
 * dan tercatat di audit, bukan berpura-pura ada penyetuju kedua.
 */
class ExpenseWorkflow
{
    public const EVIDENCE_DISK = 'local';

    public function __construct(private Transactions $tx, private Ledger $ledger) {}

    /** @param array{expense_date:string, account_code:string, paid_from:string, amount:float|int|string, description:string, vendor_id?:?int, invoice_no?:?string} $data */
    public function submit(array $data, string $actorType, int $actorId, ?UploadedFile $evidence = null): Expense
    {
        $date = Carbon::parse($data['expense_date']);
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Jumlah harus lebih dari nol.');
        }
        if ($date->isAfter(today())) {
            throw new InvalidArgumentException('Tanggal biaya tidak boleh di masa depan.');
        }
        if (! Account::where('code', $data['account_code'])->where('type', 'expense')->where('is_active', true)->exists()) {
            throw new InvalidArgumentException('Akun beban tidak valid.');
        }
        $payable = Account::whereIn('subtype', ['cash', 'bank', 'clearing'])->pluck('code')->all();
        if (! in_array($data['paid_from'], $payable, true) && ! array_key_exists($data['paid_from'], Transactions::OWNER_FUNDING) && $data['paid_from'] !== Transactions::PAYABLE) {
            throw new InvalidArgumentException('Sumber pembayaran tidak valid.');
        }
        $due = $data['paid_from'] === Transactions::PAYABLE && filled($data['due_date'] ?? null) ? Carbon::parse($data['due_date']) : null;
        if ($due && $due->lt($date)) {
            throw new InvalidArgumentException('Jatuh tempo tidak boleh sebelum tanggal tagihan.');
        }
        if ($this->ledger->isClosed($date)) {
            throw new InvalidArgumentException('Periode '.$date->format('Y-m').' sudah ditutup.');
        }
        $costCenter = Ledger::costCenter(filled($data['cost_center'] ?? null) ? $data['cost_center'] : null);
        $invoice = filled($data['invoice_no'] ?? null) ? trim($data['invoice_no']) : null;
        if ($invoice && Expense::where('vendor_id', $data['vendor_id'] ?? null)->where('invoice_no', $invoice)->whereNotIn('status', ['rejected', 'cancelled'])->exists()) {
            throw new InvalidArgumentException("Invoice {$invoice} dari vendor ini sudah pernah dicatat.");
        }

        return DB::transaction(function () use ($data, $date, $due, $costCenter, $amount, $invoice, $actorType, $actorId, $evidence) {
            $expense = Expense::create([
                'status' => 'submitted', 'expense_date' => $date, 'vendor_id' => $data['vendor_id'] ?? null,
                'account_code' => $data['account_code'], 'paid_from' => $data['paid_from'], 'amount' => $amount,
                'description' => mb_substr($data['description'], 0, 200), 'invoice_no' => $invoice, 'due_date' => $due, 'cost_center' => $costCenter,
                'created_by_type' => $actorType, 'created_by_id' => $actorId,
            ]);
            if ($evidence) {
                $this->storeEvidence($expense, $evidence);
            }
            AuditEvent::log($actorType, $actorId, 'expense.submitted', $expense, null, $this->snapshot($expense));

            return $expense;
        });
    }

    public function attachEvidence(Expense $expense, UploadedFile $file, PlatformAdmin $by): Expense
    {
        if (in_array($expense->status, ['rejected', 'cancelled'], true)) {
            throw new InvalidArgumentException('Dokumen ini sudah ditutup.');
        }
        $before = $this->snapshot($expense);
        $this->storeEvidence($expense, $file);
        AuditEvent::log('admin', $by->id, 'expense.evidence_attached', $expense, $before, $this->snapshot($expense));

        return $expense;
    }

    /** Setujui lalu posting ke buku besar dalam satu transaksi. */
    public function approveAndPost(Expense $expense, PlatformAdmin $by): Expense
    {
        $this->assertApprover($by);
        $this->assertWithinAuthority($expense, $by);

        return DB::transaction(function () use ($expense, $by) {
            $locked = Expense::lockForUpdate()->findOrFail($expense->id);
            if ($locked->status === 'posted') {
                return $locked; // klik ganda
            }
            if ($locked->status !== 'submitted') {
                throw new InvalidArgumentException("Status {$locked->status} tidak bisa disetujui.");
            }
            $before = $this->snapshot($locked);
            $self = $locked->created_by_type === 'admin' && $locked->created_by_id === $by->id;
            $entry = $this->tx->expense(
                $locked->expense_date->toDateString(), $locked->account_code, (float) $locked->amount, $locked->paid_from,
                $locked->description.($locked->vendor ? ' · '.$locked->vendor->name : ''), $by->id,
                $locked->evidence_name ?? $locked->invoice_no, 'expense:'.$locked->id, $locked->cost_center
            );
            $locked->update(['status' => 'posted', 'approved_by' => $by->id, 'approved_at' => now(), 'self_approved' => $self, 'platform_fin_entry_id' => $entry->id,
                'payment_status' => $locked->paid_from === Transactions::PAYABLE ? 'unpaid' : null]);
            AuditEvent::log('admin', $by->id, 'expense.approved_posted', $locked, $before, $this->snapshot($locked), $self ? 'Disetujui oleh pembuatnya sendiri (pengecualian pemilik tunggal).' : null);

            return $locked;
        });
    }

    /** Bayar tagihan yang sudah diakui sebagai hutang usaha. Dr 2000 / Cr kas-bank-Midtrans (atau talangan pemilik). */
    public function payBill(Expense $expense, string $paidWith, string $date, PlatformAdmin $by): Expense
    {
        $this->assertApprover($by);
        $cash = Account::whereIn('subtype', ['cash', 'bank', 'clearing'])->pluck('code')->all();
        if (! in_array($paidWith, $cash, true) && ! array_key_exists($paidWith, Transactions::OWNER_FUNDING)) {
            throw new InvalidArgumentException('Pilih akun pembayar.');
        }
        $payDate = Carbon::parse($date);
        if ($payDate->isAfter(today())) {
            throw new InvalidArgumentException('Tanggal bayar tidak boleh di masa depan.');
        }

        return DB::transaction(function () use ($expense, $paidWith, $payDate, $by) {
            $locked = Expense::lockForUpdate()->findOrFail($expense->id);
            if ($locked->payment_status === 'paid') {
                return $locked; // klik ganda
            }
            if ($locked->status !== 'posted' || $locked->payment_status !== 'unpaid') {
                throw new InvalidArgumentException('Hanya tagihan yang sudah disetujui dan belum dibayar yang bisa dibayar.');
            }
            if ($payDate->lt($locked->expense_date)) {
                throw new InvalidArgumentException('Tanggal bayar tidak boleh sebelum tanggal tagihan.');
            }
            $before = $this->snapshot($locked);
            $credit = Transactions::OWNER_FUNDING[$paidWith] ?? $paidWith;
            $entry = $this->ledger->post($payDate->toDateString(), 'Bayar tagihan: '.$locked->description.($locked->vendor ? ' · '.$locked->vendor->name : ''),
                [['2000', (float) $locked->amount, 0], [$credit, 0, (float) $locked->amount]], 'manual', 'expense-payment:'.$locked->id, $locked->invoice_no, $by->id, $locked->evidence_name ?? $locked->invoice_no);
            $locked->update(['payment_status' => 'paid', 'paid_at' => $payDate, 'paid_with' => $paidWith, 'payment_entry_id' => $entry->id]);
            AuditEvent::log('admin', $by->id, 'expense.paid', $locked, $before, $this->snapshot($locked));

            return $locked;
        });
    }

    /**
     * Umur hutang usaha per tanggal: belum jatuh tempo, 1-30, 31-60, lebih dari 60 hari.
     *
     * @return array{as_of:string,total:float,overdue:float,buckets:array<string,float>,bills:array<int,array>}
     */
    public function payables(?Carbon $asOf = null): array
    {
        $asOf ??= today();
        $buckets = ['belum_jatuh_tempo' => 0.0, '1_30' => 0.0, '31_60' => 0.0, 'lebih_60' => 0.0];
        $bills = [];
        foreach (Expense::with('vendor')->where('payment_status', 'unpaid')->where('expense_date', '<=', $asOf->toDateString())->orderBy('due_date')->get() as $e) {
            $due = $e->due_date ?? $e->expense_date;
            $late = $due->lt($asOf->copy()->startOfDay()) ? (int) $due->diffInDays($asOf->copy()->startOfDay()) : 0;
            $bucket = $late === 0 ? 'belum_jatuh_tempo' : ($late <= 30 ? '1_30' : ($late <= 60 ? '31_60' : 'lebih_60'));
            $buckets[$bucket] += (float) $e->amount;
            $bills[] = ['id' => $e->id, 'vendor' => $e->vendor?->name, 'description' => $e->description, 'invoice_no' => $e->invoice_no, 'amount' => (float) $e->amount, 'due_date' => $due->toDateString(), 'days_overdue' => $late];
        }
        $total = array_sum($buckets);

        return ['as_of' => $asOf->toDateString(), 'total' => $total, 'overdue' => $total - $buckets['belum_jatuh_tempo'], 'buckets' => $buckets, 'bills' => $bills];
    }

    public function reject(Expense $expense, PlatformAdmin $by, string $reason): Expense
    {
        $this->assertApprover($by);

        return DB::transaction(function () use ($expense, $by, $reason) {
            $locked = Expense::lockForUpdate()->findOrFail($expense->id);
            if ($locked->status !== 'submitted') {
                throw new InvalidArgumentException("Status {$locked->status} tidak bisa ditolak.");
            }
            $before = $this->snapshot($locked);
            $locked->update(['status' => 'rejected', 'rejection_reason' => mb_substr($reason, 0, 200)]);
            AuditEvent::log('admin', $by->id, 'expense.rejected', $locked, $before, $this->snapshot($locked), $reason);

            return $locked;
        });
    }

    /**
     * Matriks persetujuan: super admin tanpa batas (boleh menyetujui pengajuannya sendiri, ditandai).
     * Peran finance: maksimal config('yoka_finance.approval_limit_finance') dan tidak boleh menyetujui pengajuannya sendiri.
     */
    public function assertWithinAuthority(Expense $expense, PlatformAdmin $by): void
    {
        if ($by->role === 'super_admin') {
            return;
        }
        if ($expense->created_by_type === 'admin' && $expense->created_by_id === $by->id) {
            throw new InvalidArgumentException('Pemisahan tugas: admin finance tidak boleh menyetujui biaya yang ia ajukan sendiri.');
        }
        $limit = (float) config('yoka_finance.approval_limit_finance', 5000000);
        if ((float) $expense->amount > $limit) {
            throw new InvalidArgumentException('Di atas batas persetujuan finance (Rp '.number_format($limit, 0, ',', '.').'). Wajib super admin.');
        }
    }

    private function assertApprover(PlatformAdmin $by): void
    {
        if (! $by->canWriteFinance()) {
            throw new InvalidArgumentException('Hanya admin keuangan aktif yang bisa menyetujui.');
        }
    }

    private function storeEvidence(Expense $expense, UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true) || ! in_array($file->getMimeType(), ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new InvalidArgumentException('Bukti harus PDF, JPG, PNG, atau WebP.');
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Bukti maksimal 5 MB.');
        }
        $path = $file->storeAs('finance-evidence/'.now()->format('Y/m'), 'expense-'.$expense->id.'-'.bin2hex(random_bytes(6)).'.'.$ext, self::EVIDENCE_DISK);
        $expense->update(['evidence_path' => $path, 'evidence_name' => mb_substr($file->getClientOriginalName(), 0, 150)]);
    }

    private function snapshot(Expense $e): array
    {
        return $e->only(['status', 'expense_date', 'vendor_id', 'account_code', 'paid_from', 'amount', 'description', 'invoice_no', 'evidence_name', 'approved_by', 'platform_fin_entry_id', 'due_date', 'payment_status', 'paid_at', 'paid_with', 'payment_entry_id']);
    }
}
