<?php

namespace App\Exports;

use App\Models\Business;
use App\Models\Order;
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
            'Per Hari'      => new SalesDailySheet($this->business, $this->filters),
            'Detail Order'  => new SalesOrderSheet($this->business, $this->filters),
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

// ── Sheet 3: Detail Order ─────────────────────────────────────────────────────
class SalesOrderSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(private Business $business, private array $f) {}

    public function title(): string { return 'Detail Order'; }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 18, 'C' => 18, 'D' => 15, 'E' => 18, 'F' => 18, 'G' => 15, 'H' => 18, 'I' => 18];
    }

    public function headings(): array
    {
        return ['No. Order', 'Tanggal', 'Kasir', 'Outlet', 'Subtotal (Rp)', 'Diskon (Rp)', 'Pajak (Rp)', 'Total (Rp)', 'Pembayaran'];
    }

    public function collection(): Collection
    {
        return Order::where('orders.business_id', $this->business->id)
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$this->f['date_from'], $this->f['date_to']])
            ->when(!empty($this->f['outlet_id']), fn($q) => $q->where('orders.outlet_id', $this->f['outlet_id']))
            ->join('users',   'users.id',   '=', 'orders.user_id')
            ->join('outlets', 'outlets.id', '=', 'orders.outlet_id')
            ->select(
                'orders.order_number',
                DB::raw('DATE_FORMAT(orders.created_at, "%d/%m/%Y %H:%i") as waktu'),
                'users.name as kasir',
                'outlets.name as outlet',
                'orders.subtotal',
                'orders.discount_amount',
                'orders.tax_amount',
                'orders.grand_total',
                'orders.payment_method'
            )
            ->orderBy('orders.created_at')
            ->get()
            ->map(fn($r) => [
                $r->order_number,
                $r->waktu,
                $r->kasir,
                $r->outlet,
                (float) $r->subtotal,
                (float) $r->discount_amount,
                (float) $r->tax_amount,
                (float) $r->grand_total,
                strtoupper($r->payment_method),
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
