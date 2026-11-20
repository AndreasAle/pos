<?php

namespace App\Services\PlatformFinance;

use App\Models\PlatformFinance\Budget;
use App\Models\PlatformFinance\Expense;
use Illuminate\Support\Carbon;

/**
 * Peramalan kas bulanan (Pengantar Manajemen Keuangan: anggaran kas / perencanaan kas).
 *
 * saldo awal = kas + bank hari ini (saldo Midtrans dihitung sebagai penerimaan bulan pertama, karena belum cair)
 * penerimaan  = pendapatan anggaran aktif bila ada, selain itu proyeksi skenario
 * pengeluaran = beban anggaran/proyeksi, tanpa penyusutan (bukan kas)
 * tagihan     = hutang usaha belum dibayar, masuk bulan jatuh temponya (tagihan lewat jatuh tempo masuk bulan pertama)
 */
class CashForecast
{
    public function __construct(private Ledger $ledger, private BudgetPlanner $planner) {}

    public function run(int $months = 6, string $scenario = 'base'): array
    {
        $months = max(1, min(18, $months));
        $start = today()->startOfMonth();
        $projection = $this->planner->project($scenario, $start->format('Y-m'), $months);
        $opening = $this->ledger->cashBalance(today());
        $midtrans = (float) ($this->ledger->balances(null, today())['1200']['balance'] ?? 0);

        $bills = [];
        foreach (Expense::where('payment_status', 'unpaid')->get() as $e) {
            $due = ($e->due_date ?? $e->expense_date)->copy()->startOfMonth();
            $key = $due->lt($start) ? $start->format('Y-m') : $due->format('Y-m');
            $bills[$key] = ($bills[$key] ?? 0) + (float) $e->amount;
        }

        $rows = [];
        $cash = $opening;
        $firstNegative = null;
        foreach ($projection['months'] as $i => $m) {
            $p = $m['period'];
            $budget = Budget::with('account')->where('period', $p)->get();
            $source = $budget->isNotEmpty() ? 'anggaran' : 'proyeksi '.$scenario;
            if ($budget->isNotEmpty()) {
                $inflow = (float) $budget->filter(fn ($b) => $b->account->type === 'revenue')->sum('amount');
                $outflow = (float) $budget->filter(fn ($b) => $b->account->type === 'expense' && $b->account->code !== '7300')->sum('amount');
            } else {
                $lines = $projection['lines'][$p];
                $inflow = (float) ($lines['4000'] ?? 0);
                $outflow = (float) (array_sum($lines) - $inflow - ($lines['7300'] ?? 0));
            }
            if ($i === 0 && $midtrans > 0) {
                $inflow += $midtrans;
            }
            $billsOut = (float) ($bills[$p] ?? 0);
            $open = $cash;
            $cash = round($cash + $inflow - $outflow - $billsOut, 2);
            if ($cash < 0 && $firstNegative === null) {
                $firstNegative = $p;
            }
            $rows[] = ['period' => $p, 'source' => $source, 'opening' => round($open, 2), 'inflow' => round($inflow, 2), 'outflow' => round($outflow, 2), 'bills' => round($billsOut, 2), 'closing' => $cash];
        }

        return [
            'opening_cash' => $opening,
            'midtrans_pending' => $midtrans,
            'rows' => $rows,
            'first_negative_month' => $firstNegative,
            'lowest_closing' => $rows ? min(array_column($rows, 'closing')) : $opening,
            'assumptions' => $projection['assumptions'],
            'note' => 'Peramalan, bukan kepastian. Penyusutan tidak dihitung karena bukan pengeluaran kas.',
        ];
    }
}
