<?php

namespace App\Services\PlatformFinance;

use App\Models\BusinessSubscription;
use App\Models\PlatformFinance\Account;
use App\Models\PlatformFinance\Budget;
use Illuminate\Support\Carbon;

/**
 * Perhitungan keuangan deterministik untuk Luna dan Nanda. Model AI TIDAK menghitung sendiri:
 * ia memanggil fungsi ini dan menjelaskan hasilnya. Setiap hasil membawa rumus, input, dan status;
 * bila datanya kurang, status 'insufficient_data' dengan alasannya, bukan angka karangan.
 *
 * Rumus buku: Managerial Accounting (OpenStax) bab 3 (CVP), bab 7-8 (anggaran, varians), bab 11 (payback),
 * Financial Accounting bab 5.3 (current ratio, modal kerja). Metrik SaaS (MRR, CAC, LTV, churn, runway)
 * adalah lapisan analitik tambahan Yoka, BUKAN isi buku; definisinya ada di docs/finance-intelligence/metric-dictionary.md.
 */
class Metrics
{
    public function __construct(private Ledger $ledger) {}

    private static function ok(float $value, string $formula, array $inputs, string $unit = 'rp'): array
    {
        return ['status' => 'ok', 'value' => round($value, 4), 'unit' => $unit, 'formula' => $formula, 'inputs' => $inputs];
    }

    private static function missing(string $formula, string $reason, array $inputs = []): array
    {
        return ['status' => 'insufficient_data', 'value' => null, 'formula' => $formula, 'reason' => $reason, 'inputs' => $inputs];
    }

    // ── Managerial accounting (buku) ────────────────────────────────────────────

    public static function contributionMargin(float $price, float $variableCost): array
    {
        $cm = $price - $variableCost;

        return self::ok($cm, 'margin kontribusi per unit = harga - biaya variabel per unit', compact('price', 'variableCost')) + [
            'ratio' => $price > 0 ? round($cm / $price, 4) : null,
        ];
    }

    public static function breakEven(float $fixedCosts, float $price, float $variableCost): array
    {
        $formula = 'titik impas (unit) = biaya tetap / margin kontribusi per unit; titik impas (Rp) = biaya tetap / rasio margin kontribusi';
        $cm = $price - $variableCost;
        if ($cm <= 0) {
            return self::missing($formula, 'Margin kontribusi nol atau negatif: setiap penjualan menambah rugi, titik impas tidak tercapai.', compact('fixedCosts', 'price', 'variableCost'));
        }
        $units = $fixedCosts / $cm;

        return self::ok($units, $formula, compact('fixedCosts', 'price', 'variableCost'), 'unit') + [
            'units_rounded_up' => (int) ceil($units - 1e-9),
            'revenue' => round($fixedCosts / ($cm / $price), 2),
        ];
    }

    public static function targetProfitUnits(float $fixedCosts, float $targetProfit, float $price, float $variableCost): array
    {
        $formula = 'unit untuk target laba = (biaya tetap + target laba) / margin kontribusi per unit';
        $cm = $price - $variableCost;

        return $cm <= 0
            ? self::missing($formula, 'Margin kontribusi nol atau negatif.')
            : self::ok(($fixedCosts + $targetProfit) / $cm, $formula, compact('fixedCosts', 'targetProfit', 'price', 'variableCost'), 'unit');
    }

    public static function marginOfSafety(float $actualRevenue, float $breakEvenRevenue): array
    {
        $formula = 'margin of safety = penjualan aktual - penjualan impas; persen = margin of safety / penjualan aktual';
        if ($actualRevenue <= 0) {
            return self::missing($formula, 'Belum ada penjualan aktual.');
        }
        $mos = $actualRevenue - $breakEvenRevenue;

        return self::ok($mos, $formula, compact('actualRevenue', 'breakEvenRevenue')) + ['percent' => round($mos / $actualRevenue * 100, 2)];
    }

    public static function operatingLeverage(float $contributionMargin, float $netOperatingIncome): array
    {
        $formula = 'degree of operating leverage = total margin kontribusi / laba operasi';

        return $netOperatingIncome == 0.0
            ? self::missing($formula, 'Laba operasi nol: DOL tidak terdefinisi.')
            : self::ok($contributionMargin / $netOperatingIncome, $formula, compact('contributionMargin', 'netOperatingIncome'), 'x');
    }

    /** Varians anggaran. Untuk beban, aktual di bawah anggaran = menguntungkan (favorable). */
    public static function variance(float $budget, float $actual, bool $isExpense = true): array
    {
        $diff = $actual - $budget;
        $favorable = $isExpense ? $diff <= 0 : $diff >= 0;

        return self::ok($diff, 'varians = aktual - anggaran', compact('budget', 'actual', 'isExpense')) + [
            'percent' => $budget != 0.0 ? round($diff / $budget * 100, 2) : null,
            'favorable' => $favorable,
            'label' => $diff == 0.0 ? 'sesuai anggaran' : ($favorable ? 'menguntungkan (F)' : 'tidak menguntungkan (U)'),
        ];
    }

    public static function paybackPeriod(float $investment, float $annualCashInflow): array
    {
        $formula = 'payback period = investasi awal / arus kas masuk bersih per tahun (arus kas merata)';

        return $annualCashInflow <= 0
            ? self::missing($formula, 'Arus kas masuk nol atau negatif: investasi tidak kembali.')
            : self::ok($investment / $annualCashInflow, $formula, compact('investment', 'annualCashInflow'), 'tahun');
    }

    public static function presentValue(float $futureValue, float $rate, int $periods): array
    {
        return self::ok($futureValue / ((1 + $rate) ** $periods), 'PV = FV / (1 + i)^n', compact('futureValue', 'rate', 'periods'));
    }

    public static function netPresentValue(float $investment, array $cashFlows, float $rate): array
    {
        $pv = 0.0;
        foreach (array_values($cashFlows) as $t => $cf) {
            $pv += $cf / ((1 + $rate) ** ($t + 1));
        }

        return self::ok($pv - $investment, 'NPV = Σ arus kas_t / (1 + i)^t - investasi awal', compact('investment', 'cashFlows', 'rate')) + ['accept' => $pv - $investment >= 0];
    }

    // ── Rasio dari buku besar ───────────────────────────────────────────────────

    public function liquidity(?Carbon $asOf = null): array
    {
        $b = $this->ledger->balances(null, $asOf);
        $currentAssets = array_sum(array_map(fn ($x) => $x['balance'], array_filter($b, fn ($x) => $x['type'] === 'asset' && $x['subtype'] !== 'fixed')));
        $currentLiabilities = array_sum(array_map(fn ($x) => $x['balance'], array_filter($b, fn ($x) => $x['type'] === 'liability')));
        $formula = 'current ratio = aset lancar / kewajiban lancar; modal kerja = aset lancar - kewajiban lancar';
        $base = ['working_capital' => round($currentAssets - $currentLiabilities, 2), 'current_assets' => $currentAssets, 'current_liabilities' => $currentLiabilities];

        return $currentLiabilities <= 0
            ? self::missing($formula, 'Tidak ada kewajiban lancar tercatat, current ratio tidak bermakna.') + $base
            : self::ok($currentAssets / $currentLiabilities, $formula, [], 'x') + $base;
    }

    public function budgetVsActual(string $period): array
    {
        $from = Carbon::parse($period.'-01');
        $actual = $this->ledger->balances($from, $from->copy()->endOfMonth());
        $rows = [];
        foreach (Budget::with('account')->where('period', $period)->get() as $budget) {
            $a = $actual[$budget->account->code]['balance'] ?? 0.0;
            $rows[] = ['code' => $budget->account->code, 'name' => $budget->account->name]
                + self::variance((float) $budget->amount, (float) $a, $budget->account->type === 'expense');
        }

        return $rows === []
            ? ['status' => 'insufficient_data', 'reason' => "Belum ada anggaran untuk {$period}.", 'rows' => []]
            : ['status' => 'ok', 'period' => $period, 'rows' => $rows];
    }

    // ── Metrik SaaS (lapisan Yoka, bukan buku) ─────────────────────────────────

    public function mrr(?Carbon $asOf = null): array
    {
        $asOf ??= today();
        $subs = BusinessSubscription::with('plan:id,price')->where('status', 'active')->whereNotNull('paid_at')
            ->whereDate('ends_at', '>=', $asOf->toDateString())->get();
        $mrr = $subs->sum(fn ($s) => self::monthlyValue($s));
        $accounts = $subs->pluck('business_id')->unique()->count();
        $formula = 'MRR = jumlah harga bulanan semua langganan berbayar yang aktif per tanggal';

        if ($accounts === 0) {
            return self::missing($formula, 'Belum ada langganan berbayar aktif.') + ['arr' => null, 'arpa' => null, 'paying_accounts' => 0];
        }

        return self::ok($mrr, $formula, ['as_of' => $asOf->toDateString()]) + [
            'arr' => round($mrr * 12, 2), 'paying_accounts' => $accounts, 'arpa' => round($mrr / $accounts, 2),
        ];
    }

    /** Pelanggan berbayar pertama kali pada bulan itu. */
    public function newPayingCustomers(Carbon $from, Carbon $to): int
    {
        return BusinessSubscription::whereNotNull('paid_at')->where('amount', '>', 0)
            ->selectRaw('business_id, MIN(paid_at) as first_paid')->groupBy('business_id')->get()
            ->filter(fn ($r) => Carbon::parse($r->first_paid)->between($from, $to->copy()->endOfDay()))->count();
    }

    public function cac(Carbon $from, Carbon $to): array
    {
        $formula = 'CAC = total biaya marketing (akun grup marketing) / jumlah pelanggan berbayar baru pada periode yang sama';
        $spend = array_sum(array_map(fn ($x) => $x['balance'], array_filter($this->ledger->balances($from, $to), fn ($x) => $x['report_group'] === 'marketing')));
        $new = $this->newPayingCustomers($from, $to);
        $inputs = ['marketing_spend' => $spend, 'new_paying_customers' => $new, 'from' => $from->toDateString(), 'to' => $to->toDateString()];
        if ($spend <= 0) {
            return self::missing($formula, 'Belum ada biaya marketing yang dicatat di buku besar untuk periode ini.', $inputs);
        }
        if ($new === 0) {
            return self::missing($formula, 'Tidak ada pelanggan berbayar baru: CAC tidak terdefinisi (biaya marketing belum menghasilkan pelanggan).', $inputs);
        }

        return self::ok($spend / $new, $formula, $inputs);
    }

    /** Churn pelanggan bulanan: pelanggan berbayar di awal bulan yang tidak lagi aktif di akhir bulan. */
    public function churn(string $period): array
    {
        $start = Carbon::parse($period.'-01');
        $end = $start->copy()->endOfMonth();
        $activeAt = fn (Carbon $d) => BusinessSubscription::where('status', 'active')->orWhere('status', 'expired')->get()
            ->filter(fn ($s) => $s->paid_at && $s->paid_at->lte($d) && $s->ends_at && $s->ends_at->gte($d->copy()->startOfDay()))
            ->pluck('business_id')->unique();
        $atStart = $activeAt($start);
        $atEnd = $activeAt($end);
        $formula = 'churn pelanggan = pelanggan berbayar di awal periode yang tidak aktif di akhir periode / pelanggan berbayar di awal periode';
        if ($atStart->isEmpty()) {
            return self::missing($formula, "Tidak ada pelanggan berbayar di awal {$period}.");
        }
        $lost = $atStart->diff($atEnd)->count();

        return self::ok($lost / $atStart->count(), $formula, ['start_customers' => $atStart->count(), 'lost' => $lost], 'ratio');
    }

    /** LTV = ARPA x margin kotor % / churn bulanan. Asumsi: churn dan margin stabil. */
    public function ltv(string $period): array
    {
        $formula = 'LTV = ARPA x margin kotor % / churn pelanggan bulanan (asumsi: churn dan margin stabil)';
        $mrr = $this->mrr(Carbon::parse($period.'-01')->endOfMonth());
        $churn = $this->churn($period);
        $is = $this->ledger->incomeStatement(Carbon::parse($period.'-01'), Carbon::parse($period.'-01')->endOfMonth());
        if ($mrr['status'] !== 'ok' || $churn['status'] !== 'ok') {
            return self::missing($formula, 'Butuh MRR/ARPA dan churn yang terhitung.', ['mrr' => $mrr['status'], 'churn' => $churn['status']]);
        }
        if ($is['net_revenue'] <= 0) {
            return self::missing($formula, 'Pendapatan bulan ini belum tercatat di buku besar, margin kotor tidak bisa dihitung.');
        }
        if ($churn['value'] <= 0) {
            return self::missing($formula, 'Churn 0% pada periode ini: LTV tidak bisa dihitung secara wajar (hasilnya tak terhingga). Butuh data beberapa bulan.', ['churn' => 0]);
        }
        $gm = $is['gross_profit'] / $is['net_revenue'];

        return self::ok($mrr['arpa'] * $gm / $churn['value'], $formula, ['arpa' => $mrr['arpa'], 'gross_margin' => round($gm, 4), 'churn' => $churn['value']]);
    }

    /** Burn bersih dan runway dari kas + bank (saldo Midtrans belum cair TIDAK dihitung sebagai kas). */
    public function runway(Carbon $from, Carbon $to): array
    {
        $cf = $this->ledger->cashFlow($from, $to);
        $months = max(1, $from->diffInMonths($to->copy()->addDay()));
        $burn = -($cf['operating'] + $cf['investing']) / $months;
        $cash = $this->ledger->cashBalance($to);
        $formula = 'burn bersih per bulan = -(arus kas operasi + investasi) / jumlah bulan; runway = kas / burn bersih';
        $inputs = ['cash' => $cash, 'monthly_net_burn' => round($burn, 2), 'months' => $months];
        if ($burn <= 0) {
            return self::missing($formula, 'Arus kas operasi positif atau nol pada periode ini: tidak ada burn, runway tidak terbatas oleh burn.', $inputs) + ['monthly_net_burn' => round($burn, 2), 'cash' => $cash];
        }

        return self::ok($cash / $burn, $formula, $inputs, 'bulan') + ['monthly_net_burn' => round($burn, 2), 'cash' => $cash];
    }

    public function cacPayback(Carbon $from, Carbon $to): array
    {
        $formula = 'CAC payback (bulan) = CAC / (ARPA x margin kotor %)';
        $cac = $this->cac($from, $to);
        $mrr = $this->mrr($to);
        $is = $this->ledger->incomeStatement($from, $to);
        if ($cac['status'] !== 'ok' || $mrr['status'] !== 'ok' || $is['net_revenue'] <= 0) {
            return self::missing($formula, 'Butuh CAC, ARPA, dan pendapatan yang terhitung.');
        }
        $gm = $is['gross_profit'] / $is['net_revenue'];
        if ($gm <= 0) {
            return self::missing($formula, 'Margin kotor nol atau negatif: biaya akuisisi tidak akan kembali.');
        }

        return self::ok($cac['value'] / ($mrr['arpa'] * $gm), $formula, ['cac' => $cac['value'], 'arpa' => $mrr['arpa'], 'gross_margin' => round($gm, 4)], 'bulan');
    }

    /** Ringkasan untuk agen: satu sumber angka supaya Nanda dan Luna tidak berbeda. */
    public function snapshot(string $period): array
    {
        $from = Carbon::parse($period.'-01');
        $to = $from->copy()->endOfMonth();

        return [
            'period' => $period,
            'income_statement' => $this->ledger->incomeStatement($from, $to),
            'cash_flow' => $this->ledger->cashFlow($from, $to),
            'cash_and_bank' => $this->ledger->cashBalance($to),
            'midtrans_unsettled' => $this->ledger->balances(null, $to)['1200']['balance'] ?? 0,
            'mrr' => $this->mrr($to->isFuture() ? today() : $to),
            'cac' => $this->cac($from, $to),
            'churn' => $this->churn($period),
            'runway' => $this->runway($from, $to),
            'budget_vs_actual' => $this->budgetVsActual($period),
            'note' => 'Angka berasal dari buku besar Yoka dan data langganan. Estimasi biaya AI dari telemetri BUKAN beban sampai invoice dicatat.',
        ];
    }

    /** Nilai bulanan satu langganan: nominal dibagi jumlah bulan layanan (paket tahunan tidak menggelembungkan MRR). */
    public static function monthlyValue(BusinessSubscription $s): float
    {
        return round((float) ($s->amount ?: $s->plan?->price ?: 0) / RevenueRecognition::termMonths($s), 2);
    }

    /** MRR per toko pada satu tanggal. */
    public function mrrByBusiness(Carbon $at): array
    {
        $out = [];
        foreach (BusinessSubscription::with('plan:id,price')->whereIn('status', ['active', 'expired'])->whereNotNull('paid_at')->get() as $s) {
            if ($s->paid_at->lte($at->copy()->endOfDay()) && $s->ends_at && $s->ends_at->gte($at->copy()->startOfDay()) && (! $s->starts_at || $s->starts_at->lte($at->copy()->endOfDay()))) {
                $out[$s->business_id] = max($out[$s->business_id] ?? 0, self::monthlyValue($s));
            }
        }

        return $out;
    }

    /**
     * Retensi pendapatan (metrik SaaS): dari MRR pelanggan yang ada di awal periode,
     * berapa yang bertambah (expansion), berkurang (contraction), dan hilang (churn) di akhir periode.
     * NRR = (MRR awal + expansion - contraction - churn) / MRR awal; GRR = (MRR awal - contraction - churn) / MRR awal.
     */
    public function revenueRetention(string $period): array
    {
        $start = Carbon::parse($period.'-01');
        $end = $start->copy()->endOfMonth()->min(today());
        $a = $this->mrrByBusiness($start);
        $b = $this->mrrByBusiness($end);
        $formula = 'NRR = (MRR awal + expansion - contraction - churn) / MRR awal; revenue churn = (contraction + churn) / MRR awal';
        $startMrr = array_sum($a);
        if ($startMrr <= 0) {
            return self::missing($formula, "Tidak ada MRR berbayar di awal {$period}.") + ['nrr' => null, 'grr' => null, 'revenue_churn' => null];
        }
        $exp = $con = $churn = 0.0;
        foreach ($a as $biz => $v) {
            $now = $b[$biz] ?? 0.0;
            if ($now <= 0) {
                $churn += $v;
            } elseif ($now > $v) {
                $exp += $now - $v;
            } elseif ($now < $v) {
                $con += $v - $now;
            }
        }
        $new = array_sum(array_diff_key($b, $a));
        $inputs = ['start_mrr' => round($startMrr, 2), 'expansion' => round($exp, 2), 'contraction' => round($con, 2), 'churned_mrr' => round($churn, 2), 'new_mrr' => round($new, 2), 'end_mrr' => round(array_sum($b), 2)];

        return self::ok(($startMrr + $exp - $con - $churn) / $startMrr, $formula, $inputs, 'ratio') + [
            'nrr' => round(($startMrr + $exp - $con - $churn) / $startMrr, 4),
            'grr' => round(($startMrr - $con - $churn) / $startMrr, 4),
            'revenue_churn' => round(($con + $churn) / $startMrr, 4),
        ] + $inputs;
    }

    /** Perbandingan laba rugi: bulan ini vs bulan lalu (MoM) dan year-to-date vs YTD tahun lalu. */
    public function comparison(string $period): array
    {
        $cur = Carbon::parse($period.'-01');
        $curEnd = $cur->copy()->endOfMonth();
        $prev = $cur->copy()->subMonth();
        $ytdFrom = $cur->copy()->startOfYear();
        $lyFrom = $ytdFrom->copy()->subYear();
        $lyTo = $curEnd->copy()->subYear();
        $is = fn (Carbon $f, Carbon $t) => $this->ledger->incomeStatement($f, $t);
        $pick = fn (array $x) => array_intersect_key($x, array_flip(['net_revenue', 'cost_of_revenue', 'gross_profit', 'marketing', 'operating_expenses', 'total_expenses', 'net_income']));
        $diff = function (array $a, array $b) {
            $out = [];
            foreach ($a as $k => $v) {
                $base = $b[$k] ?? 0;
                $out[$k] = ['current' => $v, 'compare' => $base, 'change' => round($v - $base, 2), 'change_pct' => $base != 0 ? round(($v - $base) / abs($base), 4) : null];
            }

            return $out;
        };

        return [
            'period' => $period,
            'mom' => $diff($pick($is($cur, $curEnd)), $pick($is($prev, $prev->copy()->endOfMonth()))),
            'ytd' => $diff($pick($is($ytdFrom, $curEnd)), $pick($is($lyFrom, $lyTo))),
            'labels' => ['mom' => $period.' vs '.$prev->format('Y-m'), 'ytd' => $ytdFrom->format('Y-m').' s/d '.$period.' vs tahun lalu'],
        ];
    }

    public static function marketingAccounts(): array
    {
        return Account::where('report_group', 'marketing')->pluck('code')->all();
    }
}
