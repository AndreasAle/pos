<?php

namespace App\Services\PlatformFinance;

use App\Models\BusinessSubscription;
use App\Models\PlatformAdmin;
use App\Models\PlatformAiRun;
use App\Models\PlatformFinance\AuditEvent;
use App\Models\PlatformFinance\Budget;
use App\Models\PlatformFinance\Entry;
use App\Models\PlatformFinance\Expense;
use App\Models\PlatformFinance\Line;
use App\Models\PlatformFinance\Period;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tutup buku bulanan dengan checklist deterministik.
 * fail = memblokir penutupan. warn = boleh ditutup asal penutup menyatakan sudah meninjau (tercatat di audit).
 * Buka ulang hanya super admin, wajib alasan, hanya periode tertutup terakhir.
 */
class MonthEndClose
{
    public function __construct(private Ledger $ledger) {}

    /** @return array{period:string,items:array<int,array{key:string,label:string,status:string,detail:string}>,can_close:bool,has_warnings:bool,closed:bool} */
    public function checklist(string $period): array
    {
        $from = $this->start($period);
        $to = $from->copy()->endOfMonth();
        $items = [];
        $add = function (string $key, string $label, string $status, string $detail = '') use (&$items) {
            $items[] = compact('key', 'label', 'status', 'detail');
        };

        $add('period_ended', 'Periode sudah berakhir', $to->lt(today()) ? 'ok' : 'fail', $to->lt(today()) ? '' : 'Periode berjalan belum bisa ditutup.');

        $prev = $from->copy()->subMonth()->format('Y-m');
        $prevHasEntries = Entry::where('entry_date', '<', $from->toDateString())->exists();
        $prevClosed = Period::where('period', $prev)->whereNull('reopened_at')->exists();
        $add('previous_closed', 'Periode sebelumnya sudah ditutup', ! $prevHasEntries || $prevClosed ? 'ok' : 'warn', ! $prevHasEntries || $prevClosed ? '' : "Periode {$prev} masih terbuka.");

        $tb = $this->ledger->trialBalance($to);
        $add('trial_balance', 'Neraca saldo seimbang', $tb['balanced'] ? 'ok' : 'fail', $tb['balanced'] ? '' : "Debit {$tb['total_debit']} vs kredit {$tb['total_credit']}.");

        $pending = Expense::where('status', 'submitted')->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->count();
        $add('pending_expenses', 'Tidak ada biaya menunggu persetujuan', $pending === 0 ? 'ok' : 'fail', $pending ? "{$pending} biaya di periode ini belum disetujui atau ditolak." : '');

        $paid = BusinessSubscription::whereNotNull('paid_at')->whereNotNull('payment_token')->where('amount', '>', 0)
            ->whereBetween('paid_at', [$from, $to->copy()->endOfDay()])->pluck('id');
        $journaled = Entry::whereIn('idempotency_key', $paid->map(fn ($id) => "midtrans:subscription:{$id}"))->count();
        $missing = $paid->count() - $journaled;
        $add('midtrans_journaled', 'Semua pembayaran Midtrans terjurnal', $missing <= 0 ? 'ok' : 'fail', $missing > 0 ? "{$missing} pembayaran belum masuk buku besar. Jalankan yoka:finance-sync-midtrans." : '');

        $midtrans = $this->ledger->balances(null, $to)['1200']['balance'] ?? 0;
        $add('midtrans_not_negative', 'Saldo Midtrans tidak negatif', $midtrans >= 0 ? 'ok' : 'fail', $midtrans < 0 ? 'Pencairan dicatat melebihi pembayaran masuk.' : '');
        $add('midtrans_reconciled', 'Saldo Midtrans dicocokkan dengan settlement', $midtrans <= 0 ? 'ok' : 'warn', $midtrans > 0 ? 'Saldo tertahan Rp '.$this->rp($midtrans).'. Pastikan sama dengan dashboard Midtrans.' : '');

        $entries = Entry::whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])->whereNull('reversal_of_id')->whereNull('reversed_by_id');
        $noEvidence = (clone $entries)->where('source', 'manual')->whereNull('evidence')->count();
        $add('evidence', 'Jurnal manual punya bukti', $noEvidence === 0 ? 'ok' : 'warn', $noEvidence ? "{$noEvidence} jurnal manual tanpa bukti." : '');

        $dups = Line::query()->join('platform_fin_entries as e', 'e.id', '=', 'platform_fin_lines.platform_fin_entry_id')
            ->whereBetween('e.entry_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('e.reversal_of_id')->whereNull('e.reversed_by_id')->where('e.source', 'manual')->where('platform_fin_lines.debit', '>', 0)
            ->groupBy('e.entry_date', 'platform_fin_lines.platform_fin_account_id', 'platform_fin_lines.debit')
            ->havingRaw('COUNT(*) > 1')->selectRaw('COUNT(*) as n')->get()->count();
        $add('duplicates', 'Tidak ada kemungkinan transaksi dobel', $dups === 0 ? 'ok' : 'warn', $dups ? "{$dups} kelompok jurnal dengan tanggal, akun, dan nominal sama." : '');

        $aiUsd = (float) PlatformAiRun::whereBetween('created_at', [$from, $to->copy()->endOfDay()])->sum('estimated_cost_usd');
        $aiBooked = $this->ledger->balances($from, $to)['5100']['balance'] ?? 0;
        $add('ai_cost', 'Beban AI dicatat dari invoice', $aiUsd <= 0 || $aiBooked > 0 ? 'ok' : 'warn', $aiUsd > 0 && $aiBooked <= 0 ? 'Telemetri memperkirakan USD '.round($aiUsd, 2).', beban AI belum dicatat.' : '');

        $reimb = $this->ledger->balances(null, $to)['2200']['balance'] ?? 0;
        $add('reimbursement', 'Talangan pemilik ditinjau', $reimb <= 0 ? 'ok' : 'warn', $reimb > 0 ? 'Yoka masih berhutang Rp '.$this->rp($reimb).' ke pemilik.' : '');

        $pendingRev = app(RevenueRecognition::class)->pendingCount($to);
        $add('revenue_recognized', 'Pendapatan diterima di muka sudah diakui', $pendingRev === 0 ? 'ok' : 'fail', $pendingRev ? "{$pendingRev} jadwal pengakuan pendapatan belum diposting. Jalankan yoka:finance-recognize-revenue." : '');

        $dueAssets = app(FixedAssets::class)->dueAssets($period)->count();
        $add('depreciation', 'Penyusutan aset sudah diposting', $dueAssets === 0 ? 'ok' : 'fail', $dueAssets ? "{$dueAssets} aset belum disusutkan untuk {$period}." : '');

        $unmatched = app(Reconciliation::class)->unmatchedCount($from, $to);
        $add('statements', 'Mutasi bank/Midtrans sudah dicocokkan', $unmatched === 0 ? 'ok' : 'warn', $unmatched ? "{$unmatched} mutasi rekening periode ini belum dicocokkan ke jurnal." : '');

        $ap = app(ExpenseWorkflow::class)->payables($to);
        $add('payables', 'Hutang usaha ditinjau', $ap['overdue'] <= 0 ? 'ok' : 'warn', $ap['overdue'] > 0 ? 'Rp '.$this->rp($ap['overdue']).' tagihan lewat jatuh tempo.' : '');

        $next = $from->copy()->addMonth()->format('Y-m');
        $hasBudget = Budget::where('period', $next)->exists();
        $add('next_budget', 'Anggaran bulan depan disiapkan', $hasBudget ? 'ok' : 'warn', $hasBudget ? '' : "Belum ada anggaran {$next}.");

        $statuses = array_column($items, 'status');

        return [
            'period' => $period,
            'items' => $items,
            'can_close' => ! in_array('fail', $statuses, true),
            'has_warnings' => in_array('warn', $statuses, true),
            'closed' => Period::where('period', $period)->whereNull('reopened_at')->exists(),
        ];
    }

    public function close(string $period, PlatformAdmin $admin, bool $acknowledgeWarnings = false): Period
    {
        $this->assertFinance($admin);
        $check = $this->checklist($period);
        if ($check['closed']) {
            throw new InvalidArgumentException("Periode {$period} sudah ditutup.");
        }
        if (! $check['can_close']) {
            $blocking = collect($check['items'])->where('status', 'fail')->pluck('label')->implode('; ');
            throw new InvalidArgumentException("Belum bisa tutup buku: {$blocking}.");
        }
        if ($check['has_warnings'] && ! $acknowledgeWarnings) {
            throw new InvalidArgumentException('Ada peringatan di checklist. Centang "sudah saya tinjau" untuk menutup.');
        }

        return DB::transaction(function () use ($period, $admin, $check, $acknowledgeWarnings) {
            $income = $this->ledger->incomeStatement($this->start($period), $this->start($period)->endOfMonth());
            $row = $this->ledger->closePeriod($period, $admin->id);
            $row->update(['checklist' => ['items' => $check['items'], 'net_income' => $income['net_income']], 'warnings_acknowledged' => $check['has_warnings'] && $acknowledgeWarnings]);
            AuditEvent::log('admin', $admin->id, 'period.closed', $row, null, ['period' => $period, 'warnings' => collect($check['items'])->where('status', 'warn')->pluck('key')->values()->all(), 'net_income' => $income['net_income']]);

            return $row->fresh();
        });
    }

    public function reopen(string $period, PlatformAdmin $admin, string $reason): Period
    {
        if (! $admin->is_active || $admin->role !== 'super_admin') {
            throw new InvalidArgumentException('Hanya super admin yang boleh membuka ulang periode.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('Alasan buka ulang minimal 10 karakter.');
        }

        return DB::transaction(function () use ($period, $admin, $reason) {
            $row = Period::where('period', $period)->whereNull('reopened_at')->lockForUpdate()->first();
            if (! $row) {
                throw new InvalidArgumentException("Periode {$period} tidak dalam keadaan tertutup.");
            }
            if (Period::where('period', '>', $period)->whereNull('reopened_at')->exists()) {
                throw new InvalidArgumentException('Buka ulang periode yang lebih baru dulu. Hanya periode tertutup terakhir yang boleh dibuka.');
            }
            $before = $row->only(['closed_at', 'closed_by', 'reopen_count']);
            $row->update(['reopened_at' => now(), 'reopened_by' => $admin->id, 'reopen_reason' => $reason, 'reopen_count' => $row->reopen_count + 1]);
            AuditEvent::log('admin', $admin->id, 'period.reopened', $row, $before, ['period' => $period, 'reopen_count' => $row->reopen_count], $reason);

            return $row->fresh();
        });
    }

    private function assertFinance(PlatformAdmin $admin): void
    {
        if (! $admin->is_active || ! $admin->canViewFinancials()) {
            throw new InvalidArgumentException('Hanya admin keuangan aktif yang boleh menutup buku.');
        }
    }

    private function start(string $period): Carbon
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new InvalidArgumentException('Format periode YYYY-MM.');
        }

        return Carbon::parse($period.'-01')->startOfDay();
    }

    private function rp(float $v): string
    {
        return number_format($v, 0, ',', '.');
    }
}
