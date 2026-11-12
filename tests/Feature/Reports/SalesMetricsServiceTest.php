<?php

namespace Tests\Feature\Reports;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Reporting\SalesMetricsService;
use App\Services\Reporting\SalesReportFilter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Deterministic money fixtures for the sales metric layer. Every expected value
 * below is computed by hand from the fixture, not from the code under test.
 */
class SalesMetricsServiceTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPos();
        $this->business->update(['timezone' => 'Asia/Jakarta']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'outlet_id' => null, 'role' => 'owner']);
        $this->owner->refresh();
    }

    protected User $owner;

    /** Local (Asia/Jakarta) wall-clock time, stored the way the app stores created_at. */
    private function at(string $local): string
    {
        return CarbonImmutable::parse($local, 'Asia/Jakarta')->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    /**
     * @param  array<int, array{0:string,1:float,2:float,3?:float}>  $items  [name, price, qty, cost]
     * @param  array<int, array{0:string,1:float}>  $payments  split lines; empty = single payment of grand_total
     */
    private function order(array $items, float $discount = 0, float $tax = 0, string $when = '2026-11-10 10:00:00', array $payments = [], array $o = []): Order
    {
        $subtotal = array_sum(array_map(fn ($i) => $i[1] * $i[2], $items));
        $grand = $subtotal - $discount + $tax;
        $split = count($payments) > 1;
        $order = Order::create(array_merge([
            'business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id,
            'order_number' => 'T-'.(++$this->seq), 'subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax,
            'grand_total' => $grand, 'paid_amount' => $split ? array_sum(array_column($payments, 1)) : $grand,
            'change_amount' => 0, 'payment_method' => $split ? $payments[0][0] : 'cash', 'payment_status' => 'paid', 'status' => 'paid',
            'is_split_payment' => $split,
        ], $o));
        Order::whereKey($order->id)->update(['created_at' => $this->at($when)]);
        foreach ($items as $i) {
            OrderItem::create(['order_id' => $order->id, 'product_name' => $i[0], 'price' => $i[1], 'qty' => $i[2], 'subtotal' => $i[1] * $i[2], 'cost_price' => $i[3] ?? 0]);
        }
        if ($split) {
            foreach ($payments as $p) {
                OrderPayment::create(['order_id' => $order->id, 'payment_method' => $p[0], 'amount' => $p[1]]);
            }
        }

        return $order;
    }

    private function filter(array $input = [], ?User $user = null, string $now = '2026-11-12 12:00:00'): SalesReportFilter
    {
        return SalesReportFilter::fromRequest($user ?? $this->owner, array_merge(['date_from' => '2026-11-01', 'date_to' => '2026-11-12'], $input), CarbonImmutable::parse($now, 'Asia/Jakarta'));
    }

    private function svc(): SalesMetricsService
    {
        return app(SalesMetricsService::class);
    }

    public function test_blueprint_fixture_order_a_reconciles_exactly(): void
    {
        // item Rp100.000, discount Rp10.000, tax Rp9.000, cash 40.000 + QRIS 59.000
        $this->order([['Kopi', 50000, 2, 20000]], 10000, 9000, payments: [['cash', 40000], ['qris', 59000]]);

        $s = $this->svc()->summary($this->filter());
        $m = $s['metrics'];

        $this->assertSame(100000.0, $m['gross_item_sales']);
        $this->assertSame(10000.0, $m['discounts']);
        $this->assertSame(90000.0, $m['net_sales']);
        $this->assertSame(9000.0, $m['tax_amount']);
        $this->assertSame(99000.0, $m['payment_collected']);
        $this->assertSame(1, $m['transactions']);
        $this->assertSame(90000.0, $m['aov']);
        $this->assertSame(99000.0, $m['order_grand_total']);
        $byMethod = collect($s['payments'])->keyBy('method');
        $this->assertSame(40000.0, $byMethod['cash']['amount']);
        $this->assertSame(59000.0, $byMethod['qris']['amount']);
        $this->assertSame(60000.0, $m['gross_profit_estimate']); // 100.000 - 2 x 20.000
        $this->assertSame(100.0, $m['cost_coverage_pct']);
        $this->assertSame([], $s['warnings']);
    }

    public function test_net_sales_is_not_grand_total_when_tax_is_charged(): void
    {
        $this->order([['Teh', 20000, 1]], 0, 2200);

        $m = $this->svc()->summary($this->filter())['metrics'];

        $this->assertSame(20000.0, $m['net_sales']);
        $this->assertSame(22200.0, $m['order_grand_total']);
        $this->assertNotEquals($m['net_sales'], $m['order_grand_total']);
    }

    public function test_three_items_and_two_payments_do_not_fan_out(): void
    {
        $this->order([['A', 10000, 1], ['B', 20000, 1], ['C', 30000, 1]], payments: [['cash', 25000], ['qris', 35000]]);

        $s = $this->svc()->summary($this->filter());

        $this->assertSame(60000.0, $s['metrics']['gross_item_sales']);
        $this->assertSame(1, $s['metrics']['transactions']);
        $this->assertSame(60000.0, $s['metrics']['payment_collected']);
        $this->assertSame(3.0, $s['metrics']['quantity_sold']);
        $this->assertSame(60000.0, $this->svc()->trend($this->filter())['points'][9]['gross_item_sales']);
    }

    public function test_change_returned_is_taken_out_of_cash_in_a_split_sale(): void
    {
        // total 100.000; customer hands 80.000 cash + 30.000 QRIS, 10.000 change
        $this->order([['A', 100000, 1]], payments: [['cash', 80000], ['qris', 30000]], o: ['change_amount' => 10000]);

        $p = collect($this->svc()->summary($this->filter())['payments'])->keyBy('method');

        $this->assertSame(70000.0, $p['cash']['amount']);
        $this->assertSame(30000.0, $p['qris']['amount']);
    }

    public function test_non_split_order_counts_once_by_its_payment_method(): void
    {
        $this->order([['A', 40000, 1]], o: ['payment_method' => 'qris']);
        $this->order([['B', 10000, 1]]);

        $p = collect($this->svc()->payments($this->filter())['rows'])->keyBy('method');

        $this->assertSame(40000.0, $p['qris']['amount']);
        $this->assertSame(10000.0, $p['cash']['amount']);
    }

    public function test_refunded_and_cancelled_orders_are_reported_but_not_counted_as_sales(): void
    {
        $this->order([['A', 50000, 1]]);
        $this->order([['B', 30000, 1]], o: ['status' => 'refunded', 'payment_status' => 'refunded']);
        $this->order([['C', 20000, 1]], o: ['status' => 'cancelled']);

        $s = $this->svc()->summary($this->filter());

        $this->assertSame(50000.0, $s['metrics']['net_sales']);
        $this->assertSame(1, $s['metrics']['transactions']);
        $this->assertSame(1, $s['excluded']['refunded']['orders']);
        $this->assertSame(30000.0, $s['excluded']['refunded']['amount']);
        $this->assertSame(1, $s['excluded']['cancelled']['orders']);
    }

    public function test_midnight_boundary_uses_local_business_day(): void
    {
        $this->order([['Late', 10000, 1]], when: '2026-11-10 23:59:30');
        $this->order([['Early', 20000, 1]], when: '2026-11-11 00:00:30');

        $f = $this->filter(['date_from' => '2026-11-10', 'date_to' => '2026-11-10']);
        $m = $this->svc()->summary($f)['metrics'];

        $this->assertSame(10000.0, $m['net_sales']);
        $this->assertSame(1, $m['transactions']);
        $day2 = $this->svc()->summary($this->filter(['date_from' => '2026-11-11', 'date_to' => '2026-11-11']))['metrics'];
        $this->assertSame(20000.0, $day2['net_sales']);
    }

    public function test_hourly_trend_uses_local_hour_not_server_hour(): void
    {
        $this->order([['A', 10000, 1]], when: '2026-11-10 07:15:00');

        $points = collect($this->svc()->trend($this->filter(['grouping' => 'hour']))['points'])->keyBy('key');

        $this->assertSame(1, $points['7']['transactions']);
        $this->assertSame(0, $points['8']['transactions']);
    }

    public function test_running_period_has_no_future_buckets(): void
    {
        $this->order([['A', 10000, 1]], when: '2026-11-10 10:00:00');

        $points = $this->svc()->trend($this->filter(['preset' => 'this_month']))['points'];

        $this->assertSame('2026-11-12', end($points)['key'], 'the chart stops at today, it does not draw zeros for days that have not happened');
        $this->assertCount(12, $points);
    }

    public function test_empty_period_has_no_fabricated_chart_and_aov_is_na(): void
    {
        $s = $this->svc()->summary($this->filter());

        $this->assertSame(0, $s['metrics']['transactions']);
        $this->assertNull($s['metrics']['aov']);
        $this->assertNull($s['metrics']['gross_profit_estimate']);
        $this->assertSame('empty', $s['completeness']);
        $this->assertSame([], $this->svc()->trend($this->filter())['points']);
    }

    public function test_compare_with_zero_baseline_returns_na_not_infinity(): void
    {
        $this->order([['A', 50000, 1]], when: '2026-11-11 10:00:00');

        $f = $this->filter(['comparison' => 'previous', 'date_from' => '2026-11-11', 'date_to' => '2026-11-11']);
        $c = $this->svc()->compare($f);

        $this->assertNull($c['deltas']['net_sales']['pct']);
        $this->assertSame(50000.0, $c['deltas']['net_sales']['abs']);
        $this->assertNull($c['deltas']['aov']['abs'], 'AOV has no baseline');
    }

    public function test_compare_percentage_is_relative_to_prior(): void
    {
        $this->order([['A', 100000, 1]], when: '2026-11-10 10:00:00');
        $this->order([['B', 150000, 1]], when: '2026-11-11 10:00:00');

        $f = $this->filter(['comparison' => 'previous', 'date_from' => '2026-11-11', 'date_to' => '2026-11-11']);

        $this->assertSame(50.0, $this->svc()->compare($f)['deltas']['net_sales']['pct']);
    }

    public function test_other_tenants_orders_never_appear(): void
    {
        $this->order([['Mine', 10000, 1]]);
        $foreign = $this->foreignUser();
        Order::create(['business_id' => $foreign->business_id, 'outlet_id' => $foreign->outlet_id, 'user_id' => $foreign->id, 'order_number' => 'X-1',
            'subtotal' => 999999, 'grand_total' => 999999, 'status' => 'paid', 'payment_status' => 'paid', 'payment_method' => 'cash']);

        $this->assertSame(10000.0, $this->svc()->summary($this->filter())['metrics']['net_sales']);
    }

    public function test_outlet_of_another_tenant_or_unauthorized_is_rejected(): void
    {
        $foreign = $this->foreignUser();

        $this->expectException(ValidationException::class);
        $this->filter(['outlet_ids' => [$foreign->outlet_id]]);
    }

    public function test_outlet_bound_admin_cannot_widen_to_another_outlet(): void
    {
        $other = Outlet::factory()->create(['business_id' => $this->business->id]);
        $admin = User::factory()->create(['business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'role' => 'admin']);
        $admin->refresh();

        $this->assertSame([$this->outlet->id], $this->filter([], $admin)->outletIds);
        $this->expectException(ValidationException::class);
        $this->filter(['outlet_ids' => [$other->id]], $admin);
    }

    public function test_payment_method_filter_limits_orders_and_lines(): void
    {
        $this->order([['A', 40000, 1]], o: ['payment_method' => 'qris']);
        $this->order([['B', 10000, 1]]);

        $m = $this->svc()->summary($this->filter(['payment_methods' => ['qris']]));

        $this->assertSame(1, $m['metrics']['transactions']);
        $this->assertSame(40000.0, $m['metrics']['payment_collected']);
    }

    public function test_cost_coverage_flags_partial_hpp(): void
    {
        $this->order([['Costed', 10000, 1, 4000], ['Uncosted', 10000, 1, 0]]);

        $s = $this->svc()->summary($this->filter());

        $this->assertSame(50.0, $s['metrics']['cost_coverage_pct']);
        $this->assertSame('partial', $s['completeness']);
    }

    public function test_product_mix_allocates_discount_proportionally_and_sums_to_net(): void
    {
        $this->order([['A', 60000, 1, 30000], ['B', 40000, 1, 10000]], discount: 10000);

        $rows = collect($this->svc()->productMix($this->filter())->items())->keyBy('name');

        $this->assertSame(54000.0, $rows['A']['net_sales']);
        $this->assertSame(36000.0, $rows['B']['net_sales']);
        $this->assertSame(90000.0, $rows->sum('net_sales'));
        $this->assertFalse($rows['A']['matched'], 'legacy item without product id is flagged');
    }

    public function test_breakdown_outlet_by_day_and_payment_by_outlet(): void
    {
        $this->order([['A', 10000, 1]], when: '2026-11-10 10:00:00');
        $this->order([['B', 30000, 1]], when: '2026-11-11 10:00:00', o: ['payment_method' => 'qris']);

        $b = $this->svc()->breakdown($this->filter(), 'outlet', 'day', 'net_sales');
        $this->assertSame(40000.0, $b['grand_total']);
        $this->assertSame(10000.0, $b['rows'][0]['cells']['2026-11-10']);

        $p = $this->svc()->breakdown($this->filter(), 'payment_method', 'outlet', 'payment_collected');
        $this->assertSame(40000.0, $p['grand_total']);
    }

    public function test_breakdown_rejects_payment_by_item_attribution(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc()->breakdown($this->filter(), 'payment_method', 'category', 'net_sales');
    }

    public function test_order_list_search_and_detail_are_tenant_scoped(): void
    {
        $mine = $this->order([['A', 10000, 1]]);
        $foreign = $this->foreignUser();
        $theirs = Order::create(['business_id' => $foreign->business_id, 'outlet_id' => $foreign->outlet_id, 'user_id' => $foreign->id, 'order_number' => 'X-2',
            'subtotal' => 5, 'grand_total' => 5, 'status' => 'paid', 'payment_status' => 'paid', 'payment_method' => 'cash']);

        $list = $this->svc()->orderList($this->filter(), $mine->order_number);
        $this->assertSame(1, $list->total());
        $this->assertSame($mine->id, $this->svc()->orderDetail($this->filter(), $mine->id)->id);

        $this->expectException(ModelNotFoundException::class);
        $this->svc()->orderDetail($this->filter(), $theirs->id);
    }
}
