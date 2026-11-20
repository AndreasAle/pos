<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformFinance\Account;
use App\Models\PlatformFinance\AuditEvent;
use App\Models\PlatformFinance\Expense;
use App\Models\PlatformFinance\Vendor;
use App\Services\PlatformFinance\ExpenseWorkflow;
use Illuminate\Support\Facades\Storage;
use App\Models\PlatformFinance\Budget;
use App\Models\PlatformFinance\Entry;
use App\Models\PlatformFinance\Period;
use App\Services\PlatformFinance\Ledger;
use App\Services\PlatformFinance\Metrics;
use App\Services\PlatformFinance\MonthEndClose;
use App\Services\PlatformFinance\Reconciliation;
use App\Services\PlatformFinance\BudgetPlanner;
use App\Services\PlatformFinance\CashForecast;
use App\Services\PlatformFinance\FixedAssets;
use App\Services\PlatformFinance\RevenueRecognition;
use App\Models\PlatformFinance\BudgetVersion;
use App\Models\PlatformFinance\StatementLine;
use App\Services\PlatformFinance\Review;
use App\Services\PlatformFinance\Transactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/** Keuangan Yoka sendiri (platform). Hanya super admin dan finance. Agen AI tidak lewat sini. */
class FinanceController extends Controller
{
    /** $write=false: auditor keuangan boleh. $write=true: hanya super admin/finance aktif. */
    private function guard(bool $write = true): int
    {
        $admin = auth('platform')->user();
        abort_unless($write ? $admin->canWriteFinance() : $admin->canViewYokaFinance(), 403);

        return $admin->id;
    }

    public function index(Request $request, Ledger $ledger, Metrics $metrics, Review $review, MonthEndClose $monthEnd)
    {
        $this->guard(false);
        $period = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('period')) ? $request->query('period') : today()->format('Y-m');
        $from = Carbon::parse($period.'-01');
        $to = $from->copy()->endOfMonth()->min(today()->endOfDay());

        return view('platform.finance', [
            'period' => $period,
            'accounts' => Account::where('is_active', true)->orderBy('code')->get(),
            'balances' => $ledger->balances(null, $to),
            'income' => $ledger->incomeStatement($from, $to),
            'cashFlow' => $ledger->cashFlow($from, $to),
            'trial' => $ledger->trialBalance($to),
            'mrr' => $metrics->mrr(today()),
            'cac' => $metrics->cac($from, $to),
            'runway' => $metrics->runway($from, $to),
            'budget' => $metrics->budgetVsActual($period),
            'review' => $review->run($to),
            'entries' => Entry::with('lines.account')->latest('entry_date')->latest('id')->paginate(25),
            'closed' => Period::whereNull('reopened_at')->orderByDesc('period')->pluck('period'),
            'closePeriod' => $closePeriod = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('close')) ? $request->query('close') : today()->subMonth()->format('Y-m'),
            'closeCheck' => $monthEnd->checklist($closePeriod),
            'lastClosed' => Period::whereNull('reopened_at')->orderByDesc('period')->first(),
            'pending' => Expense::with('vendor')->where('status', 'submitted')->orderBy('expense_date')->get(),
            'payables' => app(ExpenseWorkflow::class)->payables(today()),
            'reconAccount' => $reconAccount = in_array($request->query('recon'), ['1000', '1100', '1200'], true) ? $request->query('recon') : '1100',
            'recon' => app(Reconciliation::class)->summary($reconAccount, today(), is_numeric($request->query('closing')) ? (float) $request->query('closing') : null),
            'reconCandidates' => StatementLine::where('account_code', $reconAccount)->where('status', 'unmatched')->orderBy('txn_date')->limit(30)->get()
                ->mapWithKeys(fn ($s) => [$s->id => app(Reconciliation::class)->candidates($s, 31)]),
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(),
            'audit' => AuditEvent::latest('id')->limit(30)->get(),
            'canWrite' => auth('platform')->user()->canWriteFinance(),
            'balanceSheet' => $ledger->balanceSheet($to),
            'comparison' => $metrics->comparison($period),
            'retention' => $metrics->revenueRetention($period),
            'costCenters' => $ledger->costCenterReport($from, $to),
            'forecast' => app(CashForecast::class)->run(6, in_array($request->query('scenario'), ['conservative', 'base', 'growth'], true) ? $request->query('scenario') : 'base'),
            'scenarios' => app(BudgetPlanner::class)->compare(today()->addMonth()->format('Y-m'), 6),
            'rolling' => app(BudgetPlanner::class)->rollingForecast(),
            'versions' => BudgetVersion::latest('id')->limit(10)->get(),
            'assets' => app(FixedAssets::class)->register(),
            'deferred' => app(RevenueRecognition::class)->deferredBalance($to),
            'costCenterLabels' => Ledger::COST_CENTERS,
        ]);
    }

    public function store(Request $request, Transactions $tx)
    {
        $by = $this->guard();
        $cash = Account::whereIn('subtype', ['cash', 'bank', 'clearing'])->pluck('code')->all();
        $data = $request->validate([
            'kind' => 'required|in:expense,income,capital,drawing,transfer,payout,refund',
            'date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:1|max:10000000000',
            'account' => 'nullable|exists:platform_fin_accounts,code',
            'paid_from' => 'nullable|string|max:20',
            'to' => 'nullable|exists:platform_fin_accounts,code',
            'fee' => 'nullable|numeric|min:0',
            'description' => 'required|string|max:200',
            'evidence' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:120',
        ]);
        $paidFrom = (string) ($data['paid_from'] ?? '');
        $valid = fn (string $code) => in_array($code, $cash, true) || array_key_exists($code, Transactions::OWNER_FUNDING);

        if ($data['kind'] === 'expense') {
            return $this->submitExpense($request, $data, $by);
        }

        try {
            $entry = match ($data['kind']) {
                'expense' => $valid($paidFrom) && Account::where('code', $data['account'])->where('type', 'expense')->exists()
                    ? $tx->expense($data['date'], $data['account'], (float) $data['amount'], $paidFrom, $data['description'], $by, $data['evidence'] ?? null)
                    : throw new InvalidArgumentException('Pilih akun beban dan sumber pembayaran.'),
                'income' => in_array($paidFrom, $cash, true) && Account::where('code', $data['account'])->where('type', 'revenue')->exists()
                    ? $tx->otherIncome($data['date'], (float) $data['amount'], $paidFrom, $data['account'], $data['description'], $by)
                    : throw new InvalidArgumentException('Pilih akun pendapatan dan akun penerima.'),
                'capital' => in_array($paidFrom, $cash, true) ? $tx->ownerCapital($data['date'], (float) $data['amount'], $paidFrom, $data['description'], $by) : throw new InvalidArgumentException('Pilih akun penerima modal.'),
                'drawing' => in_array($paidFrom, $cash, true) ? $tx->ownerDrawing($data['date'], (float) $data['amount'], $paidFrom, $data['description'], $by) : throw new InvalidArgumentException('Pilih akun sumber.'),
                'transfer' => in_array($paidFrom, $cash, true) && in_array($data['to'] ?? '', $cash, true)
                    ? $tx->transfer($data['date'], (float) $data['amount'], $paidFrom, $data['to'], $data['description'], $by)
                    : throw new InvalidArgumentException('Pilih akun asal dan tujuan.'),
                'payout' => $tx->midtransPayout($data['date'], (float) $data['amount'], (float) ($data['fee'] ?? 0), in_array($data['to'] ?? '', $cash, true) ? $data['to'] : '1100', $data['reference'] ?? null, $by),
                'refund' => in_array($paidFrom, $cash, true) ? $tx->refund($data['date'], (float) $data['amount'], $paidFrom, $data['description'], $by) : throw new InvalidArgumentException('Pilih akun sumber refund.'),
            };
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['finance' => $e->getMessage()]);
        }
        AuditEvent::log('admin', $by, 'entry.posted', $entry, null, ['kind' => $data['kind'], 'amount' => $data['amount'], 'description' => $data['description']]);

        return back()->with('status', 'Transaksi tercatat.');
    }

    private function submitExpense(Request $request, array $data, int $by)
    {
        $request->validate(['evidence_file' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,webp', 'vendor' => 'nullable|string|max:120', 'invoice_no' => 'nullable|string|max:80', 'due_date' => 'nullable|date', 'cost_center' => 'nullable|in:'.implode(',', array_keys(Ledger::COST_CENTERS))]);
        $vendor = filled($request->input('vendor')) ? Vendor::firstOrCreate(['name' => trim($request->input('vendor'))]) : null;
        $workflow = app(ExpenseWorkflow::class);
        try {
            $expense = $workflow->submit([
                'expense_date' => $data['date'], 'account_code' => (string) $data['account'], 'paid_from' => (string) $data['paid_from'],
                'amount' => $data['amount'], 'description' => $data['description'], 'vendor_id' => $vendor?->id, 'invoice_no' => $request->input('invoice_no'),
                'due_date' => $request->input('due_date'), 'cost_center' => $request->input('cost_center'),
            ], 'admin', $by, $request->file('evidence_file'));
            if ($request->boolean('approve_now')) {
                $workflow->approveAndPost($expense, auth('platform')->user());

                return back()->with('status', 'Biaya disetujui dan diposting.');
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['finance' => $e->getMessage()]);
        }

        return back()->with('status', 'Biaya diajukan dan menunggu persetujuan.');
    }

    public function approveExpense(Expense $expense, ExpenseWorkflow $workflow)
    {
        $this->guard();
        try {
            $workflow->approveAndPost($expense, auth('platform')->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }

        return back()->with('status', 'Biaya disetujui dan diposting ke buku besar.');
    }

    public function payExpense(Request $request, Expense $expense, ExpenseWorkflow $workflow)
    {
        $this->guard();
        $data = $request->validate(['paid_with' => 'required|string|max:20', 'date' => 'required|date|before_or_equal:today']);
        try {
            $workflow->payBill($expense, $data['paid_with'], $data['date'], auth('platform')->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }

        return back()->with('status', 'Tagihan dibayar dan dicatat.');
    }

    public function importStatement(Request $request, Reconciliation $recon)
    {
        $this->guard();
        $data = $request->validate(['account' => 'required|in:1000,1100,1200', 'csv' => 'nullable|string|max:400000', 'file' => 'nullable|file|max:2048|mimes:csv,txt']);
        $csv = $request->hasFile('file') ? (string) file_get_contents($request->file('file')->getRealPath()) : (string) ($data['csv'] ?? '');
        if (trim($csv) === '') {
            return back()->withErrors(['finance' => 'Tempel isi CSV atau pilih file.']);
        }
        try {
            $r = $recon->import($data['account'], $csv, auth('platform')->user());
            $auto = $recon->autoMatch($data['account'], auth('platform')->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }
        $msg = "{$r['imported']} mutasi diimpor, {$r['duplicates']} sudah pernah diimpor, {$auto} cocok otomatis.";

        return redirect()->route('platform.finance', ['recon' => $data['account']])->with('status', $msg)->withErrors($r['errors'] ? ['finance' => implode(' ', $r['errors'])] : []);
    }

    public function reconcileStatement(Request $request, StatementLine $line, Reconciliation $recon)
    {
        $this->guard();
        $data = $request->validate(['action' => 'required|in:match,ignore,unmatch', 'line_id' => 'required_if:action,match|nullable|integer', 'reason' => 'nullable|string|max:200']);
        try {
            match ($data['action']) {
                'match' => $recon->match($line, (int) $data['line_id'], auth('platform')->user(), $data['reason'] ?? null),
                'ignore' => $recon->ignore($line, auth('platform')->user(), (string) ($data['reason'] ?? '')),
                'unmatch' => $recon->unmatch($line, auth('platform')->user(), (string) ($data['reason'] ?? 'dibatalkan')),
            };
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }

        return back()->with('status', 'Mutasi diperbarui.');
    }

    public function rejectExpense(Request $request, Expense $expense, ExpenseWorkflow $workflow)
    {
        $this->guard();
        $reason = $request->validate(['reason' => 'required|string|max:200'])['reason'];
        try {
            $workflow->reject($expense, auth('platform')->user(), $reason);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }

        return back()->with('status', 'Biaya ditolak.');
    }

    public function evidence(Expense $expense)
    {
        $this->guard(false);
        abort_unless($expense->evidence_path && Storage::disk(ExpenseWorkflow::EVIDENCE_DISK)->exists($expense->evidence_path), 404);

        return Storage::disk(ExpenseWorkflow::EVIDENCE_DISK)->download($expense->evidence_path, $expense->evidence_name);
    }

    public function reverse(Request $request, Entry $entry, Ledger $ledger)
    {
        $by = $this->guard();
        $reason = $request->validate(['reason' => 'required|string|max:150'])['reason'];
        try {
            $reversal = $ledger->reverse($entry, $reason, $by);
            AuditEvent::log('admin', $by, 'entry.reversed', $entry, null, ['reversal_id' => $reversal->id], $reason);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }

        return back()->with('status', 'Jurnal pembalik dibuat. Jurnal asli tetap tersimpan.');
    }

    public function budget(Request $request)
    {
        $this->guard();
        $data = $request->validate(['period' => 'required|date_format:Y-m', 'account' => 'required|exists:platform_fin_accounts,code', 'amount' => 'required|numeric|min:0']);
        $budget = Budget::updateOrCreate(
            ['period' => $data['period'], 'platform_fin_account_id' => Account::where('code', $data['account'])->value('id')],
            ['amount' => $data['amount']]
        );

        AuditEvent::log('admin', auth('platform')->id(), 'budget.set', $budget, null, $data);

        return back()->with('status', 'Anggaran disimpan.');
    }

    public function close(Request $request, MonthEndClose $monthEnd)
    {
        $this->guard();
        $data = $request->validate(['period' => 'required|date_format:Y-m']);
        try {
            $monthEnd->close($data['period'], auth('platform')->user(), $request->boolean('acknowledge'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }

        return back()->with('status', "Periode {$data['period']} ditutup.");
    }

    public function reopen(Request $request, MonthEndClose $monthEnd)
    {
        $this->guard();
        $data = $request->validate(['period' => 'required|date_format:Y-m', 'reason' => 'required|string|max:255']);
        try {
            $monthEnd->reopen($data['period'], auth('platform')->user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }

        return back()->with('status', "Periode {$data['period']} dibuka ulang. Tutup lagi setelah koreksi.");
    }

    public function budgetDraft(Request $request, BudgetPlanner $planner)
    {
        $by = $this->guard();
        $data = $request->validate([
            'scenario' => 'required|in:conservative,base,growth', 'period_from' => 'required|date_format:Y-m', 'months' => 'required|integer|min:1|max:12',
            'name' => 'nullable|string|max:120', 'arpa' => 'nullable|numeric|min:0', 'new_customers_per_month' => 'nullable|numeric|min:0',
            'churn_rate' => 'nullable|numeric|min:0|max:1', 'cac' => 'nullable|numeric|min:0', 'notes' => 'nullable|string|max:500',
        ]);
        try {
            $planner->draft((string) ($data['name'] ?? ''), $data['scenario'], $data['period_from'], (int) $data['months'],
                array_filter(array_intersect_key($data, array_flip(['arpa', 'new_customers_per_month', 'churn_rate', 'cac'])), fn ($v) => $v !== null), 'admin', $by, $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }

        return back()->with('status', 'Draf anggaran dibuat. Setujui agar menjadi anggaran aktif.');
    }

    public function budgetDecide(Request $request, BudgetVersion $version, BudgetPlanner $planner)
    {
        $this->guard();
        $data = $request->validate(['decision' => 'required|in:approve,reject', 'reason' => 'required_if:decision,reject|nullable|string|max:200']);
        try {
            $data['decision'] === 'approve'
                ? $planner->approve($version, auth('platform')->user())
                : $planner->reject($version, auth('platform')->user(), (string) $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }

        return back()->with('status', $data['decision'] === 'approve' ? 'Anggaran disetujui dan aktif.' : 'Draf anggaran ditolak.');
    }

    public function assetStore(Request $request, FixedAssets $assets)
    {
        $this->guard();
        $data = $request->validate([
            'name' => 'required|string|max:150', 'acquired_at' => 'required|date|before_or_equal:today', 'cost' => 'required|numeric|min:1',
            'salvage_value' => 'nullable|numeric|min:0', 'useful_life_months' => 'required|integer|min:1|max:600', 'paid_from' => 'required|string|max:20',
            'cost_center' => 'nullable|in:'.implode(',', array_keys(Ledger::COST_CENTERS)), 'evidence' => 'nullable|string|max:255',
        ]);
        try {
            $assets->acquire($data, auth('platform')->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }

        return back()->with('status', 'Aset dicatat.');
    }

    public function depreciate(Request $request, FixedAssets $assets)
    {
        $by = $this->guard();
        $period = $request->validate(['period' => 'required|date_format:Y-m|before:'.today()->format('Y-m').'-01'])['period'];
        try {
            $done = $assets->depreciate($period, $by);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }
        if ($done) {
            AuditEvent::log('admin', $by, 'asset.depreciated', \App\Models\PlatformFinance\Depreciation::latest('id')->first(), null, ['period' => $period, 'items' => $done]);
        }

        return back()->with('status', count($done).' aset disusutkan untuk '.$period.'.');
    }

    public function recognizeRevenue(RevenueRecognition $recognition)
    {
        $this->guard();
        try {
            $n = $recognition->recognizeDue();
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['finance' => $e->getMessage()]);
        }

        return back()->with('status', "{$n} jadwal pendapatan diakui.");
    }

    /** Ekspor CSV (bisa dibuka di Excel). Auditor keuangan boleh. */
    public function export(Request $request, Ledger $ledger, Metrics $metrics)
    {
        $by = $this->guard(false);
        $report = (string) $request->query('report');
        $period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('period')) ? $request->query('period') : today()->format('Y-m');
        $from = Carbon::parse($period.'-01');
        $to = $from->copy()->endOfMonth()->min(today()->endOfDay());

        $rows = match ($report) {
            'income' => $this->incomeRows($ledger->incomeStatement($from, $to)),
            'balance' => (function () use ($ledger, $to) {
                $bs = $ledger->balanceSheet($to);
                $out = [['Bagian', 'Kode', 'Akun', 'Saldo']];
                foreach (['assets' => 'Aset', 'liabilities' => 'Kewajiban', 'equity' => 'Ekuitas'] as $k => $label) {
                    foreach ($bs[$k] as $r) {
                        $out[] = [$label, $r['code'], $r['name'], $r['balance']];
                    }
                }
                $out[] = ['Ekuitas', '', 'Laba berjalan', $bs['current_earnings']];
                $out[] = ['Ekuitas', '', 'Prive', -$bs['drawings']];
                $out[] = ['Total', '', 'Aset', $bs['total_assets']];
                $out[] = ['Total', '', 'Kewajiban + Ekuitas', $bs['total_liabilities'] + $bs['total_equity']];

                return $out;
            })(),
            'trial' => array_merge([['Kode', 'Akun', 'Debit', 'Kredit']], array_map(fn ($r) => [$r['code'], $r['name'], $r['debit'], $r['credit']], $ledger->trialBalance($to)['rows'] ?? [])),
            'journal' => (function () use ($from, $to) {
                $out = [['Tanggal', 'No. jurnal', 'Keterangan', 'Kode', 'Akun', 'Debit', 'Kredit', 'Cost center', 'Sumber', 'Bukti', 'Referensi']];
                foreach (Entry::with('lines.account')->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])->orderBy('entry_date')->orderBy('id')->get() as $e) {
                    foreach ($e->lines as $l) {
                        $out[] = [$e->entry_date->toDateString(), $e->id, $e->description, $l->account->code, $l->account->name, (float) $l->debit, (float) $l->credit, $l->cost_center, $e->source, $e->evidence, $e->reference];
                    }
                }

                return $out;
            })(),
            'payables' => array_merge([['Vendor', 'Keterangan', 'Invoice', 'Jatuh tempo', 'Telat (hari)', 'Jumlah']],
                array_map(fn ($b) => [$b['vendor'], $b['description'], $b['invoice_no'], $b['due_date'], $b['days_overdue'], $b['amount']], app(ExpenseWorkflow::class)->payables($to)['bills'])),
            'cash_forecast' => array_merge([['Bulan', 'Sumber', 'Saldo awal', 'Masuk', 'Keluar', 'Tagihan', 'Saldo akhir']],
                array_map(fn ($r) => [$r['period'], $r['source'], $r['opening'], $r['inflow'], $r['outflow'], $r['bills'], $r['closing']], app(CashForecast::class)->run(12)['rows'])),
            'budget' => array_merge([['Kode', 'Akun', 'Anggaran', 'Aktual', 'Selisih', 'F/U']],
                array_map(fn ($r) => [$r['code'], $r['name'], $r['inputs']['budget'], $r['inputs']['actual'], $r['value'], $r['label']], $metrics->budgetVsActual($period)['rows'])),
            'comparison' => (function () use ($metrics, $period) {
                $c = $metrics->comparison($period);
                $out = [['Pos', 'Bulan ini', 'Bulan lalu', 'Perubahan', 'YTD', 'YTD tahun lalu', 'Perubahan YTD']];
                foreach ($c['mom'] as $k => $v) {
                    $out[] = [$k, $v['current'], $v['compare'], $v['change'], $c['ytd'][$k]['current'], $c['ytd'][$k]['compare'], $c['ytd'][$k]['change']];
                }

                return $out;
            })(),
            'cost_centers' => (function () use ($ledger, $from, $to) {
                $r = $ledger->costCenterReport($from, $to);
                $out = [['Cost center', 'Beban']];
                foreach ($r['totals'] as $k => $v) {
                    $out[] = [$r['labels'][$k] ?? $k, $v];
                }

                return $out;
            })(),
            'assets' => array_merge([['Aset', 'Tanggal perolehan', 'Harga', 'Akumulasi penyusutan', 'Nilai buku', 'Penyusutan/bulan', 'Umur (bulan)', 'Status']],
                array_map(fn ($a) => [$a['name'], $a['acquired_at'], $a['cost'], $a['accumulated'], $a['book_value'], $a['monthly'], $a['life_months'], $a['status']], app(FixedAssets::class)->register())),
            default => abort(404),
        };
        AuditEvent::log('admin', $by, 'report.exported', Entry::latest('id')->first() ?? new Entry(), null, ['report' => $report, 'period' => $period]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $r), ';');
            }
            fclose($out);
        }, "keuangan-yoka-{$report}-{$period}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function incomeRows(array $is): array
    {
        $out = [['Pos', 'Jumlah']];
        foreach (['gross_revenue' => 'Pendapatan kotor', 'refunds' => 'Refund', 'net_revenue' => 'Pendapatan bersih', 'cost_of_revenue' => 'Biaya pokok layanan', 'gross_profit' => 'Laba kotor', 'marketing' => 'Marketing', 'operating_expenses' => 'Beban operasional', 'net_income' => 'Laba bersih'] as $k => $label) {
            $out[] = [$label, $is[$k]];
        }
        foreach ($is['expense_lines'] as $l) {
            $out[] = ['  '.$l['code'].' '.$l['name'], $l['amount']];
        }

        return $out;
    }
}
