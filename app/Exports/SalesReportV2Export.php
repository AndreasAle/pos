<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * XLSX built from the same dataset as the screen (SalesReportController::exportData).
 * Every sheet starts with the period, outlets, timezone and generation time so a
 * file forwarded to a bookkeeper still says what it covers.
 */
class SalesReportV2Export implements WithMultipleSheets
{
    public function __construct(private array $d) {}

    public function sheets(): array
    {
        $d = $this->d;
        $m = $d['summary']['metrics'];

        $summary = [['Metrik', 'Nilai', 'Catatan']];
        foreach ([
            ['Penjualan kotor item', $m['gross_item_sales'], 'Subtotal item sebelum diskon order'],
            ['Diskon', $m['discounts'], ''],
            ['Penjualan bersih', $m['net_sales'], 'Penjualan kotor - diskon; tanpa pajak/service/ongkir'],
            ['Pajak', $m['tax_amount'], 'Ditambahkan di atas penjualan bersih'],
            ['Service', $m['service_amount'], ''],
            ['Ongkir', $m['delivery_fees'], ''],
            ['Total tagihan order', $m['order_grand_total'], 'Bukan penjualan bersih'],
            ['Pembayaran tercatat', $m['payment_collected'], 'Tercatat di POS, bukan settlement gateway'],
            ['Transaksi', $m['transactions'], 'Order paid'],
            ['Rata-rata transaksi (AOV)', $m['aov'] ?? 'N/A', 'Penjualan bersih / transaksi'],
            ['Estimasi laba kotor', $m['gross_profit_estimate'] ?? 'N/A', $m['cost_coverage_pct'] === null ? 'HPP tidak tersedia' : 'Cakupan HPP '.$m['cost_coverage_pct'].'% - bukan laba bersih'],
            ['Order refund (dikecualikan)', $d['summary']['excluded']['refunded']['orders'], 'Rp '.number_format($d['summary']['excluded']['refunded']['amount'], 0, ',', '.')],
            ['Order batal (dikecualikan)', $d['summary']['excluded']['cancelled']['orders'], ''],
        ] as $r) {
            $summary[] = $r;
        }
        foreach ($d['summary']['warnings'] as $w) {
            $summary[] = ['Peringatan', '', $w];
        }

        $payments = [['Metode', 'Order', 'Jumlah tercatat', '% dari total']];
        foreach ($d['summary']['payments'] as $p) {
            $payments[] = [$p['method'], $p['orders'], $p['amount'], $p['share_pct']];
        }

        $trend = [['Periode', 'Transaksi', 'Penjualan kotor', 'Diskon', 'Penjualan bersih', 'AOV']];
        foreach ($d['trend']['points'] as $p) {
            $trend[] = [$p['label'], $p['transactions'], $p['gross_item_sales'], $p['discounts'], $p['net_sales'], $p['aov'] ?? 'N/A'];
        }

        $cats = [['Kategori', 'Qty', 'Penjualan kotor', 'Penjualan bersih (diskon dialokasikan proporsional)']];
        foreach ($d['categories'] as $c) {
            $cats[] = [$c['name'], $c['quantity'], $c['gross_item_sales'], $c['net_sales']];
        }

        $prods = [['Produk', 'Varian', 'Qty', 'Penjualan kotor', 'Diskon dialokasikan', 'Penjualan bersih', 'HPP', 'Cakupan HPP %', 'Margin kotor estimasi', 'ID produk cocok']];
        foreach ($d['products'] as $p) {
            $prods[] = [$p['name'], $p['variant'], $p['quantity'], $p['gross_item_sales'], $p['discount_allocated'], $p['net_sales'], $p['cogs'], $p['cost_coverage_pct'] ?? 'N/A', $p['gross_margin_estimate'] ?? 'N/A', $p['matched'] ? 'ya' : 'tidak (item lama)'];
        }

        $orders = [['No. order', 'Waktu (lokal)', 'Outlet', 'Kasir', 'Tipe', 'Penjualan kotor', 'Diskon', 'Penjualan bersih', 'Pajak', 'Total tagihan', 'Metode bayar']];
        foreach ($d['orders'] as $o) {
            $methods = $o->is_split_payment ? $o->payments->pluck('payment_method')->unique()->implode(' + ') : $o->payment_method;
            $orders[] = [$o->order_number, $o->created_at->setTimezone($d['f']->timezone)->format('Y-m-d H:i'), $o->outlet?->name, $o->user?->name, $o->order_type, (float) $o->subtotal,
                (float) $o->discount_amount, (float) $o->subtotal - (float) $o->discount_amount, (float) $o->tax_amount, (float) $o->grand_total, $methods];
        }
        if ($d['orders_truncated']) {
            $orders[] = ['Hanya '.count($d['orders']).' dari '.$d['orders_total'].' order yang ditampilkan.'];
        }

        $meta = $this->meta();

        return [
            new SalesV2Sheet('Ringkasan', array_merge($meta, [[]], $summary), [34, 22, 60]),
            new SalesV2Sheet('Pembayaran', array_merge($meta, [[]], $payments), [20, 12, 20, 14]),
            new SalesV2Sheet('Tren', array_merge($meta, [[]], $trend), [22, 12, 18, 14, 18, 14]),
            new SalesV2Sheet('Kategori', array_merge($meta, [[]], $cats), [28, 12, 18, 26]),
            new SalesV2Sheet('Produk', array_merge($meta, [[]], $prods), [32, 18, 10, 16, 16, 16, 14, 12, 16, 16]),
            new SalesV2Sheet('Order', array_merge($meta, [[]], $orders), [20, 18, 18, 18, 12, 16, 12, 16, 12, 16, 20]),
        ];
    }

    /** @return array<int, array<int, string>> */
    private function meta(): array
    {
        $d = $this->d;

        return [
            ['Laporan Penjualan - '.$d['business']->name],
            ['Periode', $d['f']->dateFrom().' s/d '.$d['f']->dateTo()],
            ['Outlet', implode(', ', $d['outlet_names'])],
            ['Zona waktu', $d['f']->timezone],
            ['Dibuat', $d['generated_at']->format('Y-m-d H:i:s')],
            ['Versi metrik', $d['summary']['evidence']['metric_version'].' - filter '.$d['summary']['evidence']['filter_hash']],
        ];
    }
}

class SalesV2Sheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function __construct(private string $title, private array $rows, private array $widths) {}

    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function columnWidths(): array
    {
        $out = [];
        foreach ($this->widths as $i => $w) {
            $out[chr(65 + $i)] = $w;
        }

        return $out;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            8 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0C7856']]],
        ];
    }
}
