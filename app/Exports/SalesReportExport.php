<?php

namespace App\Exports;

use App\Models\Business;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class SalesReportExport implements WithMultipleSheets
{
    public function __construct(
        private Business $business,
        private array $filters
    ) {}

    public function sheets(): array
    {
        return [
            'Ringkasan'     => new SalesSummarySheet($this->business, $this->filters),
            'Per Hari'       => new SalesDailySheet($this->business, $this->filters),
            'Produk Terjual' => new SalesProductSheet($this->business, $this->filters),
            'Detail Order'   => new SalesOrderSheet($this->business, $this->filters),
            'Detail Item'    => new SalesItemSheet($this->business, $this->filters),
        ];
    }
}

// ── Sheet 1: Ringkasan ────────────────────────────────────────────────────────
class SalesSummarySheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(private Business $business, private array $f) {}

    public function title(): string { return 'Ringkasan'; }

    public function columnWidths(): array
    {
        return ['A' => 30, 'B' => 25, 'C' => 20];
    }

    public function headings(): array
    {
        return ['Metrik', 'Nilai', 'Keterangan'];
    }

    public function collection(): Collection
    {
        $q = Order::where('orders.business_id', $this->business->id)
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$this->f['date_from'], $this->f['date_to']]);

        if (!empty($this->f['outlet_id'])) {
            $q->where('orders.outlet_id', $this->f['outlet_id']);
        }

        $total   = (float) $q->sum('grand_total');
        $count   = $q->count();
        $disc    = (float) $q->sum('discount_amount');
        $tax     = (float) $q->sum('tax_amount');
        $avg     = $count > 0 ? round($total / $count) : 0;

        $byMethod = Order::where('orders.business_id', $this->business->id)
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$this->f['date_from'], $this->f['date_to']])
            ->when(!empty($this->f['outlet_id']), fn($q) => $q->where('orders.outlet_id', $this->f['outlet_id']))
            ->select('payment_method', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(grand_total) as total'))
            ->groupBy('payment_method')
            ->get();

        $rows = collect([
            ['Periode', $this->f['date_from'] . ' s/d ' . $this->f['date_to'], ''],
            ['Bisnis',  $this->business->name, ''],
            ['', '', ''],
            ['TOTAL OMZET',     'Rp ' . number_format($total, 0, ',', '.'), ''],
            ['TOTAL TRANSAKSI',  number_format($count), 'order'],
            ['RATA-RATA ORDER',  'Rp ' . number_format($avg, 0, ',', '.'), 'per transaksi'],
            ['TOTAL DISKON',     'Rp ' . number_format($disc, 0, ',', '.'), ''],
            ['TOTAL PAJAK',      'Rp ' . number_format($tax, 0, ',', '.'), ''],
            ['', '', ''],
            ['BREAKDOWN PEMBAYARAN', '', ''],
        ]);

        foreach ($byMethod as $m) {
            $rows->push([
                strtoupper($m->payment_method),
                'Rp ' . number_format($m->total, 0, ',', '.'),
                $m->cnt . ' transaksi',
            ]);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1  => ['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5']]],
            4  => ['font' => ['bold' => true, 'size' => 12], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']]],
            5  => ['font' => ['bold' => true]],
            6  => ['font' => ['bold' => true]],
            10 => ['font' => ['bold' => true, 'size' => 11], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']]],
        ];
    }
}

// ── Sheet 2: Per Hari ─────────────────────────────────────────────────────────
class SalesDailySheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(private Business $business, private array $f) {}

    public function title(): string { return 'Per Hari'; }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 15, 'C' => 20, 'D' => 15, 'E' => 15];
    }

    public function headings(): array
    {
        return ['Tanggal', 'Hari', 'Total Omzet (Rp)', 'Jumlah Transaksi', 'Rata-rata Order (Rp)'];
    }

    public function collection(): Collection
    {
        return Order::where('orders.business_id', $this->business->id)
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$this->f['date_from'], $this->f['date_to']])
            ->when(!empty($this->f['outlet_id']), fn($q) => $q->where('orders.outlet_id', $this->f['outlet_id']))
            ->select(
                DB::raw('DATE(orders.created_at) as tanggal'),
                DB::raw('DAYNAME(orders.created_at) as hari'),
                DB::raw('SUM(grand_total) as total'),
                DB::raw('COUNT(*) as jumlah'),
                DB::raw('ROUND(AVG(grand_total),0) as rata')
            )
            ->groupBy('tanggal', 'hari')
            ->orderBy('tanggal')
            ->get()
            ->map(fn($r) => [
                $r->tanggal,
                $r->hari,
                (float) $r->total,
                (int) $r->jumlah,
                (float) $r->rata,
            ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}

// ── Sheet: Produk Terjual ─────────────────────────────────────────────────────
class SalesProductSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(private Business $business, private array $f) {}

    public function title(): string { return 'Produk Terjual'; }

    public function columnWidths(): array
    {
        return ['A' => 32, 'B' => 18, 'C' => 12, 'D' => 20];
    }

    public function headings(): array
    {
        return ['Produk', 'Varian', 'Qty', 'Penjualan (Rp)'];
    }

    public function collection(): Collection
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $this->business->id)
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$this->f['date_from'], $this->f['date_to']])
            ->when(!empty($this->f['outlet_id']), fn($q) => $q->where('orders.outlet_id', $this->f['outlet_id']))
            ->select(
                'order_items.product_name',
                'order_items.variant_name',
                DB::raw('SUM(order_items.qty) as qty'),
                DB::raw('SUM(order_items.subtotal) as total')
            )
            ->groupBy('order_items.product_name', 'order_items.variant_name')
            ->orderByDesc('qty')
            ->get()
            ->map(fn($r) => [$r->product_name, $r->variant_name ?? '', (float) $r->qty, (float) $r->total]);

        return $rows->push(['TOTAL', '', $rows->sum(2), $rows->sum(3)]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
            ],
            $sheet->getHighestRow() => ['font' => ['bold' => true]],
        ];
    }
}

// ── Sheet: Detail Item (one row per item sold) ────────────────────────────────
class SalesItemSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(private Business $business, private array $f) {}

    public function title(): string { return 'Detail Item'; }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 18, 'C' => 16, 'D' => 30, 'E' => 16, 'F' => 8, 'G' => 16, 'H' => 18];
    }

    public function headings(): array
    {
        return ['No. Order', 'Waktu', 'Kasir', 'Produk', 'Varian', 'Qty', 'Harga (Rp)', 'Subtotal (Rp)'];
    }

    public function collection(): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.business_id', $this->business->id)
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$this->f['date_from'], $this->f['date_to']])
            ->when(!empty($this->f['outlet_id']), fn($q) => $q->where('orders.outlet_id', $this->f['outlet_id']))
            ->select(
                'orders.order_number',
                DB::raw('DATE_FORMAT(orders.created_at, "%d/%m/%Y %H:%i") as waktu'),
                'users.name as kasir',
                'order_items.product_name',
                'order_items.variant_name',
                'order_items.qty',
                'order_items.price',
                'order_items.subtotal'
            )
            ->orderBy('orders.created_at')
            ->orderBy('order_items.id')
            ->get()
            ->map(fn($r) => [
                $r->order_number, $r->waktu, $r->kasir, $r->product_name, $r->variant_name ?? '',
                (float) $r->qty, (float) $r->price, (float) $r->subtotal,
            ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
            ],
        ];
    }
}

// ── Sheet 3: Detail Order ─────────────────────────────────────────────────────
class SalesOrderSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(private Business $business, private array $f) {}

    public function title(): string { return 'Detail Order'; }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 18, 'C' => 16, 'D' => 18, 'E' => 55, 'F' => 16, 'G' => 16, 'H' => 28, 'I' => 14, 'J' => 16, 'K' => 14, 'L' => 16, 'M' => 16];
    }

    public function headings(): array
    {
        return ['No. Order', 'Tanggal', 'Kasir', 'Pelanggan', 'Item (qty x harga = subtotal)', 'Subtotal (Rp)', 'Diskon (Rp)', 'Promo', 'Pajak (Rp)', 'Total (Rp)', 'Pembayaran', 'Dibayar (Rp)', 'Kembalian (Rp)'];
    }

    public function collection(): Collection
    {
        $money = fn ($v) => number_format((float) $v, 0, ',', '.');

        return app(\App\Services\ReportService::class)
            ->detailedOrders($this->business, $this->f)
            ->reorder('created_at')
            ->get()
            ->map(fn ($o) => [
                $o->order_number,
                $o->created_at->format('d/m/Y H:i'),
                $o->user?->name,
                $o->customer?->name ?? '',
                $o->items->map(fn ($i) => (float) $i->qty . 'x ' . $i->product_name
                    . ($i->variant_name ? ' (' . $i->variant_name . ')' : '')
                    . ' @' . $money($i->price) . ' = ' . $money($i->subtotal))
                    ->implode("\n"),
                (float) $o->subtotal,
                (float) $o->discount_amount,
                $o->discount_amount > 0
                    ? ($o->promotion ? $o->promotion->name . ' (' . $o->promotion->label() . ')' : 'Diskon manual')
                    : '',
                (float) $o->tax_amount,
                (float) $o->grand_total,
                strtoupper($o->payment_method),
                (float) $o->paid_amount,
                (float) $o->change_amount,
            ]);
    }

    public function styles(Worksheet $sheet): array
    {
        // Items sit one per line inside the cell.
        $sheet->getStyle('E:E')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A:M')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
            ],
        ];
    }
}
