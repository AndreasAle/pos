<?php

namespace App\Services\Intelligence;

class ResponseBuilder
{
    public function build(string $tool, array $result, string $visual): array
    {
        $data = $result['data'];
        $blocks = $visual === 'none' ? [] : $this->blocks($tool, $result, $visual);

        return [
            'schema_version' => '1.0',
            'message' => '',
            'evidence' => $result['evidence_refs'],
            'blocks' => $blocks,
            'suggestions' => $this->suggestions($tool, $data),
            'uncertainty' => array_values(array_unique(array_merge($result['warnings'], $result['completeness']['status'] === 'partial' ? ['Sebagian hasil memiliki cakupan data terbatas.'] : []))),
            'as_of' => $result['generated_at'],
            'period' => $result['period'],
            'timezone' => $result['timezone'],
        ];
    }

    private function blocks(string $tool, array $result, string $visual): array
    {
        $data = $result['data'];
        $currency = $result['currency'];
        $title = $this->title($tool, $result['period']['label']);
        $all = match ($tool) {
            'sales_summary' => [[
                'type' => 'kpi', 'title' => $title, 'evidence_ids' => $this->ids($result),
                'metrics' => [
                    ['label' => 'Penjualan bersih', 'value' => $data['net_sales'], 'formatted' => $this->money($data['net_sales'], $currency)],
                    ['label' => 'Transaksi', 'value' => $data['transactions'], 'formatted' => number_format($data['transactions'], 0, ',', '.')],
                    ['label' => 'Rata-rata transaksi', 'value' => $data['average_order'], 'formatted' => $data['average_order'] === null ? 'N/A' : $this->money($data['average_order'], $currency)],
                    ['label' => 'Estimasi laba kotor', 'value' => $data['estimated_gross_profit'], 'formatted' => $this->money($data['estimated_gross_profit'], $currency)],
                ],
            ]],
            'sales_series' => [['type' => 'line_chart', 'title' => $title, 'x_label' => 'Tanggal', 'y_label' => 'Penjualan bersih ('.$currency.')', 'unit' => $currency, 'points' => $data['points'], 'evidence_ids' => $this->ids($result)]],
            'payment_breakdown' => [
                ['type' => 'bar_chart', 'title' => $title, 'x_label' => 'Nilai pembayaran ('.$currency.')', 'y_label' => 'Metode', 'unit' => $currency, 'items' => collect($data['payments'])->map(fn ($row) => ['label' => strtoupper($row['payment_method']), 'value' => $row['amount'], 'formatted' => $this->money($row['amount'], $currency)])->all(), 'evidence_ids' => $this->ids($result)],
                ['type' => 'table', 'title' => $title, 'columns' => [['key' => 'method', 'label' => 'Metode'], ['key' => 'transactions', 'label' => 'Transaksi'], ['key' => 'amount', 'label' => 'Total']], 'rows' => collect($data['payments'])->map(fn ($row) => ['method' => strtoupper($row['payment_method']), 'transactions' => $row['transactions'], 'amount' => $this->money($row['amount'], $currency)])->all(), 'evidence_ids' => $this->ids($result)],
            ],
            'product_performance' => [
                ['type' => 'bar_chart', 'title' => $title, 'x_label' => 'Penjualan ('.$currency.')', 'y_label' => 'Produk', 'unit' => $currency, 'items' => collect($data['products'])->map(fn ($row) => ['label' => $row['name'], 'value' => $row['net_sales'], 'formatted' => $this->money($row['net_sales'], $currency)])->all(), 'evidence_ids' => $this->ids($result)],
                ['type' => 'table', 'title' => $title, 'columns' => [['key' => 'product', 'label' => 'Produk'], ['key' => 'quantity', 'label' => 'Terjual'], ['key' => 'sales', 'label' => 'Penjualan']], 'rows' => collect($data['products'])->map(fn ($row) => ['product' => $row['name'], 'quantity' => $row['quantity'], 'sales' => $this->money($row['net_sales'], $currency)])->all(), 'evidence_ids' => $this->ids($result)],
            ],
            'stock_status' => [['type' => 'table', 'title' => $title, 'columns' => [['key' => 'name', 'label' => 'Bahan'], ['key' => 'stock', 'label' => 'Stok'], ['key' => 'minimum', 'label' => 'Minimum'], ['key' => 'estimate', 'label' => 'Estimasi']], 'rows' => collect($data['ingredients'])->map(fn ($row) => ['name' => $row['name'], 'stock' => $row['stock'].' '.$row['unit'], 'minimum' => $row['minimum'].' '.$row['unit'], 'estimate' => $row['estimated_days_left'] === null ? 'Belum cukup data' : $row['estimated_days_left'].' hari'])->all(), 'evidence_ids' => $this->ids($result)]],
            'peak_hours' => [['type' => 'bar_chart', 'title' => $title, 'x_label' => 'Transaksi', 'y_label' => 'Jam', 'unit' => 'transaksi', 'items' => collect($data['hours'])->map(fn ($row) => ['label' => sprintf('%02d.00', $row['hour']), 'value' => $row['transactions'], 'formatted' => $row['transactions'].' transaksi'])->all(), 'evidence_ids' => $this->ids($result)]],
            'outlet_performance' => [['type' => 'bar_chart', 'title' => $title, 'x_label' => 'Penjualan ('.$currency.')', 'y_label' => 'Outlet', 'unit' => $currency, 'items' => collect($data['outlets'])->map(fn ($row) => ['label' => $row['name'], 'value' => $row['net_sales'], 'formatted' => $this->money($row['net_sales'], $currency)])->all(), 'evidence_ids' => $this->ids($result)]],
            'customer_performance' => [['type' => 'table', 'title' => $title, 'columns' => [['key' => 'name', 'label' => 'Pelanggan'], ['key' => 'visits', 'label' => 'Kunjungan'], ['key' => 'spending', 'label' => 'Belanja']], 'rows' => collect($data['customers'])->map(fn ($row) => ['name' => $row['name'], 'visits' => $row['visits'], 'spending' => $this->money($row['spending'], $currency)])->all(), 'evidence_ids' => $this->ids($result)]],
            'shift_summary' => [['type' => 'table', 'title' => $title, 'columns' => [['key' => 'cashier', 'label' => 'Kasir'], ['key' => 'outlet', 'label' => 'Outlet'], ['key' => 'status', 'label' => 'Status']], 'rows' => $data['shifts'], 'evidence_ids' => $this->ids($result)]],
            'recipe_cost' => [['type' => 'table', 'title' => $title, 'columns' => [['key' => 'name', 'label' => 'Produk'], ['key' => 'cost', 'label' => 'Estimasi biaya resep'], ['key' => 'coverage', 'label' => 'Cakupan']], 'rows' => collect($data['products'])->map(fn ($row) => ['name' => $row['name'], 'cost' => $this->money($row['estimated_recipe_cost'], $currency), 'coverage' => $row['costed_ingredient_count'].'/'.$row['ingredient_count'].' bahan'])->all(), 'evidence_ids' => $this->ids($result)]],
            default => [],
        };

        if ($visual === 'full') {
            return $all;
        }
        $wanted = match ($visual) {
            'metrics' => ['kpi'], 'chart' => ['line_chart', 'bar_chart', 'donut_chart'], 'table' => ['table'], default => []
        };

        return array_values(array_filter($all, fn ($block) => in_array($block['type'], $wanted, true) && $this->hasData($block)));
    }

    private function hasData(array $block): bool
    {
        return ! empty($block['metrics'] ?? $block['points'] ?? $block['items'] ?? $block['rows'] ?? []);
    }

    private function ids(array $result): array
    {
        return array_column($result['evidence_refs'], 'id');
    }

    private function money(float|int|null $value, string $currency): string
    {
        return ($currency === 'IDR' ? 'Rp ' : $currency.' ').number_format((float) $value, 0, ',', '.');
    }

    private function title(string $tool, string $period): string
    {
        return match ($tool) {
            'sales_summary' => 'Ringkasan '.$period, 'sales_series' => 'Tren penjualan '.$period, 'payment_breakdown' => 'Metode pembayaran '.$period, 'product_performance' => 'Performa produk '.$period, 'stock_status' => 'Kondisi stok', 'peak_hours' => 'Jam ramai '.$period, 'outlet_performance' => 'Performa outlet '.$period, 'customer_performance' => 'Pelanggan teratas '.$period, 'shift_summary' => 'Ringkasan shift '.$period, 'recipe_cost' => 'Estimasi biaya resep', default => 'Data Yoka'
        };
    }

    private function suggestions(string $tool, array $data): array
    {
        return match ($tool) {
            'sales_summary' => ['Buat grafik trennya', 'Bandingkan dengan periode sebelumnya'], 'payment_breakdown' => ['Jelaskan perbedaannya', 'Buatkan tabel rinci'], 'stock_status' => ['Mana yang perlu dibeli dulu?', 'Cek stok tiga hari ke depan'], default => []
        };
    }
}
