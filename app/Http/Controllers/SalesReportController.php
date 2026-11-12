<?php

namespace App\Http\Controllers;

use App\Exports\SalesReportV2Export;
use App\Models\ProductCategory;
use App\Services\Intelligence\MetricDictionary;
use App\Services\Reporting\SalesMetricsService;
use App\Services\Reporting\SalesReportFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Sales Reporting v2: Ringkasan, Analitik, Breakdown, Produk, Riwayat Order.
 * Owner/admin only (route middleware). Every number comes from SalesMetricsService.
 */
class SalesReportController extends Controller
{
    private const PDF_ORDER_LIMIT = 500;

    public function __construct(private SalesMetricsService $metrics) {}

    public function summary(Request $request)
    {
        return $this->guarded($request, 'reports.sales', function (SalesReportFilter $f) {
            $summary = $this->metrics->summary($f);

            return view('reports.sales.summary', [
                'f' => $f, 'tab' => 'summary', 'summary' => $summary,
                'trend' => $this->metrics->trend($f),
                'hours' => $this->metrics->trend($f->withWindow($f->start, $f->end)->withGrouping('hour')),
                'categories' => $this->metrics->categories($f, 6),
                'topProducts' => collect($this->metrics->productMix($f, 'net_sales', 'desc', 6)->items()),
                'recent' => $this->metrics->orderList($f, null, 'paid', 8),
                'compare' => $this->metrics->compare($f),
            ] + $this->context($f));
        });
    }

    public function analytics(Request $request)
    {
        return $this->guarded($request, 'reports.sales.analytics', function (SalesReportFilter $f) {
            $compare = $this->metrics->compare($f);

            return view('reports.sales.analytics', [
                'f' => $f, 'tab' => 'analytics', 'compare' => $compare, 'trend' => $this->metrics->trend($f),
                'summary' => $this->metrics->summary($f),
            ] + $this->context($f));
        });
    }

    public function breakdown(Request $request)
    {
        return $this->guarded($request, 'reports.sales.breakdown', function (SalesReportFilter $f) use ($request) {
            $row = (string) $request->query('row', 'category');
            $col = (string) $request->query('col', 'outlet');
            $metric = (string) $request->query('metric', 'net_sales');
            $matrix = null;
            $error = null;
            try {
                $matrix = $this->metrics->breakdown($f, $row, $col, $metric);
            } catch (ValidationException $e) {
                $error = collect($e->errors())->flatten()->first();
            }

            return view('reports.sales.breakdown', [
                'f' => $f, 'tab' => 'breakdown', 'matrix' => $matrix, 'matrixError' => $error,
                'row' => $row, 'col' => $col, 'metric' => $metric,
            ] + $this->context($f));
        });
    }

    public function products(Request $request)
    {
        return $this->guarded($request, 'reports.sales.products', function (SalesReportFilter $f) use ($request) {
            return view('reports.sales.products', [
                'f' => $f, 'tab' => 'products', 'summary' => $this->metrics->summary($f),
                'products' => $this->metrics->productMix($f, (string) $request->query('sort', 'net_sales'), (string) $request->query('dir', 'desc'))->withQueryString(),
                'sort' => (string) $request->query('sort', 'net_sales'), 'dir' => (string) $request->query('dir', 'desc'),
            ] + $this->context($f));
        });
    }

    public function orders(Request $request)
    {
        return $this->guarded($request, 'reports.sales.orders', function (SalesReportFilter $f) use ($request) {
            $status = (string) $request->query('status', 'paid');
            $search = trim((string) $request->query('q', '')) ?: null;
            $day = $this->drillWindow($f, $request);

            return view('reports.sales.orders', [
                'f' => $day, 'tab' => 'orders', 'status' => $status, 'search' => $search,
                'orders' => $this->metrics->orderList($day, $search, $status, 25, $request->filled('hour') ? (int) $request->query('hour') : null)->withQueryString(),
                'drill' => $request->filled('day') ? $request->query('day') : ($request->filled('hour') ? 'Jam '.sprintf('%02d', (int) $request->query('hour')).':00' : null),
            ] + $this->context($day));
        });
    }

    public function order(Request $request, int $orderId)
    {
        $f = SalesReportFilter::fromRequest($request->user(), []);

        return view('reports.sales.order', [
            'f' => $f, 'tab' => 'orders', 'order' => $this->metrics->orderDetail($f, $orderId),
            'back' => $request->query('back') && str_starts_with((string) $request->query('back'), url('/reports/sales')) ? $request->query('back') : route('reports.sales.orders'),
        ] + $this->context($f));
    }

    public function summaryJson(Request $request)
    {
        $f = SalesReportFilter::fromRequest($request->user(), $request->query());

        return response()->json(['filter' => $f->toQuery()] + $this->metrics->summary($f) + ['definitions' => $this->metrics->definitions()]);
    }

    public function trendsJson(Request $request)
    {
        $f = SalesReportFilter::fromRequest($request->user(), $request->query());

        return response()->json($this->metrics->trend($f) + ['evidence' => $this->metrics->evidence($f)]);
    }

    // ── Export ───────────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        $f = SalesReportFilter::fromRequest($request->user(), $request->query());
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 422);
        $this->auditExport($request, $f, $format);
        $name = 'laporan-penjualan-'.$f->dateFrom().'-sd-'.$f->dateTo();

        if ($format === 'xlsx') {
            return Excel::download(new SalesReportV2Export($this->exportData($f)), $name.'.xlsx');
        }

        $data = $this->exportData($f, self::PDF_ORDER_LIMIT);

        return Pdf::loadView('reports.pdf.sales-v2', $data)->setPaper('a4', 'landscape')
            ->setOptions(['dpi' => 96, 'defaultFont' => 'sans-serif'])->download($name.'.pdf');
    }

    /** Same service calls as the screen, so the file can never disagree with it. */
    public function exportData(SalesReportFilter $f, ?int $orderLimit = null): array
    {
        $business = auth()->user()->business;
        $summary = $this->metrics->summary($f);
        $list = $this->metrics->orderList($f, null, 'paid', $orderLimit ?? 5000);

        return [
            'business' => $business, 'f' => $f, 'summary' => $summary, 'trend' => $this->metrics->trend($f),
            'categories' => $this->metrics->categories($f, 50),
            'products' => collect($this->metrics->productMix($f, 'net_sales', 'desc', $orderLimit ?? 5000)->items()),
            'orders' => collect($list->items()), 'orders_total' => $list->total(), 'orders_truncated' => $list->total() > count($list->items()),
            'outlet_names' => $business->outlets()->whereIn('id', $f->outletIds)->pluck('name')->all(),
            'generated_at' => CarbonImmutable::now($f->timezone), 'compare' => $this->metrics->compare($f),
        ];
    }

    private function auditExport(Request $request, SalesReportFilter $f, string $format): void
    {
        // No customer data, only who/what/when and a hash of the filter.
        Log::info('sales_report_export', [
            'request_id' => $request->header('X-Request-Id') ?: (string) Str::uuid(), 'user_id' => $request->user()->id,
            'business_id' => $f->businessId, 'report' => 'sales', 'format' => $format, 'filter_hash' => $f->hash(), 'outcome' => 'started',
        ]);
    }

    // ── Tanya Yoka hand-off ──────────────────────────────────────────────────

    /**
     * Hands a report to Tanya Yoka as an encrypted reference. The reference carries only
     * allowlisted filters and is re-authenticated against the logged-in user on arrival,
     * so it can never point the assistant at another tenant.
     */
    public function ask(Request $request)
    {
        abort_unless(config('pos.features.intelligence'), 404);
        $f = SalesReportFilter::fromRequest($request->user(), $request->query());
        $ref = Crypt::encryptString(json_encode([
            'report_type' => 'sales', 'business_id' => $f->businessId, 'user_id' => $request->user()->id,
            'filters' => $f->toQuery(), 'metric_version' => MetricDictionary::VERSION,
            'as_of' => $f->asOf?->toIso8601String(), 'exp' => now()->addMinutes(30)->timestamp,
        ]));

        return redirect()->route('intelligence.index', ['report_ref' => $ref]);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function guarded(Request $request, string $route, \Closure $render)
    {
        try {
            $f = SalesReportFilter::fromRequest($request->user(), $request->query());
        } catch (ValidationException $e) {
            return redirect()->route($route)->withErrors($e->errors())->with('report_error', collect($e->errors())->flatten()->first());
        }

        return $render($f);
    }

    /** Clicking a chart bar sends day= or hour=; narrow the window without leaving the filter's scope. */
    private function drillWindow(SalesReportFilter $f, Request $request): SalesReportFilter
    {
        if (! $request->filled('day')) {
            return $f;
        }
        try {
            $d = CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query('day'), $f->timezone);
        } catch (\Throwable) {
            return $f;
        }
        $start = $d->max($f->start);
        $end = $d->addDay()->min($f->end);

        return $start->lt($end) ? $f->withWindow($start, $end) : $f;
    }

    private function context(SalesReportFilter $f): array
    {
        $user = auth()->user();

        return [
            'outlets' => $user->business->outlets()->whereIn('id', $f->allowedOutletIds)->get(['id', 'name']),
            'cashiers' => $user->business->users()->whereIn('role', ['owner', 'admin', 'cashier'])->orderBy('name')->get(['id', 'name']),
            'categoriesAll' => ProductCategory::where('business_id', $f->businessId)->orderBy('name')->get(['id', 'name']),
            'queryString' => $f->toQuery(),
            'definitions' => $this->metrics->definitions(),
        ];
    }
}
