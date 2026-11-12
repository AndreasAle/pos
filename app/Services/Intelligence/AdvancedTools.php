<?php

namespace App\Services\Intelligence;

use App\Models\Order;
use App\Models\User;
use App\Services\Finance\FinanceReports;
use App\Services\Reporting\SalesReportFilter;
use App\Services\YokaToolRegistry;
use Carbon\CarbonImmutable;

/**
 * Higher-level business analysis for the Yoka agent, built on the same tenant-scoped services
 * the Laporan screens use: finance (P&L, hutang/piutang), menu engineering, forecast and
 * customer retention. Every query is limited to the user's business and permitted outlets.
 */
class AdvancedTools
{
    public function __construct(private YokaToolRegistry $tools, private PeriodResolver $periods, private FinanceReports $finance) {}

    public function names(): array
    {
        return ['profit_and_loss', 'cash_position', 'menu_engineering', 'sales_forecast', 'customer_retention'];
    }

    public function definitions(): array
    {
        $periods = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'last_30_days'];
        $def = fn (string $name, string $desc, array $props = [], array $req = []) => ['type' => 'function', 'function' => ['name' => $name, 'description' => $desc, 'strict' => true, 'parameters' => ['type' => 'object', 'additionalProperties' => false, 'properties' => $props ?: new stdClass, 'required' => $req ?: array_keys($props)]]];
        $period = ['period' => ['type' => 'string', 'enum' => $periods]];

        return [
            $def('profit_and_loss', 'Laporan laba rugi nyata: penjualan, HPP, laba kotor, biaya operasional per kategori, laba bersih. Pakai untuk pertanyaan laba bersih, biaya, efisiensi, "untung berapa".', $period),
            $def('cash_position', 'Posisi hutang ke supplier dan piutang pelanggan beserta umur jatuh tempo (aging) dan yang menunggak.'),
            $def('menu_engineering', 'Matriks menu: bintang (laris + margin tinggi), kuda beban (laris tapi margin rendah), teka-teki (margin tinggi tapi kurang laku), anjing (lemah). Dasar rekomendasi harga, bundling, dan menu yang dihapus.', $period),
            $def('sales_forecast', 'Proyeksi penjualan akhir bulan dari laju harian, pola per hari dalam minggu, dan selisih terhadap bulan lalu.'),
            $def('customer_retention', 'Pelanggan baru vs kembali, tingkat repeat order, dan pelanggan yang lama tidak datang (calon win-back).', $period),
        ];
    }

    public function run(User $user, string $tool, array $args): array
    {
        return match ($tool) {
            'profit_and_loss' => $this->profitLoss($user, $args),
            'cash_position' => $this->cashPosition($user),
            'menu_engineering' => $this->menuEngineering($user, $args),
            'sales_forecast' => $this->forecast($user),
            'customer_retention' => $this->retention($user, $args),
        };
    }

    private function envelope(string $schema, User $user, array $period, array $data, array $warnings = []): array
    {
        return ['schema' => $schema, 'period' => ['label' => $period['label'] ?? '-'], 'scope' => ['outlet_ids' => SalesReportFilter::allowedOutlets($user)], 'evidence_refs' => [], 'currency' => 'IDR',
            'generated_at' => now()->toIso8601String(), 'timezone' => $user->business->timezone ?: config('app.timezone'), 'completeness' => ['status' => 'partial'], 'schema_version' => '2.0', 'warnings' => $warnings, 'data' => $data];
    }

    private function profitLoss(User $user, array $a): array
    {
        $p = $this->periods->resolve($user->business, ['period' => $a['period'] ?? 'this_month']);
        $f = SalesReportFilter::forRange($user, $p['from'], $p['to'], SalesReportFilter::allowedOutlets($user));
        $pl = $this->finance->profitLoss($f);

        return $this->envelope('profit_and_loss', $user, $p, $pl, ['Biaya hanya yang sudah dicatat di menu Biaya; bila kosong, laba bersih belum akurat.']);
    }

    private function cashPosition(User $user): array
    {
        $payables = $this->finance->payablesAging($user->business_id);
        $receivables = $this->finance->receivablesAging($user->business_id);
        $trim = fn (array $x) => ['total' => $x['total'], 'overdue' => $x['overdue'], 'buckets' => $x['buckets'], 'top_parties' => array_slice($x['parties'], 0, 5, true), 'as_of' => $x['as_of']];

        return $this->envelope('cash_position', $user, ['label' => 'Per hari ini'], ['payables' => $trim($payables), 'receivables' => $trim($receivables)]);
    }

    private function menuEngineering(User $user, array $a): array
    {
        $r = $this->tools->execute($user, 'product_performance', ['period' => $a['period'] ?? 'last_30_days', 'limit' => 20]);
        $items = array_values(array_filter($r['data']['products'], fn ($x) => $x['quantity'] > 0));
        if (count($items) < 3) {
            return $this->envelope('menu_engineering', $user, $r['period'], ['classes' => [], 'note' => 'Data produk terlalu sedikit untuk matriks menu.']);
        }
        $avgQty = array_sum(array_column($items, 'quantity')) / count($items);
        $unitMargin = fn ($x) => $x['quantity'] > 0 ? $x['estimated_gross_profit'] / $x['quantity'] : 0;
        $avgMargin = array_sum(array_map($unitMargin, $items)) / count($items);
        $classes = ['bintang' => [], 'kuda_beban' => [], 'teka_teki' => [], 'anjing' => []];
        foreach ($items as $x) {
            $popular = $x['quantity'] >= $avgQty;
            $profitable = $unitMargin($x) >= $avgMargin;
            $key = $popular ? ($profitable ? 'bintang' : 'kuda_beban') : ($profitable ? 'teka_teki' : 'anjing');
            $classes[$key][] = ['name' => $x['name'], 'quantity' => $x['quantity'], 'net_sales' => $x['net_sales'], 'margin_per_unit' => round($unitMargin($x)), 'gross_profit' => $x['estimated_gross_profit']];
        }

        return $this->envelope('menu_engineering', $user, $r['period'], ['avg_quantity' => round($avgQty, 1), 'avg_margin_per_unit' => round($avgMargin), 'classes' => $classes], ['Margin memakai snapshot HPP; produk tanpa HPP terbaca margin tinggi.']);
    }

    private function forecast(User $user): array
    {
        $tz = $user->business->timezone ?: config('app.timezone');
        $now = CarbonImmutable::now($tz);
        $month = $this->tools->execute($user, 'sales_series', ['period' => 'this_month']);
        $recent = $this->tools->execute($user, 'sales_series', ['period' => 'last_30_days']);
        $last = $this->tools->execute($user, 'sales_summary', ['period' => 'last_month']);
        $points = $month['data']['points'];
        $soFar = array_sum(array_column($points, 'value'));
        $daysElapsed = max(1, $now->day);
        $daysInMonth = $now->daysInMonth;
        $weekday = [];
        foreach ($recent['data']['points'] as $pt) {
            $d = CarbonImmutable::parse($pt['date'])->locale('id')->isoFormat('dddd');
            $weekday[$d]['sum'] = ($weekday[$d]['sum'] ?? 0) + $pt['value'];
            $weekday[$d]['n'] = ($weekday[$d]['n'] ?? 0) + 1;
        }
        $pattern = array_map(fn ($w) => round($w['sum'] / $w['n']), $weekday);
        arsort($pattern);
        $runRate = $soFar / $daysElapsed * $daysInMonth;

        return $this->envelope('sales_forecast', $user, $month['period'], [
            'net_sales_so_far' => round($soFar), 'days_elapsed' => $daysElapsed, 'days_in_month' => $daysInMonth,
            'avg_per_day' => round($soFar / $daysElapsed), 'projected_month_end' => round($runRate),
            'last_month_net_sales' => $last['data']['net_sales'], 'projection_vs_last_month_pct' => $last['data']['net_sales'] > 0 ? round(($runRate / $last['data']['net_sales'] - 1) * 100, 1) : null,
            'avg_sales_by_weekday_last_30_days' => $pattern,
        ], ['Proyeksi linear dari rata-rata harian; belum memperhitungkan musiman atau promo.']);
    }

    private function retention(User $user, array $a): array
    {
        $p = $this->periods->resolve($user->business, ['period' => $a['period'] ?? 'last_30_days']);
        $outlets = SalesReportFilter::allowedOutlets($user);
        $base = fn () => Order::query()->where('orders.business_id', $user->business_id)->whereIn('orders.outlet_id', $outlets)->where('orders.status', 'paid')->whereNotNull('orders.customer_id');
        $inPeriod = $base()->where('orders.created_at', '>=', $p['from'])->where('orders.created_at', '<', $p['to'])
            ->selectRaw('orders.customer_id, COUNT(*) visits, SUM(orders.subtotal-orders.discount_amount) spending')->groupBy('orders.customer_id')->get();
        $ids = $inPeriod->pluck('customer_id');
        $returning = $ids->isEmpty() ? 0 : $base()->where('orders.created_at', '<', $p['from'])->whereIn('orders.customer_id', $ids)->distinct()->count('orders.customer_id');
        $lapsed = $base()->selectRaw('orders.customer_id, MAX(orders.created_at) last_visit, COUNT(*) visits')->groupBy('orders.customer_id')
            ->havingRaw('MAX(orders.created_at) < ? AND MAX(orders.created_at) >= ?', [now()->subDays(30), now()->subDays(120)])->havingRaw('COUNT(*) >= 2')->orderByDesc('visits')->limit(15)->get();
        $total = $inPeriod->count();
        $repeatInPeriod = $inPeriod->where('visits', '>=', 2)->count();

        return $this->envelope('customer_retention', $user, $p, [
            'customers_in_period' => $total, 'new_customers' => max(0, $total - $returning), 'returning_customers' => $returning,
            'repeat_within_period' => $repeatInPeriod, 'repeat_rate_pct' => $total ? round($repeatInPeriod / $total * 100, 1) : null,
            'lapsed_customers_count' => $lapsed->count(), 'lapsed_sample_visits' => $lapsed->pluck('visits')->take(5)->all(),
            'note_lapsed' => 'Pelanggan langganan (>=2 kunjungan) yang terakhir datang 30-120 hari lalu; nama sengaja tidak dikirim ke AI.',
        ], ['Hanya order yang terhubung ke profil pelanggan yang terhitung.']);
    }
}
