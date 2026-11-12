<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessInsight;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InsightService
{
    public function refresh(Business $business): int
    {
        $insights = array_merge(
            $this->salesTrend($business), $this->averageOrder($business),
            $this->stockForecast($business), $this->lowMargins($business),
            $this->reversals($business), $this->peakHour($business),
            $this->outletPerformance($business),
        );

        foreach ($insights as $insight) {
            BusinessInsight::updateOrCreate(
                ['business_id' => $business->id, 'fingerprint' => $insight['fingerprint']],
                $insight + ['business_id' => $business->id, 'detected_at' => now(), 'expires_at' => now()->addHours(26)]
            );
        }

        return count($insights);
    }

    public function visible(Business $business, int $limit = 6)
    {
        $this->refresh($business);

        return $business->insights()->visible()
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 WHEN 'positive' THEN 3 ELSE 4 END")
            ->latest('detected_at')->limit($limit)->get();
    }

    private function salesTrend(Business $b): array
    {
        $today = $this->revenue($b, now()->startOfDay(), now());
        $previous = $this->revenue($b, now()->subDay()->startOfDay(), now()->subDay());
        if ($previous['revenue'] < 1 || $today['orders'] < 1) return [];
        $delta = round(($today['revenue'] - $previous['revenue']) / $previous['revenue'] * 100);
        if (abs($delta) < 10) return [];
        $down = $delta < 0;

        return [[
            'fingerprint' => 'sales-trend:'.today(), 'type' => 'sales_trend',
            'severity' => $down ? 'warning' : 'positive',
            'title' => $down ? 'Omzet hari ini menurun' : 'Omzet hari ini bertumbuh',
            'summary' => 'Omzet '.($down ? 'turun ' : 'naik ').abs($delta).'% dibanding waktu yang sama kemarin.',
            'data' => ['current' => $today['revenue'], 'previous' => $previous['revenue'], 'change_percent' => $delta],
            'action_label' => 'Buka laporan', 'action_route' => 'reports.sales',
        ]];
    }

    private function averageOrder(Business $b): array
    {
        $current = $this->revenue($b, now()->subDays(6)->startOfDay(), now());
        $previous = $this->revenue($b, now()->subDays(13)->startOfDay(), now()->subDays(7)->endOfDay());
        if (!$current['orders'] || !$previous['orders']) return [];
        $a = $current['revenue'] / $current['orders']; $p = $previous['revenue'] / $previous['orders'];
        $delta = round(($a - $p) / max($p, 1) * 100);
        if ($delta > -12) return [];

        return [[
            'fingerprint' => 'aov:'.now()->format('o-W'), 'type' => 'average_order', 'severity' => 'warning',
            'title' => 'Nilai rata-rata transaksi turun',
            'summary' => 'Rata-rata belanja 7 hari terakhir turun '.abs($delta).'% menjadi '.$this->rp($a).'.',
            'data' => ['average' => round($a), 'change_percent' => $delta],
            'action_label' => 'Lihat produk', 'action_route' => 'reports.products',
        ]];
    }

    private function stockForecast(Business $b): array
    {
        $usage = StockMovement::where('business_id', $b->id)->whereIn('type', ['sale', 'out', 'waste'])
            ->where('created_at', '>=', now()->subDays(14))->select('ingredient_id', DB::raw('SUM(ABS(qty)) total'))
            ->groupBy('ingredient_id')->pluck('total', 'ingredient_id');
        $rows = [];
        foreach (Ingredient::forBusiness($b->id)->where('is_active', true)->get() as $ingredient) {
            $daily = (float) ($usage[$ingredient->id] ?? 0) / 14;
            $days = $daily > 0 ? (float) $ingredient->current_stock / $daily : null;
            if (!$ingredient->isLowStock() && ($days === null || $days > 3)) continue;
            $label = $days === null ? 'sudah mencapai batas minimum' : 'diperkirakan habis dalam '.max(1, (int) ceil($days)).' hari';
            $rows[] = [
                'fingerprint' => 'stock:'.$ingredient->id.':'.today(), 'type' => 'stock_forecast',
                'severity' => $ingredient->isLowStock() ? 'critical' : 'warning', 'title' => 'Stok '.$ingredient->name.' perlu perhatian',
                'summary' => ucfirst($ingredient->name).' '.$label.'. Sisa '.(float) $ingredient->current_stock.' '.$ingredient->unit.'.',
                'data' => ['ingredient_id' => $ingredient->id, 'days_left' => $days, 'daily_usage' => round($daily, 3)],
                'action_label' => 'Kelola stok', 'action_route' => 'ingredients.index',
            ];
        }
        return array_slice($rows, 0, 3);
    }

    private function lowMargins(Business $b): array
    {
        $row = OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $b->id)->where('orders.status', 'paid')->where('orders.created_at', '>=', now()->subDays(30))
            ->where('order_items.price', '>', 0)->select('order_items.product_name')
            ->selectRaw('SUM((order_items.price-order_items.cost_price)*order_items.qty)/SUM(order_items.price*order_items.qty)*100 margin')
            ->selectRaw('SUM(order_items.qty) qty')->groupBy('order_items.product_name')->having('qty', '>=', 3)
            ->orderBy('margin')->first();
        if (!$row || $row->margin >= 20) return [];
        return [[
            'fingerprint' => 'margin:'.md5($row->product_name).':'.now()->format('Y-m'), 'type' => 'low_margin', 'severity' => 'warning',
            'title' => 'Margin '.$row->product_name.' rendah',
            'summary' => 'Margin estimasi hanya '.round($row->margin, 1).'%. Periksa harga jual, resep, atau biaya bahan.',
            'data' => ['product' => $row->product_name, 'margin' => round($row->margin, 1)],
            'action_label' => 'Lihat laba', 'action_route' => 'reports.profit',
        ]];
    }

    private function reversals(Business $b): array
    {
        $all = Order::forBusiness($b->id)->where('created_at', '>=', now()->subDays(7))->count();
        $bad = Order::forBusiness($b->id)->where('created_at', '>=', now()->subDays(7))->whereIn('status', ['cancelled', 'refunded'])->count();
        if ($all < 10 || $bad / $all < .08) return [];
        $rate = round($bad / $all * 100, 1);
        return [[
            'fingerprint' => 'reversal:'.now()->format('o-W'), 'type' => 'reversal_rate', 'severity' => 'critical',
            'title' => 'Void dan refund lebih tinggi dari normal', 'summary' => "{$bad} dari {$all} order ({$rate}%) dibatalkan atau di-refund dalam 7 hari terakhir.",
            'data' => ['count' => $bad, 'rate' => $rate], 'action_label' => 'Periksa order', 'action_route' => 'orders.index',
        ]];
    }

    private function peakHour(Business $b): array
    {
        $row = Order::forBusiness($b->id)->paid()->where('created_at', '>=', now()->subDays(30))
            ->selectRaw($this->hourExpression('created_at').' hour, COUNT(*) total')->groupBy('hour')->orderByDesc('total')->first();
        if (!$row || $row->total < 5) return [];
        $end = ((int) $row->hour + 1) % 24;
        return [[
            'fingerprint' => 'peak-hour:'.now()->format('Y-m'), 'type' => 'peak_hour', 'severity' => 'info',
            'title' => 'Jam tersibuk teridentifikasi',
            'summary' => sprintf('Pukul %02d.00–%02d.00 adalah jam paling ramai berdasarkan 30 hari terakhir (%d transaksi).', $row->hour, $end, $row->total),
            'data' => ['hour' => (int) $row->hour, 'orders' => (int) $row->total],
            'action_label' => 'Lihat laporan', 'action_route' => 'reports.sales',
        ]];
    }

    private function outletPerformance(Business $b): array
    {
        if ($b->outlets()->where('is_active', true)->count() < 2) return [];
        $rows = Order::forBusiness($b->id)->paid()->where('orders.created_at', '>=', now()->subDays(7))
            ->join('outlets', 'outlets.id', '=', 'orders.outlet_id')->select('outlets.id', 'outlets.name')
            ->selectRaw('SUM(orders.grand_total) revenue')->groupBy('outlets.id', 'outlets.name')->orderBy('revenue')->get();
        if ($rows->count() < 2 || $rows->last()->revenue <= 0) return [];
        $low = $rows->first(); $gap = round((1 - $low->revenue / $rows->last()->revenue) * 100);
        if ($gap < 30) return [];
        return [[
            'fingerprint' => 'outlet-gap:'.$low->id.':'.now()->format('o-W'), 'type' => 'outlet_performance', 'severity' => 'warning',
            'title' => 'Performa '.$low->name.' tertinggal', 'summary' => 'Omzet 7 hari terakhir '.$gap.'% di bawah outlet teratas.',
            'outlet_id' => $low->id, 'data' => ['gap_percent' => $gap],
            'action_label' => 'Bandingkan outlet', 'action_route' => 'dashboard',
        ]];
    }

    private function revenue(Business $b, $from, $to): array
    {
        $q = Order::forBusiness($b->id)->paid()->whereBetween('created_at', [$from, $to]);
        return ['revenue' => (float) (clone $q)->sum('grand_total'), 'orders' => (int) $q->count()];
    }

    private function rp(float $value): string { return 'Rp '.number_format($value, 0, ',', '.'); }

    private function hourExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', {$column}) AS INTEGER)"
            : "HOUR({$column})";
    }
}
