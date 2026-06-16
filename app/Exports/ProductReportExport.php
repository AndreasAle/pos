<?php

namespace App\Exports;

use App\Models\Business;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ProductReportExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(
        private Business $business,
        private array $filters
    ) {}

    public function title(): string { return 'Produk Terlaris'; }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 35, 'C' => 15, 'D' => 20, 'E' => 18, 'F' => 18, 'G' => 12];
    }

    public function headings(): array
    {
        return ['#', 'Nama Produk', 'Qty Terjual', 'Total Revenue (Rp)', 'Total HPP (Rp)', 'Est. Profit (Rp)', 'Margin (%)'];
    }

    public function collection(): Collection
    {
        $f = $this->filters;

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $this->business->id)
            ->where('orders.status', 'paid')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$f['date_from'], $f['date_to']])
            ->when(!empty($f['outlet_id']), fn($q) => $q->where('orders.outlet_id', $f['outlet_id']))
            ->select(
                'order_items.product_name',
                DB::raw('SUM(order_items.qty) as total_qty'),
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
                DB::raw('SUM(order_items.qty * order_items.cost_price) as total_cost')
            )
            ->groupBy('order_items.product_name')
            ->orderByDesc('total_qty')
            ->get()
            ->map(function ($row, $i) {
                $profit = (float) $row->total_revenue - (float) $row->total_cost;
                $margin = (float) $row->total_revenue > 0
                    ? round($profit / (float) $row->total_revenue * 100, 2)
                    : 0;

                return [
                    $i + 1,
                    $row->product_name,
                    (float) $row->total_qty,
                    (float) $row->total_revenue,
                    (float) $row->total_cost,
                    $profit,
                    $margin . '%',
                ];
            });
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
