<?php

namespace App\Services\PlatformFinance;

use App\Models\PlatformFinance\Entry;
use Illuminate\Support\Carbon;

/**
 * Pintu baca untuk agen AI. TIDAK ADA operasi tulis: agen tidak bisa memposting, membalik,
 * menutup periode, atau memindahkan uang. Semua angka dari Ledger/Metrics (satu sumber),
 * jadi Nanda dan Luna selalu memakai angka yang sama.
 */
class AgentFinanceTools
{
    public function __construct(private Ledger $ledger, private Metrics $metrics, private Review $review) {}

    private function period(array $args): string
    {
        $p = (string) ($args['period'] ?? '');

        return preg_match('/^\d{4}-\d{2}$/', $p) ? $p : today()->format('Y-m');
    }

    public function ledger(array $args): array
    {
        $period = $this->period($args);
        $from = Carbon::parse($period.'-01');
        $to = $from->copy()->endOfMonth()->min(today()->endOfDay());

        $data = match ($args['report'] ?? '') {
            'income_statement' => $this->ledger->incomeStatement($from, $to),
            'balance_sheet' => $this->ledger->balanceSheet($to),
            'cash_flow' => $this->ledger->cashFlow($from, $to),
            'trial_balance' => $this->ledger->trialBalance($to),
            'review' => $this->review->run($to),
            'payables' => app(ExpenseWorkflow::class)->payables($to),
            'cost_centers' => $this->ledger->costCenterReport($from, $to),
            'fixed_assets' => ['aset' => app(FixedAssets::class)->register()],
            'deferred_revenue' => ['saldo_diterima_di_muka' => app(RevenueRecognition::class)->deferredBalance($to), 'catatan' => 'Paket lebih dari 1 bulan diakui merata per bulan layanan.'],
            'reconciliation' => ['bank' => app(Reconciliation::class)->summary('1100', $to), 'midtrans' => app(Reconciliation::class)->summary('1200', $to)],
            'journal' => ['jurnal' => Entry::with('lines.account')->latest('entry_date')->latest('id')->limit(15)->get()->map(fn (Entry $e) => [
                'id' => $e->id, 'tanggal' => $e->entry_date->toDateString(), 'keterangan' => $e->description, 'sumber' => $e->source,
                'bukti' => $e->evidence ? 'ada' : 'tidak ada', 'dibalik' => (bool) $e->reversed_by_id,
                'baris' => $e->lines->map(fn ($l) => [$l->account->code.' '.$l->account->name, (float) $l->debit, (float) $l->credit])->all(),
            ])->all()],
            default => ['error' => 'report tidak dikenal'],
        };
        $empty = Entry::count() === 0;

        return $data + ['sumber_data' => 'buku besar Yoka (platform), terpisah dari data toko klien']
            + ($empty ? ['peringatan' => 'Buku besar masih kosong. Jangan menyimpulkan kondisi keuangan; minta Bos mencatat transaksi di menu Keuangan Yoka.'] : []);
    }

    /**
     * Satu-satunya "tulis" untuk agen: draf biaya berstatus diajukan, menunggu persetujuan manusia.
     * Hanya Nanda (accounting), maksimal 5 draf per hari, tidak pernah memposting jurnal.
     */
    public function draftExpense(\App\Models\PlatformAiAgent $agent, array $args): array
    {
        if ($agent->key !== 'accounting') {
            return ['ok' => false, 'error' => 'Hanya Nanda (accounting) yang boleh mengajukan draf biaya.'];
        }
        if (\App\Models\PlatformFinance\Expense::where('created_by_type', 'agent')->whereDate('created_at', today())->count() >= 5) {
            return ['ok' => false, 'error' => 'Batas 5 draf biaya per hari tercapai. Minta Bos mencatat langsung di Keuangan Yoka.'];
        }
        $vendor = filled($args['vendor'] ?? null)
            ? \App\Models\PlatformFinance\Vendor::firstOrCreate(['name' => mb_substr(trim($args['vendor']), 0, 120)])
            : null;
        try {
            $expense = app(ExpenseWorkflow::class)->submit([
                'expense_date' => (string) ($args['expense_date'] ?? ''), 'account_code' => (string) ($args['account_code'] ?? ''),
                'paid_from' => (string) ($args['paid_from'] ?? ''), 'amount' => $args['amount'] ?? 0,
                'description' => (string) ($args['description'] ?? ''), 'vendor_id' => $vendor?->id, 'invoice_no' => $args['invoice_no'] ?? null, 'due_date' => $args['due_date'] ?? null,
            ], 'agent', $agent->id);
        } catch (\InvalidArgumentException|\Carbon\Exceptions\InvalidFormatException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'draft_id' => $expense->id, 'status' => 'menunggu persetujuan Bos di menu Keuangan Yoka',
            'catatan' => 'Belum masuk buku besar. Jangan bilang sudah tercatat; bilang sudah diajukan untuk disetujui.'];
    }

    /**
     * Tulis terkendali untuk Luna: draf versi anggaran dari skenario. Tidak mengubah anggaran aktif sampai disetujui manusia.
     */
    public function draftBudget(\App\Models\PlatformAiAgent $agent, array $args): array
    {
        if ($agent->key !== 'finance') {
            return ['ok' => false, 'error' => 'Hanya Luna (finance) yang boleh menyusun draf anggaran.'];
        }
        $limit = (int) config('yoka_finance.agent_budget_drafts_per_day', 3);
        if (\App\Models\PlatformFinance\BudgetVersion::where('created_by_type', 'agent')->whereDate('created_at', today())->count() >= $limit) {
            return ['ok' => false, 'error' => "Batas {$limit} draf anggaran per hari tercapai."];
        }
        try {
            $v = app(BudgetPlanner::class)->draft((string) ($args['name'] ?? ''), (string) ($args['scenario'] ?? 'base'),
                (string) ($args['period_from'] ?? today()->addMonth()->format('Y-m')), max(1, min(12, (int) ($args['months'] ?? 3))),
                array_intersect_key($args, array_flip(['customers', 'arpa', 'new_customers_per_month', 'churn_rate', 'cac', 'fee_rate', 'fixed_costs'])),
                'agent', $agent->id, $args['notes'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'version_id' => $v->id, 'status' => 'draf, menunggu persetujuan Bos di Keuangan Yoka',
            'asumsi' => $v->assumptions, 'catatan' => 'Anggaran aktif belum berubah. Sebut asumsinya dan bilang sudah diajukan.'];
    }

    public function metrics(array $args): array
    {
        $period = $this->period($args);
        $from = Carbon::parse($period.'-01');
        $to = $from->copy()->endOfMonth()->min(today()->endOfDay());
        $n = fn (string $k) => (float) ($args[$k] ?? 0);

        return match ($args['metric'] ?? '') {
            'snapshot' => $this->metrics->snapshot($period),
            'saas' => [
                'mrr' => $this->metrics->mrr($to), 'cac' => $this->metrics->cac($from, $to), 'churn' => $this->metrics->churn($period),
                'ltv' => $this->metrics->ltv($period), 'runway' => $this->metrics->runway($from, $to), 'cac_payback' => $this->metrics->cacPayback($from, $to),
                'catatan' => 'Metrik SaaS adalah lapisan analitik Yoka, bukan isi buku. Status insufficient_data berarti belum bisa dihitung: sampaikan alasannya, jangan menebak angka.',
            ],
            'budget_vs_actual' => $this->metrics->budgetVsActual($period),
            'revenue_retention' => $this->metrics->revenueRetention($period),
            'comparison' => $this->metrics->comparison($period),
            'cash_forecast' => app(CashForecast::class)->run((int) ($args['months'] ?? 6), in_array($args['scenario'] ?? 'base', ['conservative', 'base', 'growth'], true) ? ($args['scenario'] ?? 'base') : 'base'),
            'scenarios' => app(BudgetPlanner::class)->compare($args['period_from'] ?? today()->addMonth()->format('Y-m'), max(1, min(12, (int) ($args['months'] ?? 6)))),
            'rolling_forecast' => app(BudgetPlanner::class)->rollingForecast(),
            'liquidity' => $this->metrics->liquidity($to),
            'break_even' => Metrics::breakEven($n('fixed_costs'), $n('price'), $n('variable_cost')),
            'contribution_margin' => Metrics::contributionMargin($n('price'), $n('variable_cost')),
            'variance' => Metrics::variance($n('budget'), $n('actual'), (bool) ($args['is_expense'] ?? true)),
            'payback' => Metrics::paybackPeriod($n('investment'), $n('annual_cash_inflow')),
            'npv' => Metrics::netPresentValue($n('investment'), array_map('floatval', (array) ($args['cash_flows'] ?? [])), $n('rate')),
            default => ['error' => 'metric tidak dikenal'],
        } + ['angka_input_dari_pengguna' => in_array($args['metric'] ?? '', ['break_even', 'contribution_margin', 'variance', 'payback', 'npv'], true)
            ? 'Hasil ini dari angka yang diberikan, bukan dari buku besar. Sebut sebagai asumsi.' : null];
    }
}
