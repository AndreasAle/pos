<?php

namespace Tests\Feature\Reports;

use App\Exports\SalesReportV2Export;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\Reporting\SalesMetricsService;
use App\Services\Reporting\SalesReportFilter;
use App\Services\YokaToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

class SalesReportV2Test extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPos();
        $this->business->update(['timezone' => 'Asia/Jakarta']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'outlet_id' => null, 'role' => 'owner']);
    }

    private function sale(float $subtotal = 100000, float $discount = 10000, float $tax = 9000, array $o = [], array $payments = []): Order
    {
        $grand = $subtotal - $discount + $tax;
        $order = Order::create(array_merge([
            'business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id,
            'order_number' => 'R-'.uniqid(), 'subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax,
            'grand_total' => $grand, 'paid_amount' => $grand, 'payment_method' => 'cash', 'payment_status' => 'paid', 'status' => 'paid',
            'is_split_payment' => (bool) $payments,
        ], $o));
        OrderItem::create(['order_id' => $order->id, 'product_name' => 'Kopi', 'price' => $subtotal, 'qty' => 1, 'subtotal' => $subtotal, 'cost_price' => 40000]);
        foreach ($payments as $p) {
            OrderPayment::create(['order_id' => $order->id, 'payment_method' => $p[0], 'amount' => $p[1]]);
        }

        return $order;
    }

    public function test_cashier_cannot_open_any_sales_report_screen_or_export(): void
    {
        foreach (['reports.sales', 'reports.sales.analytics', 'reports.sales.breakdown', 'reports.sales.products', 'reports.sales.orders', 'reports.sales.export', 'reports.sales.pdf', 'reports.sales.summary', 'reports.profit'] as $route) {
            $this->actingAs($this->cashier)->get(route($route))->assertForbidden();
        }
    }

    public function test_summary_shows_net_sales_not_grand_total_and_labels_payments_as_recorded(): void
    {
        $this->sale(payments: [['cash', 40000], ['qris', 59000]]);

        $this->actingAs($this->owner)->get(route('reports.sales'))
            ->assertOk()
            ->assertSee('Penjualan bersih')
            ->assertSee('Rp 90.000')
            ->assertSee('Rp 99.000')
            ->assertSee('bukan settlement', false)
            ->assertSee('Riwayat Order');
    }

    public function test_empty_state_has_no_chart_and_offers_open_pos(): void
    {
        $this->actingAs($this->owner)->get(route('reports.sales'))
            ->assertOk()
            ->assertSee('Belum ada transaksi pada filter ini')
            ->assertSee('Buka Kasir')
            ->assertDontSee('id="trendChart"', false);
    }

    public function test_flag_off_keeps_the_legacy_report(): void
    {
        config(['pos.features.reports_v2' => false]);
        $this->sale();

        $this->actingAs($this->owner)->get(route('reports.sales'))->assertOk()->assertSee('Total Omzet');
    }

    public function test_foreign_outlet_is_rejected_without_leaking_data(): void
    {
        $foreign = $this->foreignUser();
        Order::create(['business_id' => $foreign->business_id, 'outlet_id' => $foreign->outlet_id, 'user_id' => $foreign->id, 'order_number' => 'LEAK-1',
            'subtotal' => 777777, 'grand_total' => 777777, 'status' => 'paid', 'payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->actingAs($this->owner)->get(route('reports.sales.orders', ['outlet_ids' => [$foreign->outlet_id]]))
            ->assertRedirect(route('reports.sales.orders'));
        $this->actingAs($this->owner)->get(route('reports.sales.summary', ['outlet_ids' => [$foreign->outlet_id]]))->assertStatus(302);
        $this->actingAs($this->owner)->get(route('reports.sales.orders'))->assertDontSee('LEAK-1')->assertDontSee('777.777');
    }

    public function test_order_detail_is_tenant_scoped(): void
    {
        $mine = $this->sale();
        $foreign = $this->foreignUser();
        $theirs = Order::create(['business_id' => $foreign->business_id, 'outlet_id' => $foreign->outlet_id, 'user_id' => $foreign->id, 'order_number' => 'THEIRS',
            'subtotal' => 5, 'grand_total' => 5, 'status' => 'paid', 'payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->actingAs($this->owner)->get(route('reports.sales.order', $mine->id))->assertOk()->assertSee($mine->order_number)->assertSee('Rincian angka');
        $this->actingAs($this->owner)->get(route('reports.sales.order', $theirs->id))->assertNotFound();
    }

    public function test_orders_page_paginates_25_and_totals_do_not_depend_on_the_page(): void
    {
        foreach (range(1, 30) as $i) {
            $this->sale(10000, 0, 0);
        }
        $svc = app(SalesMetricsService::class);
        $f = SalesReportFilter::fromRequest($this->owner, []);
        $total = $svc->summary($f)['metrics']['net_sales'];

        $this->actingAs($this->owner)->get(route('reports.sales.orders'))->assertOk()->assertSee('30 order');
        $this->assertSame(25, $svc->orderList($f)->count());
        $this->assertSame(30, $svc->orderList($f)->total());
        $this->assertSame(300000.0, $total);
    }

    public function test_breakdown_explains_why_an_invalid_combination_is_refused(): void
    {
        $this->sale();

        $this->actingAs($this->owner)->get(route('reports.sales.breakdown', ['row' => 'payment_method', 'col' => 'category', 'metric' => 'net_sales']))
            ->assertOk()->assertSee('tidak dapat diatribusikan', false);
        $this->actingAs($this->owner)->get(route('reports.sales.breakdown', ['row' => 'outlet', 'col' => 'weekday', 'metric' => 'net_sales']))
            ->assertOk()->assertSee('Rp 90.000');
    }

    public function test_analytics_zero_baseline_renders_na_not_infinity(): void
    {
        $this->sale();

        $this->actingAs($this->owner)->get(route('reports.sales.analytics', ['comparison' => 'previous', 'preset' => 'this_month']))
            ->assertOk()->assertSee('N/A')->assertDontSee('INF')->assertDontSee('NaN');
    }

    public function test_xlsx_export_matches_screen_numbers_and_carries_metadata(): void
    {
        Excel::fake();
        $this->sale(payments: [['cash', 40000], ['qris', 59000]]);

        $this->actingAs($this->owner)->get(route('reports.sales.export', ['preset' => 'this_month']))->assertOk();

        Excel::assertDownloaded('laporan-penjualan-'.now('Asia/Jakarta')->startOfMonth()->toDateString().'-sd-'.now('Asia/Jakarta')->endOfMonth()->toDateString().'.xlsx', function (SalesReportV2Export $e) {
            $summary = $e->sheets()[0]->array();
            $flat = collect($summary)->filter(fn ($r) => isset($r[0]))->mapWithKeys(fn ($r) => [$r[0] => $r[1] ?? null]);
            $this->assertSame(90000.0, $flat['Penjualan bersih']);
            $this->assertSame(99000.0, $flat['Pembayaran tercatat']);
            $this->assertSame('Asia/Jakarta', $flat['Zona waktu']);
            $this->assertNotEmpty($flat['Dibuat']);
            $this->assertSame(1, $flat['Transaksi']);

            return true;
        });
    }

    public function test_pdf_export_downloads(): void
    {
        $this->sale();

        $r = $this->actingAs($this->owner)->get(route('reports.sales.pdf'));

        $r->assertOk();
        $this->assertStringContainsString('pdf', $r->headers->get('content-type'));
    }

    public function test_tanya_yoka_numbers_equal_report_numbers(): void
    {
        $this->sale(payments: [['cash', 70000], ['qris', 29000]]);
        $f = SalesReportFilter::fromRequest($this->owner, ['preset' => 'today']);
        $report = app(SalesMetricsService::class)->summary($f);

        $ai = app(YokaToolRegistry::class)->execute($this->owner, 'sales_summary', ['period' => 'today']);
        $pay = app(YokaToolRegistry::class)->execute($this->owner, 'payment_breakdown', ['period' => 'today']);

        $this->assertSame($report['metrics']['net_sales'], $ai['data']['net_sales']);
        $this->assertSame($report['metrics']['gross_item_sales'], $ai['data']['gross_item_sales']);
        $this->assertSame($report['metrics']['payment_collected'], $pay['data']['total']);
        $this->assertSame($report['metrics']['transactions'], $ai['data']['transactions']);
    }

    public function test_ai_handoff_is_bound_to_the_logged_in_user_and_tenant(): void
    {
        $this->sale();
        $response = $this->actingAs($this->owner)->get(route('reports.sales.ask', ['preset' => 'this_month']));
        $response->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $q);

        $this->actingAs($this->owner)->get(route('intelligence.index', ['report_ref' => $q['report_ref']]))->assertOk()->assertSee('Analisis laporan penjualan', false);

        // A reference minted for another tenant/user is ignored, never honoured.
        $foreign = $this->foreignUser();
        $forged = Crypt::encryptString(json_encode(['report_type' => 'sales', 'business_id' => $foreign->business_id, 'user_id' => $this->owner->id,
            'filters' => ['preset' => 'this_month'], 'exp' => now()->addMinutes(5)->timestamp]));
        $this->actingAs($this->owner)->get(route('intelligence.index', ['report_ref' => $forged]))->assertOk()->assertDontSee('Analisis laporan penjualan', false);
        $this->actingAs($this->owner)->get(route('intelligence.index', ['report_ref' => 'garbage']))->assertOk()->assertDontSee('Analisis laporan penjualan', false);
    }

    public function test_every_sales_screen_renders_for_owner(): void
    {
        $this->sale();
        foreach (['reports.sales', 'reports.sales.analytics', 'reports.sales.breakdown', 'reports.sales.products', 'reports.sales.orders'] as $route) {
            $this->actingAs($this->owner)->get(route($route))->assertOk();
        }
        $this->actingAs($this->owner)->getJson(route('reports.sales.summary'))->assertOk()->assertJsonPath('metrics.net_sales', 90000)->assertJsonStructure(['evidence' => ['metric_version', 'timezone']]);
        $this->actingAs($this->owner)->getJson(route('reports.sales.trends', ['grouping' => 'hour']))->assertOk();
    }
}
