<?php

namespace App\Http\Controllers;

use App\Exports\ProductReportExport;
use App\Exports\SalesReportExport;
use App\Exports\ShiftReportExport;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(protected ReportService $report)
    {
        // Increase execution time for export operations
        ini_set('max_execution_time', 120);
        ini_set('memory_limit', '256M');
    }

    public function sales(Request $request)
    {
        $data = $this->report->salesReport(auth()->user()->business, $request->all());
        return view('reports.sales', $data);
    }

    public function products(Request $request)
    {
        $data = $this->report->productReport(auth()->user()->business, $request->all());
        return view('reports.products', $data);
    }

    public function cashier(Request $request)
    {
        $data = $this->report->cashierReport(auth()->user()->business, $request->all());
        return view('reports.cashier', $data);
    }

    public function shift(Request $request)
    {
        $data = $this->report->shiftReport(auth()->user()->business, $request->all());
        return view('reports.shift', $data);
    }

    public function inventory(Request $request)
    {
        $data = $this->report->inventoryReport(auth()->user()->business, $request->all());
        return view('reports.inventory', $data);
    }

    public function profit(Request $request)
    {
        $data = $this->report->profitReport(auth()->user()->business, $request->all());
        return view('reports.profit', $data);
    }

    // ── Export Excel ──────────────────────────────────────────────────────────

    public function exportSales(Request $request)
    {
        $business  = auth()->user()->business;
        $filters   = $this->report->getFilters($request->all());
        $filename  = 'laporan-penjualan-' . $filters['date_from'] . '-sd-' . $filters['date_to'] . '.xlsx';

        return Excel::download(
            new SalesReportExport($business, $filters),
            $filename
        );
    }

    public function exportProducts(Request $request)
    {
        $business  = auth()->user()->business;
        $filters   = $this->report->getFilters($request->all());
        $filename  = 'laporan-produk-' . $filters['date_from'] . '-sd-' . $filters['date_to'] . '.xlsx';

        return Excel::download(
            new ProductReportExport($business, $filters),
            $filename
        );
    }

    public function exportShifts(Request $request)
    {
        $business  = auth()->user()->business;
        $filters   = $this->report->getFilters($request->all());
        $filename  = 'laporan-shift-' . $filters['date_from'] . '-sd-' . $filters['date_to'] . '.xlsx';

        return Excel::download(
            new ShiftReportExport($business, $filters),
            $filename
        );
    }

    // ── Export PDF ────────────────────────────────────────────────────────────

    public function exportSalesPdf(Request $request)
    {
        $business = auth()->user()->business;
        $data     = $this->report->salesReport($business, $request->all());
        $data['business'] = $business;

        $pdf = Pdf::loadView('reports.pdf.sales', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions(['dpi' => 96, 'defaultFont' => 'sans-serif']);

        $filename = 'laporan-penjualan-' . $data['f']['date_from'] . '.pdf';
        return $pdf->download($filename);
    }

    public function exportProductsPdf(Request $request)
    {
        $business = auth()->user()->business;
        $data     = $this->report->productReport($business, $request->all());
        $data['business'] = $business;

        $pdf = Pdf::loadView('reports.pdf.products', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions(['dpi' => 96, 'defaultFont' => 'sans-serif']);

        return $pdf->download('laporan-produk-terlaris.pdf');
    }

    public function exportProfitPdf(Request $request)
    {
        $business = auth()->user()->business;
        $data     = $this->report->profitReport($business, $request->all());
        $data['business'] = $business;

        $pdf = Pdf::loadView('reports.pdf.profit', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions(['dpi' => 96, 'defaultFont' => 'sans-serif']);

        return $pdf->download('laporan-profit.pdf');
    }
}
