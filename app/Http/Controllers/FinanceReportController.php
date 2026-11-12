<?php

namespace App\Http\Controllers;

use App\Exports\FinanceSheetsExport;
use App\Models\Order;
use App\Services\Finance\FinanceReports;
use App\Services\Intelligence\MetricDictionary;
use App\Services\Reporting\SalesMetricsService;
use App\Services\Reporting\SalesReportFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/** Laporan Pajak, Retur Transaksi, Laba Rugi. Owner/admin only (route middleware). */
class FinanceReportController extends Controller
{
    public function __construct(private FinanceReports $reports, private SalesMetricsService $sales) {}

    public function tax(Request $request)
    {
        return $this->guard($request, 'reports.tax', function (SalesReportFilter $f) use ($request) {
            $data = $this->reports->tax($f);
            if ($request->query('export') === 'xlsx') {
                return $this->xlsx($request, $f, 'pajak', $this->taxSheets($f, $data));
            }

            return view('reports.fin.tax', ['f' => $f, 'r' => $data] + $this->ctx($f));
        });
    }

    public function returns(Request $request)
    {
        return $this->guard($request, 'reports.returns', function (SalesReportFilter $f) use ($request) {
            $data = $this->reports->returns($f);
            $status = in_array($request->query('status'), ['refunded', 'cancelled', 'all'], true) ? $request->query('status') : 'all';
            $list = $this->returnList($f, $request, $status);
            if ($request->query('export') === 'xlsx') {
                return $this->xlsx($request, $f, 'retur', $this->returnSheets($f, $data));
            }

            return view('reports.fin.returns', ['f' => $f, 'r' => $data, 'orders' => $list->withQueryString(), 'status' => $status] + $this->ctx($f));
        });
    }

    public function profitLoss(Request $request)
    {
        return $this->guard($request, 'reports.profit-loss', function (SalesReportFilter $f) use ($request) {
            $data = $this->reports->profitLoss($f);
            $prior = null;
            if ($f->comparison === 'previous') {
                $prior = $this->reports->profitLoss($f->comparisonFilter());
            }
            $format = $request->query('export');
            if ($format === 'xlsx') {
                return $this->xlsx($request, $f, 'laba-rugi', $this->plSheets($f, $data, $prior));
            }
            if ($format === 'pdf') {
                $this->audit($request, $f, 'laba-rugi', 'pdf');

                return Pdf::loadView('reports.pdf.profit-loss', ['f' => $f, 'r' => $data, 'prior' => $prior, 'business' => $request->user()->business, 'generated_at' => CarbonImmutable::now($f->timezone), 'outlet_names' => $request->user()->business->outlets()->whereIn('id', $f->outletIds)->pluck('name')->all()])
                    ->setPaper('a4', 'portrait')->setOptions(['dpi' => 96, 'defaultFont' => 'sans-serif'])->download('laba-rugi-'.$f->dateFrom().'-sd-'.$f->dateTo().'.pdf');
            }

            return view('reports.fin.profit-loss', ['f' => $f, 'r' => $data, 'prior' => $prior] + $this->ctx($f));
        });
    }

    private function returnList(SalesReportFilter $f, Request $request, string $status)
    {
        $q = $this->sales->orders($f, 'orders', true, $status === 'all' ? ['refunded', 'cancelled'] : [$status], Order::query())
            ->when(trim((string) $request->query('q', '')), fn ($b, $s) => $b->where('orders.order_number', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%'))
            ->with(['outlet:id,name', 'user:id,name', 'payments:id,order_id,payment_method,amount'])->orderByDesc('orders.created_at');

        return $q->paginate(25);
    }

    // ── Spreadsheet sheets (same numbers as the screen) ─────────────────────

    private function meta(SalesReportFilter $f, string $title): array
    {
        $names = auth()->user()->business->outlets()->whereIn('id', $f->outletIds)->pluck('name')->implode(', ');

        return [[$title.' - '.auth()->user()->business->name], ['Periode', $f->dateFrom().' s/d '.$f->dateTo()], ['Outlet', $names], ['Zona waktu', $f->timezone], ['Dibuat', now($f->timezone)->format('Y-m-d H:i:s')], ['Versi metrik', MetricDictionary::VERSION], []];
    }

    private function plSheets(SalesReportFilter $f, array $r, ?array $prior): array
    {
        $col = fn ($key, $fn) => $prior ? [$fn($r), $fn($prior)] : [$fn($r)];
        $rows = [$prior ? ['Pos', 'Periode ini', 'Pembanding'] : ['Pos', 'Jumlah']];
        $add = fn (string $label, callable $fn) => $rows[] = array_merge([$label], $col($label, $fn));
        $add('Penjualan kotor item', fn ($x) => $x['revenue']['gross_item_sales']);
        $add('Diskon', fn ($x) => -$x['revenue']['discounts']);
        $add('Penjualan bersih', fn ($x) => $x['revenue']['net_sales']);
        $add('Harga pokok penjualan (HPP)', fn ($x) => -$x['cogs']);
        $add('LABA KOTOR', fn ($x) => $x['gross_profit']);
        foreach ($r['opex'] as $i => $o) {
            $add($o['code'].' '.$o['name'], fn ($x) => -($x['opex'][$i]['amount'] ?? 0));
        }
        $add('Total beban operasional', fn ($x) => -$x['opex_total']);
        $add('LABA OPERASIONAL', fn ($x) => $x['operating_profit']);
        $add('Beban lain-lain', fn ($x) => -$x['other_total']);
        $add('LABA SEBELUM PAJAK PENGHASILAN', fn ($x) => $x['profit_before_tax']);
        $notes = [[], ['Catatan'], ['Pajak penjualan (PB1/PPN), service, dan ongkir tidak termasuk penjualan bersih.'], ['Beban dicatat berdasarkan tanggal biaya (basis kas). Pembelian stok adalah persediaan; HPP berasal dari snapshot harga pokok item terjual.'], ['Bukan laporan keuangan teraudit; pajak penghasilan tidak dihitung.']];
        foreach ($r['warnings'] as $w) {
            $notes[] = ['Peringatan: '.$w];
        }

        return ['Laba Rugi' => array_merge($this->meta($f, 'Laporan Laba Rugi'), $rows, $notes)];
    }

    private function taxSheets(SalesReportFilter $f, array $r): array
    {
        $sum = [['Metrik', 'Nilai'], ['Dasar pengenaan pajak (DPP)', $r['taxable_base']], ['Pajak dipungut', $r['tax_collected']], ['Tarif efektif %', $r['effective_rate_pct'] ?? 'N/A'], ['Pajak atas order refund/batal', $r['tax_reversed']['amount']], ['Setoran pajak tercatat', $r['tax_remitted']], ['Saldo pajak belum disetor', $r['tax_balance']], ['Service charge dipungut', $r['service_collected']]];
        $day = [['Tanggal', 'Order', 'DPP', 'Pajak', 'Service']];
        foreach ($r['by_day'] as $d) {
            $day[] = [$d['date'], $d['orders'], $d['base'], $d['tax'], $d['service']];
        }
        $out = [['Outlet', 'Order', 'DPP', 'Pajak']];
        foreach ($r['by_outlet'] as $d) {
            $out[] = [$d['name'], $d['orders'], $d['base'], $d['tax']];
        }
        $m = $this->meta($f, 'Laporan Pajak');

        return ['Ringkasan' => array_merge($m, $sum), 'Per Hari' => array_merge($m, $day), 'Per Outlet' => array_merge($m, $out)];
    }

    private function returnSheets(SalesReportFilter $f, array $r): array
    {
        $sum = [['Jenis', 'Order', 'Nilai tagihan', 'Pajak'], ['Refund', $r['refunded']['orders'], $r['refunded']['grand_total'], $r['refunded']['tax']], ['Batal', $r['cancelled']['orders'], $r['cancelled']['grand_total'], $r['cancelled']['tax']], ['Tingkat retur %', $r['return_rate_pct'] ?? 'N/A']];
        $why = [['Jenis', 'Alasan', 'Order', 'Nilai']];
        foreach ($r['reasons'] as $x) {
            $why[] = [$x['status'], $x['reason'], $x['orders'], $x['amount']];
        }
        $m = $this->meta($f, 'Laporan Retur Transaksi');

        return ['Ringkasan' => array_merge($m, $sum), 'Alasan' => array_merge($m, $why)];
    }

    private function xlsx(Request $request, SalesReportFilter $f, string $name, array $sheets)
    {
        $this->audit($request, $f, $name, 'xlsx');

        return Excel::download(new FinanceSheetsExport($sheets), $name.'-'.$f->dateFrom().'-sd-'.$f->dateTo().'.xlsx');
    }

    private function audit(Request $request, SalesReportFilter $f, string $report, string $format): void
    {
        Log::info('finance_report_export', ['user_id' => $request->user()->id, 'business_id' => $f->businessId, 'report' => $report, 'format' => $format, 'filter_hash' => $f->hash()]);
    }

    private function guard(Request $request, string $route, \Closure $render)
    {
        try {
            $f = SalesReportFilter::fromRequest($request->user(), $request->query());
        } catch (ValidationException $e) {
            return redirect()->route($route)->with('report_error', collect($e->errors())->flatten()->first());
        }

        return $render($f);
    }

    private function ctx(SalesReportFilter $f): array
    {
        return ['outlets' => auth()->user()->business->outlets()->whereIn('id', $f->allowedOutletIds)->get(['id', 'name']), 'queryString' => $f->toQuery()];
    }
}
