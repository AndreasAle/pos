<?php

namespace Tests\Feature\Reports;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use App\Services\PosOrderService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * The numbers the owner makes decisions on. A report that quietly counts a
 * voided order, or leaks another tenant's revenue, is worse than no report.
 */
class ReportServiceTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function report(): ReportService
    {
        return app(ReportService::class);
    }

    /**
     * Record a paid sale, optionally backdated.
     */
    private function sell(float $price, float $qty = 1, array $overrides = [], ?string $date = null): Order
    {
        $product = $this->product(price: $price, costPrice: $price * 0.4);

        $order = app(PosOrderService::class)->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => $qty, 'price' => $price],
        ], $overrides));

        if ($date) {
            $order->forceFill(['created_at' => $date . ' 10:00:00'])->saveQuietly();
        }

        return $order->fresh();
    }

    // ── Sales ────────────────────────────────────────────────────────────────

    public function test_sales_summary_totals_revenue_orders_and_average(): void
    {
        $this->setUpPos();
        $this->sell(100000);
        $this->sell(50000);
        $this->sell(30000);

        $data = $this->report()->salesReport($this->business, []);

        $this->assertEquals(180000, $data['summary']['total_revenue']);
        $this->assertSame(3, $data['summary']['total_orders']);
        $this->assertEquals(60000, $data['summary']['avg_order']);
    }

    public function test_an_empty_period_reports_zero_without_dividing_by_zero(): void
    {
        $this->setUpPos();

        $data = $this->report()->salesReport($this->business, [
            'date_from' => '2020-01-01',
            'date_to'   => '2020-01-31',
        ]);

        $this->assertEquals(0, $data['summary']['total_revenue']);
        $this->assertSame(0, $data['summary']['total_orders']);
        $this->assertEquals(0, $data['summary']['avg_order']);
    }

    public function test_voided_and_refunded_orders_are_excluded_from_revenue(): void
    {
        $this->setUpPos();
        $kept     = $this->sell(100000);
        $voided   = $this->sell(50000);
        $refunded = $this->sell(70000);

        $this->actingAs($this->cashier)->post(route('orders.void', $voided), ['reason' => 'Salah']);
        $this->actingAs($this->cashier)->post(route('orders.refund', $refunded), ['reason' => 'Komplain']);

        $data = $this->report()->salesReport($this->business, []);

        $this->assertEquals(100000, $data['summary']['total_revenue']);
        $this->assertSame(1, $data['summary']['total_orders']);
        $this->assertEquals($kept->grand_total, $data['summary']['total_revenue']);
    }

    public function test_draft_and_pending_orders_are_not_counted_as_sales(): void
    {
        $this->setUpPos();
        $this->sell(100000);

        $product = $this->product(price: 90000);
        app(PosOrderService::class)->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 90000],
        ]), paymentPending: true);
        app(PosOrderService::class)->holdOrder($this->cashier, [
            'items' => [['product_id' => $product->id, 'qty' => 1, 'price' => 90000]],
        ]);

        $data = $this->report()->salesReport($this->business, []);

        $this->assertEquals(100000, $data['summary']['total_revenue']);
        $this->assertSame(1, $data['summary']['total_orders']);
    }

    public function test_the_date_range_filter_is_inclusive_on_both_ends(): void
    {
        $this->setUpPos();
        $this->sell(10000, date: '2026-03-01');
        $this->sell(20000, date: '2026-03-15');
        $this->sell(40000, date: '2026-03-31');
        $this->sell(80000, date: '2026-04-01');

        $data = $this->report()->salesReport($this->business, [
            'date_from' => '2026-03-01',
            'date_to'   => '2026-03-31',
        ]);

        $this->assertEquals(70000, $data['summary']['total_revenue']);
        $this->assertSame(3, $data['summary']['total_orders']);
    }

    public function test_sales_can_be_narrowed_to_a_single_outlet(): void
    {
        $this->setUpPos();
        $this->sell(100000);

        $otherOutlet = Outlet::factory()->create(['business_id' => $this->business->id]);
        Order::where('business_id', $this->business->id)->limit(1)->update(['outlet_id' => $otherOutlet->id]);
        $this->sell(60000);

        $data = $this->report()->salesReport($this->business, ['outlet_id' => $this->outlet->id]);

        $this->assertEquals(60000, $data['summary']['total_revenue']);
        $this->assertSame(1, $data['summary']['total_orders']);
    }

    public function test_another_tenants_sales_never_appear_in_the_report(): void
    {
        $this->setUpPos();
        $this->sell(100000);

        $mine = $this->business;
        $this->setUpPos(); // second tenant, overwrites $this->business
        $this->sell(999000);

        $data = $this->report()->salesReport($mine, []);

        $this->assertEquals(100000, $data['summary']['total_revenue']);
    }

    public function test_daily_rows_are_grouped_per_day_and_ordered(): void
    {
        $this->setUpPos();
        $this->sell(10000, date: '2026-03-02');
        $this->sell(20000, date: '2026-03-02');
        $this->sell(50000, date: '2026-03-01');

        $data = $this->report()->salesReport($this->business, [
            'date_from' => '2026-03-01',
            'date_to'   => '2026-03-31',
        ]);

        $this->assertCount(2, $data['daily']);
        $this->assertSame('2026-03-01', (string) $data['daily'][0]->date);
        $this->assertEquals(50000, $data['daily'][0]->revenue);
        $this->assertEquals(30000, $data['daily'][1]->revenue);
        $this->assertEquals(2, $data['daily'][1]->orders);
    }

    public function test_payment_methods_are_broken_down_by_count_and_total(): void
    {
        $this->setUpPos();
        $this->sell(100000, overrides: ['payment_method' => 'cash']);
        $this->sell(50000, overrides: ['payment_method' => 'qris']);
        $this->sell(25000, overrides: ['payment_method' => 'qris']);

        $data     = $this->report()->salesReport($this->business, []);
        $byMethod = $data['paymentBreakdown']->keyBy('payment_method');

        $this->assertEquals(1, $byMethod['cash']->count);
        $this->assertEquals(100000, $byMethod['cash']->total);
        $this->assertEquals(2, $byMethod['qris']->count);
        $this->assertEquals(75000, $byMethod['qris']->total);
    }

    // ── Products ─────────────────────────────────────────────────────────────

    public function test_product_report_aggregates_quantity_revenue_and_cost(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 20000, costPrice: 8000);

        foreach ([2, 3] as $qty) {
            app(PosOrderService::class)->createOrder($this->cashier, $this->payload([
                ['product_id' => $product->id, 'qty' => $qty, 'price' => 20000],
            ]));
        }

        $data = $this->report()->productReport($this->business, []);
        $row  = collect($data['products']->items())->firstWhere('product_name', $product->name);

        $this->assertEquals(5, $row->total_qty);
        $this->assertEquals(100000, $row->total_revenue);
        $this->assertEquals(40000, $row->total_cost);
    }

    public function test_product_report_excludes_another_tenants_items(): void
    {
        $this->setUpPos();
        $mine = $this->business;
        $this->sell(100000);

        $this->setUpPos();
        $this->sell(999000);

        $data = $this->report()->productReport($mine, []);

        $this->assertCount(1, $data['products']->items());
        $this->assertEquals(100000, $data['products']->items()[0]->total_revenue);
    }

    // ── Cashier ──────────────────────────────────────────────────────────────

    public function test_cashier_report_groups_revenue_per_user(): void
    {
        $this->setUpPos();
        $this->sell(100000);
        $this->sell(50000);

        $other = User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'cashier',
            'is_active'   => true,
        ]);
        Order::where('business_id', $this->business->id)->limit(1)->update(['user_id' => $other->id]);

        $data = $this->report()->cashierReport($this->business, []);

        $this->assertCount(2, $data['cashiers']);
        $this->assertEquals(150000, $data['cashiers']->sum('total_revenue'));
        $this->assertEquals(2, $data['cashiers']->sum('total_orders'));
    }

    // ── Shift ────────────────────────────────────────────────────────────────

    public function test_shift_report_lists_shifts_within_the_range_only(): void
    {
        $this->setUpPos();
        $this->shift->forceFill(['opened_at' => '2026-03-10 08:00:00'])->saveQuietly();

        $inRange = $this->report()->shiftReport($this->business, [
            'date_from' => '2026-03-01',
            'date_to'   => '2026-03-31',
        ]);
        $outOfRange = $this->report()->shiftReport($this->business, [
            'date_from' => '2026-04-01',
            'date_to'   => '2026-04-30',
        ]);

        $this->assertSame(1, $inRange['shifts']->total());
        $this->assertSame(0, $outOfRange['shifts']->total());
    }

    // ── Inventory ────────────────────────────────────────────────────────────

    public function test_inventory_report_flags_only_ingredients_below_minimum(): void
    {
        $this->setUpPos();
        Ingredient::factory()->create([
            'business_id'   => $this->business->id,
            'name'          => 'Kopi',
            'current_stock' => 5,
            'minimum_stock' => 100,
        ]);
        Ingredient::factory()->create([
            'business_id'   => $this->business->id,
            'name'          => 'Gula',
            'current_stock' => 500,
            'minimum_stock' => 100,
        ]);

        $data = $this->report()->inventoryReport($this->business, []);

        $this->assertCount(2, $data['ingredients']);
        $this->assertCount(1, $data['lowStock']);
        $this->assertSame('Kopi', $data['lowStock']->first()->name);
    }

    // ── Profit ───────────────────────────────────────────────────────────────

    public function test_profit_report_computes_cogs_profit_and_margin(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 20000, costPrice: 8000);

        app(PosOrderService::class)->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 5, 'price' => 20000],
        ]));

        $data = $this->report()->profitReport($this->business, []);

        $this->assertEquals(100000, $data['totalRevenue']);
        $this->assertEquals(40000, $data['totalCogs']);
        $this->assertEquals(60000, $data['totalProfit']);
        $this->assertEquals(60, $data['margin']);
    }

    public function test_profit_margin_is_zero_rather_than_nan_without_sales(): void
    {
        $this->setUpPos();

        $data = $this->report()->profitReport($this->business, [
            'date_from' => '2020-01-01',
            'date_to'   => '2020-01-31',
        ]);

        $this->assertEquals(0, $data['totalRevenue']);
        $this->assertEquals(0, $data['margin']);
    }

    public function test_profit_report_measures_product_lines_not_the_billed_total(): void
    {
        $this->setUpPos(['enable_tax' => true, 'tax_percent' => 10]);
        $product = $this->product(price: 100000, costPrice: 40000);

        app(PosOrderService::class)->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['manual_discount' => 20000]));

        $sales  = $this->report()->salesReport($this->business, []);
        $profit = $this->report()->profitReport($this->business, []);

        // Sales revenue is what the customer was billed: 100.000 - 20.000 + 8.000 tax.
        $this->assertEquals(88000, $sales['summary']['total_revenue']);

        // Profit revenue is the product line total, before discount and tax.
        // The two figures answer different questions and will not match.
        $this->assertEquals(100000, $profit['totalRevenue']);
        $this->assertEquals(60000, $profit['totalProfit']);
    }

    // ── Default filters ──────────────────────────────────────────────────────

    public function test_filters_default_to_the_current_month_to_date(): void
    {
        $this->setUpPos();

        $f = $this->report()->getFilters([]);

        $this->assertSame(now()->startOfMonth()->format('Y-m-d'), $f['date_from']);
        $this->assertSame(now()->format('Y-m-d'), $f['date_to']);
        $this->assertNull($f['outlet_id']);
    }
}
