<?php

namespace App\Services\PlatformFinance;

use App\Models\PlatformAdmin;
use App\Models\PlatformFinance\Account;
use App\Models\PlatformFinance\AuditEvent;
use App\Models\PlatformFinance\Budget;
use App\Models\PlatformFinance\BudgetLine;
use App\Models\PlatformFinance\BudgetVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Anggaran berversi dengan skenario (Managerial Accounting bab 7: master budget; bab 10: flexible budget).
 *
 * Proyeksi deterministik dari asumsi yang SELALU ditampilkan:
 *   pelanggan_t = pelanggan_(t-1) x (1 - churn) + pelanggan baru
 *   pendapatan langganan = pelanggan x ARPA
 *   fee gateway = pendapatan x rasio fee historis
 *   biaya variabel per pelanggan (AI, WA) = rata-rata 3 bulan / pelanggan sekarang
 *   marketing = pelanggan baru x CAC (bila CAC terhitung), selain itu rata-rata 3 bulan
 *   biaya tetap = rata-rata 3 bulan
 * Skenario hanya mengalikan asumsi; angka dasar berasal dari buku besar dan data langganan.
 */
class BudgetPlanner
{
    public const FACTORS = [
        'conservative' => ['new' => 0.5, 'churn' => 1.5, 'cost' => 1.1, 'marketing' => 1.0], // belanja iklan sama, hasil lebih sedikit
        'base' => ['new' => 1.0, 'churn' => 1.0, 'cost' => 1.0, 'marketing' => 1.0],
        'growth' => ['new' => 1.5, 'churn' => 0.8, 'cost' => 1.05, 'marketing' => 1.3],
    ];

    public function __construct(private Ledger $ledger, private Metrics $metrics) {}

    /** Asumsi dasar dari data nyata 3 bulan terakhir yang sudah lewat. */
    public function baseline(?Carbon $asOf = null): array
    {
        $asOf ??= today();
        $from = $asOf->copy()->startOfMonth()->subMonths(3);
        $to = $asOf->copy()->startOfMonth()->subDay();
        $avg = [];
        foreach ($this->ledger->balances($from, $to) as $code => $b) {
            if ($b['type'] === 'expense' && $code !== '7300') {
                $avg[$code] = round($b['balance'] / 3, 2);
            }
        }
        $is = $this->ledger->incomeStatement($from, $to);
        $mrr = $this->metrics->mrr($asOf);
        $customers = (int) ($mrr['paying_accounts'] ?? 0);
        $churnVals = [];
        foreach ([1, 2, 3] as $i) {
            $c = $this->metrics->churn($asOf->copy()->startOfMonth()->subMonths($i)->format('Y-m'));
            if ($c['status'] === 'ok') {
                $churnVals[] = $c['value'];
            }
        }
        $cac = $this->metrics->cac($from, $to);
        $gross = (float) $is['gross_revenue'];

        return [
            'customers' => $customers,
            'arpa' => (float) ($mrr['arpa'] ?? 0),
            'new_customers_per_month' => round($this->metrics->newPayingCustomers($from, $to) / 3, 2),
            'churn_rate' => $churnVals ? round(array_sum($churnVals) / count($churnVals), 4) : 0.05,
            'churn_source' => $churnVals ? 'historis' : 'asumsi awal 5% (belum ada riwayat)',
            'cac' => $cac['status'] === 'ok' ? round((float) $cac['value'], 2) : null,
            'fee_rate' => $gross > 0 ? round(($this->ledger->balances($from, $to)['5200']['balance'] ?? 0) / $gross, 4) : 0.0,
            'avg_expense' => $avg,
            'basis' => $from->toDateString().' s/d '.$to->toDateString(),
        ];
    }

    /**
     * @param  array<string,mixed>  $overrides  customers, arpa, new_customers_per_month, churn_rate, cac, fee_rate, fixed_costs[code]
     * @return array{scenario:string,assumptions:array,months:array<int,array>,lines:array<string,array<string,float>>,totals:array}
     */
    public function project(string $scenario, string $periodFrom, int $months, array $overrides = []): array
    {
        if (! isset(self::FACTORS[$scenario])) {
            throw new InvalidArgumentException('Skenario harus conservative, base, atau growth.');
        }
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodFrom) || $months < 1 || $months > 24) {
            throw new InvalidArgumentException('Periode YYYY-MM dan jumlah bulan 1-24.');
        }
        $f = self::FACTORS[$scenario];
        $b = $this->baseline();
        $num = fn (string $k) => isset($overrides[$k]) && is_numeric($overrides[$k]) ? (float) $overrides[$k] : null;
        foreach (['arpa', 'cac', 'fee_rate'] as $k) {
            if (($v = $num($k)) !== null) {
                if ($v < 0) {
                    throw new InvalidArgumentException("{$k} tidak boleh negatif.");
                }
                $b[$k] = $v;
            }
        }
        $customers = $num('customers') ?? (float) $b['customers'];
        $new = ($num('new_customers_per_month') ?? $b['new_customers_per_month']) * $f['new'];
        $churn = min(1.0, max(0.0, ($num('churn_rate') ?? $b['churn_rate']) * $f['churn']));
        $avg = $b['avg_expense'];
        foreach ((array) ($overrides['fixed_costs'] ?? []) as $code => $v) {
            if (is_numeric($v) && $v >= 0 && Account::where('code', (string) $code)->where('type', 'expense')->exists()) {
                $avg[(string) $code] = (float) $v;
            }
        }

        $accounts = Account::where('type', 'expense')->get()->keyBy('code');
        $baseCustomers = max(1, (int) $b['customers']);
        $marketingCodes = $accounts->where('report_group', 'marketing')->keys()->all();
        $mktAvgTotal = array_sum(array_intersect_key($avg, array_flip($marketingCodes)));

        $rows = [];
        $lines = [];
        $period = Carbon::parse($periodFrom.'-01');
        for ($i = 0; $i < $months; $i++, $period->addMonth()) {
            $p = $period->format('Y-m');
            $customers = max(0, $customers * (1 - $churn) + $new);
            $revenue = round($customers * $b['arpa'], 2);
            $l = ['4000' => $revenue];
            foreach ($avg as $code => $amount) {
                $acc = $accounts[$code] ?? null;
                if (! $acc || $amount <= 0) {
                    continue;
                }
                if ($code === '5200') {
                    continue;
                }
                if ($acc->report_group === 'marketing') {
                    $l[$code] = $b['cac'] !== null && $mktAvgTotal > 0
                        ? round($new * $b['cac'] * ($amount / $mktAvgTotal) * $f['marketing'], 2)
                        : round($amount * $f['marketing'], 2);
                } elseif ($acc->behavior === 'variable') {
                    $l[$code] = round($amount / $baseCustomers * $customers * $f['cost'], 2);
                } else {
                    $l[$code] = round($amount * $f['cost'], 2);
                }
            }
            if ($b['fee_rate'] > 0) {
                $l['5200'] = round($revenue * $b['fee_rate'], 2);
            }
            $expenses = round(array_sum($l) - $revenue, 2);
            $lines[$p] = $l;
            $rows[] = ['period' => $p, 'customers' => round($customers, 1), 'revenue' => $revenue, 'expenses' => $expenses, 'net' => round($revenue - $expenses, 2)];
        }

        return [
            'scenario' => $scenario,
            'assumptions' => ['customers_start' => $b['customers'], 'arpa' => $b['arpa'], 'new_customers_per_month' => round($new, 2), 'churn_rate' => round($churn, 4),
                'churn_source' => $b['churn_source'], 'cac' => $b['cac'], 'fee_rate' => $b['fee_rate'], 'factors' => $f, 'basis' => $b['basis']],
            'months' => $rows,
            'lines' => $lines,
            'totals' => ['revenue' => round(array_sum(array_column($rows, 'revenue')), 2), 'expenses' => round(array_sum(array_column($rows, 'expenses')), 2), 'net' => round(array_sum(array_column($rows, 'net')), 2)],
        ];
    }

    /** Ketiga skenario berdampingan, tanpa menyimpan apa pun. */
    public function compare(string $periodFrom, int $months, array $overrides = []): array
    {
        $out = [];
        foreach (array_keys(self::FACTORS) as $s) {
            $p = $this->project($s, $periodFrom, $months, $overrides);
            $out[$s] = ['totals' => $p['totals'], 'assumptions' => $p['assumptions'], 'last_month' => end($p['months'])];
        }

        return $out;
    }

    public function draft(string $name, string $scenario, string $periodFrom, int $months, array $overrides, string $actorType, int $actorId, ?string $notes = null): BudgetVersion
    {
        $p = $this->project($scenario, $periodFrom, $months, $overrides);

        return DB::transaction(function () use ($p, $name, $scenario, $periodFrom, $months, $actorType, $actorId, $notes) {
            $v = BudgetVersion::create([
                'name' => mb_substr(trim($name) ?: (BudgetVersion::SCENARIOS[$scenario].' '.$periodFrom), 0, 120), 'scenario' => $scenario,
                'period_from' => $periodFrom, 'months' => $months, 'status' => 'draft', 'assumptions' => $p['assumptions'],
                'notes' => $notes ? mb_substr($notes, 0, 500) : null, 'created_by_type' => $actorType, 'created_by_id' => $actorId,
            ]);
            foreach ($p['lines'] as $period => $codes) {
                foreach ($codes as $code => $amount) {
                    BudgetLine::create(['platform_fin_budget_version_id' => $v->id, 'period' => $period, 'account_code' => $code, 'amount' => $amount]);
                }
            }
            AuditEvent::log($actorType, $actorId, 'budget.drafted', $v, null, ['scenario' => $scenario, 'from' => $periodFrom, 'months' => $months, 'totals' => $p['totals']]);

            return $v;
        });
    }

    /** Setujui versi: barisnya menjadi anggaran aktif (dipakai Anggaran vs Aktual). Versi aktif sebelumnya diarsipkan. */
    public function approve(BudgetVersion $version, PlatformAdmin $by): BudgetVersion
    {
        if (! $by->is_active || ! $by->canWriteFinance()) {
            throw new InvalidArgumentException('Hanya admin keuangan aktif yang bisa menyetujui anggaran.');
        }

        return DB::transaction(function () use ($version, $by) {
            $v = BudgetVersion::lockForUpdate()->findOrFail($version->id);
            if ($v->status !== 'draft') {
                throw new InvalidArgumentException("Versi berstatus {$v->status} tidak bisa disetujui.");
            }
            $periods = $v->lines()->distinct()->pluck('period');
            BudgetVersion::where('status', 'approved')->whereKeyNot($v->id)
                ->whereHas('lines', fn ($q) => $q->whereIn('period', $periods))->update(['status' => 'archived']);
            $accounts = Account::pluck('id', 'code');
            foreach ($v->lines as $line) {
                Budget::updateOrCreate(['period' => $line->period, 'platform_fin_account_id' => $accounts[$line->account_code]], ['amount' => $line->amount]);
            }
            $v->update(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()]);
            AuditEvent::log('admin', $by->id, 'budget.approved', $v, ['status' => 'draft'], ['status' => 'approved', 'periods' => $periods->all()]);

            return $v;
        });
    }

    public function reject(BudgetVersion $version, PlatformAdmin $by, string $reason): BudgetVersion
    {
        if (! $by->is_active || ! $by->canWriteFinance()) {
            throw new InvalidArgumentException('Hanya admin keuangan aktif.');
        }
        if ($version->status !== 'draft') {
            throw new InvalidArgumentException('Hanya draf yang bisa ditolak.');
        }
        $version->update(['status' => 'rejected', 'notes' => mb_substr(trim(($version->notes ? $version->notes.' | ' : '').'Ditolak: '.$reason), 0, 500)]);
        AuditEvent::log('admin', $by->id, 'budget.rejected', $version, ['status' => 'draft'], ['status' => 'rejected'], $reason);

        return $version;
    }

    /**
     * Rolling forecast 12 bulan: bulan yang sudah lewat = aktual buku besar,
     * bulan berjalan dan sesudahnya = anggaran aktif bila ada, selain itu proyeksi skenario dasar.
     */
    public function rollingForecast(int $pastMonths = 3, int $futureMonths = 9): array
    {
        $start = today()->startOfMonth()->subMonths($pastMonths);
        $current = today()->startOfMonth();
        $projection = $this->project('base', $current->format('Y-m'), max(1, $futureMonths))['lines'];
        $rows = [];
        for ($d = $start->copy(); $d->lt($current->copy()->addMonths($futureMonths)); $d->addMonth()) {
            $p = $d->format('Y-m');
            if ($d->lt($current)) {
                $is = $this->ledger->incomeStatement($d->copy(), $d->copy()->endOfMonth());
                $rows[] = ['period' => $p, 'type' => 'aktual', 'revenue' => $is['net_revenue'], 'expenses' => $is['total_expenses'], 'net' => $is['net_income']];

                continue;
            }
            $budget = Budget::with('account')->where('period', $p)->get();
            if ($budget->isNotEmpty()) {
                $rev = (float) $budget->filter(fn ($b) => $b->account->type === 'revenue')->sum('amount');
                $exp = (float) $budget->filter(fn ($b) => $b->account->type === 'expense')->sum('amount');
                $rows[] = ['period' => $p, 'type' => 'anggaran', 'revenue' => round($rev, 2), 'expenses' => round($exp, 2), 'net' => round($rev - $exp, 2)];
            } else {
                $l = $projection[$p] ?? ['4000' => 0];
                $rev = (float) ($l['4000'] ?? 0);
                $exp = round(array_sum($l) - $rev, 2);
                $rows[] = ['period' => $p, 'type' => 'proyeksi', 'revenue' => $rev, 'expenses' => $exp, 'net' => round($rev - $exp, 2)];
            }
        }

        return ['rows' => $rows, 'note' => 'Aktual dari buku besar; anggaran dari versi yang disetujui; proyeksi dari skenario dasar dengan asumsi historis.'];
    }
}
