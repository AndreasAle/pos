<?php

namespace Tests\Feature\Intelligence;

use App\Models\Business;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Outlet;
use App\Models\User;
use App\Services\YokaToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class YokaBusinessToolsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $business = Business::factory()->create(['timezone' => 'Asia/Jakarta', 'currency' => 'IDR']);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        return User::factory()->create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'role' => 'owner']);
    }

    private function order(User $user, string $number, array $attributes = []): Order
    {
        return Order::create(array_replace([
            'business_id' => $user->business_id, 'outlet_id' => $user->outlet_id, 'user_id' => $user->id,
            'order_number' => $number, 'status' => 'paid', 'payment_status' => 'paid', 'payment_method' => 'cash',
            'subtotal' => 100000, 'discount_amount' => 0, 'grand_total' => 100000, 'is_split_payment' => false,
        ], $attributes));
    }

    public function test_payment_breakdown_uses_split_payment_rows_without_double_counting(): void
    {
        $owner = $this->owner();
        $this->order($owner, 'SINGLE-CASH');
        $split = $this->order($owner, 'SPLIT', ['subtotal' => 150000, 'grand_total' => 150000, 'is_split_payment' => true]);
        OrderPayment::create(['order_id' => $split->id, 'payment_method' => 'cash', 'amount' => 50000]);
        OrderPayment::create(['order_id' => $split->id, 'payment_method' => 'qris', 'amount' => 100000]);

        $result = app(YokaToolRegistry::class)->execute($owner, 'payment_breakdown', ['period' => 'today']);
        $payments = collect($result['data']['payments'])->keyBy('payment_method');

        $this->assertSame(150000.0, $payments['cash']['amount']);
        $this->assertSame(2, $payments['cash']['transactions']);
        $this->assertSame(100000.0, $payments['qris']['amount']);
        $this->assertSame(250000.0, $result['data']['total']);
        $this->assertStringContainsString('bukan settlement', $result['warnings'][0]);
    }

    public function test_sales_metrics_follow_the_central_definition_and_expose_cogs_coverage(): void
    {
        $owner = $this->owner();
        $order = $this->order($owner, 'METRIC', ['subtotal' => 120000, 'discount_amount' => 20000, 'grand_total' => 111000]);
        OrderItem::create(['order_id' => $order->id, 'product_name' => 'Kopi', 'price' => 60000, 'cost_price' => 20000, 'qty' => 2, 'subtotal' => 120000]);

        $result = app(YokaToolRegistry::class)->execute($owner, 'sales_summary', ['period' => 'today']);

        $this->assertSame(120000.0, $result['data']['gross_item_sales']);
        $this->assertSame(100000.0, $result['data']['net_sales']);
        $this->assertSame(80000.0, $result['data']['estimated_gross_profit']);
        $this->assertSame(100.0, $result['data']['cogs_coverage_percent']);
        $this->assertArrayHasKey('net_sales', $result['metric_definitions']);
    }

    public function test_empty_data_returns_null_aov_and_no_fabricated_series_points(): void
    {
        $owner = $this->owner();
        $summary = app(YokaToolRegistry::class)->execute($owner, 'sales_summary', ['period' => 'today']);
        $series = app(YokaToolRegistry::class)->execute($owner, 'sales_series', ['period' => 'today']);

        $this->assertSame(0, $summary['data']['transactions']);
        $this->assertNull($summary['data']['average_order']);
        $this->assertSame([], $series['data']['points']);
    }

    public function test_requested_outlet_must_belong_to_the_authenticated_business(): void
    {
        $owner = $this->owner();
        $foreign = Outlet::factory()->create(['business_id' => Business::factory()->create()->id]);

        $this->expectException(ValidationException::class);
        app(YokaToolRegistry::class)->execute($owner, 'sales_summary', ['period' => 'today', 'outlet_ids' => [$foreign->id]]);
    }

    public function test_unknown_tool_arguments_are_rejected(): void
    {
        $owner = $this->owner();
        $this->expectException(ValidationException::class);
        app(YokaToolRegistry::class)->execute($owner, 'sales_summary', ['period' => 'today', 'business_id' => 999]);
    }
}
