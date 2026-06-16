<?php

namespace App\Exports;

use App\Models\Business;
use App\Models\CashierShift;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ShiftReportExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(
        private Business $business,
        private array $filters
    ) {}

    public function title(): string { return 'Laporan Shift'; }

    public function columnWidths(): array
    {
        return ['A' => 22, 'B' => 20, 'C' => 20, 'D' => 20, 'E' => 18, 'F' => 18, 'G' => 18, 'H' => 15, 'I' => 12];
    }

    public function headings(): array
    {
        return [
            'Kasir', 'Outlet', 'Waktu Buka', 'Waktu Tutup',
            'Modal Awal (Rp)', 'Cash Expected (Rp)', 'Cash Aktual (Rp)', 'Selisih (Rp)', 'Status',
        ];
    }

    public function collection(): Collection
    {
        $f = $this->filters;

        return CashierShift::where('cashier_shifts.business_id', $this->business->id)
            ->whereBetween(DB::raw('DATE(opened_at)'), [$f['date_from'], $f['date_to']])
            ->when(!empty($f['outlet_id']), fn($q) => $q->where('outlet_id', $f['outlet_id']))
            ->join('users',   'users.id',   '=', 'cashier_shifts.user_id')
            ->join('outlets', 'outlets.id', '=', 'cashier_shifts.outlet_id')
            ->select(
                'users.name as kasir',
                'outlets.name as outlet',
                'cashier_shifts.opened_at',
                'cashier_shifts.closed_at',
                'cashier_shifts.opening_cash',
                'cashier_shifts.closing_cash_expected',
                'cashier_shifts.closing_cash_actual',
                'cashier_shifts.cash_difference',
                'cashier_shifts.status'
            )
            ->orderBy('cashier_shifts.opened_at')
            ->get()
            ->map(fn($r) => [
                $r->kasir,
                $r->outlet,
                $r->opened_at ? \Carbon\Carbon::parse($r->opened_at)->format('d/m/Y H:i') : '-',
                $r->closed_at ? \Carbon\Carbon::parse($r->closed_at)->format('d/m/Y H:i') : 'Aktif',
                (float) $r->opening_cash,
                $r->status === 'closed' ? (float) $r->closing_cash_expected : '-',
                $r->status === 'closed' ? (float) $r->closing_cash_actual   : '-',
                $r->status === 'closed' ? (float) $r->cash_difference       : '-',
                strtoupper($r->status),
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
