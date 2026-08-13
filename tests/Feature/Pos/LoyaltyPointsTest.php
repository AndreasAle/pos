<?php

namespace Tests\Feature\Pos;

use App\Exceptions\PosTransactionException;
use App\Models\Customer;
use App\Models\CustomerPoint;
use App\Models\Order;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Loyalty points are customer-facing currency: earning, redeeming, and the
 * ledger rows that have to reconcile with the balance on the customer record.
 */
class LoyaltyPointsTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function service(): PosOrderService
    {
        return app(PosOrderService::class);
    }

    private function customer(int $points = 0): Customer
    {
        return Customer::factory()->create([
            'business_id'    => $this->business->id,
            'loyalty_points' => $points,
        ]);
    }

    public function test_customer_earns_points_and_gets_a_ledger_row(): void
    {
        $this->setUpPos(['points_per_rupiah' => 0.001]); // 1 point per Rp 1.000
        $product  = $this->product(price: 100000);
        $customer = $this->customer();

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['customer_id' => $customer->id]));

        $this->assertSame(100, $customer->fresh()->loyalty_points);

        $entry = CustomerPoint::where('order_id', $order->id)->sole();
        $this->assertSame('earn', $entry->type);
        $this->assertSame(100, $entry->points);
    }

    public function test_no_points_are_earned_when_the_rate_is_not_configured(): void
    {
        $this->setUpPos();
        $product  = $this->product(price: 100000);
        $customer = $this->customer();

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['customer_id' => $customer->id]));

        $this->assertSame(0, $customer->fresh()->loyalty_points);
        $this->assertSame(0, CustomerPoint::count());
    }

    public function test_customer_spending_and_transaction_counters_are_updated(): void
    {
        $this->setUpPos();
        $product  = $this->product(price: 100000);
        $customer = $this->customer();

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['customer_id' => $customer->id]));

        $customer->refresh();
        $this->assertSame(1, $customer->total_transactions);
        $this->assertEquals(100000, $customer->total_spending);
    }

    public function test_redeeming_points_discounts_the_bill_and_deducts_the_balance(): void
    {
        $this->setUpPos(['point_value_rupiah' => 100]);
        $product  = $this->product(price: 100000);
        $customer = $this->customer(points: 500);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['customer_id' => $customer->id, 'redeem_points' => 200]));

        // 200 points x Rp 100
        $this->assertEquals(20000, $order->discount_amount);
        $this->assertEquals(80000, $order->grand_total);
        $this->assertSame(300, $customer->fresh()->loyalty_points);

        $entry = CustomerPoint::where('order_id', $order->id)->where('type', 'redeem')->sole();
        $this->assertSame(-200, $entry->points);
    }

    public function test_redeeming_stacks_on_top_of_a_manual_discount(): void
    {
        $this->setUpPos(['point_value_rupiah' => 100]);
        $product  = $this->product(price: 100000);
        $customer = $this->customer(points: 500);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], [
            'customer_id'     => $customer->id,
            'redeem_points'   => 200,
            'manual_discount' => 10000,
        ]));

        // manual 10.000 + points 20.000
        $this->assertEquals(30000, $order->discount_amount);
        $this->assertEquals(70000, $order->grand_total);
    }

    public function test_redeeming_more_points_than_the_customer_owns_is_rejected(): void
    {
        $this->setUpPos(['point_value_rupiah' => 100]);
        $product  = $this->product(price: 100000);
        $customer = $this->customer(points: 50);

        $this->expectException(PosTransactionException::class);
        $this->expectExceptionMessageMatches('/Poin tidak cukup/');

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['customer_id' => $customer->id, 'redeem_points' => 500]));
    }

    public function test_an_over_redeem_leaves_no_order_and_no_point_movement(): void
    {
        $this->setUpPos(['point_value_rupiah' => 100]);
        $product  = $this->product(price: 100000);
        $customer = $this->customer(points: 50);

        try {
            $this->service()->createOrder($this->cashier, $this->payload([
                ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
            ], ['customer_id' => $customer->id, 'redeem_points' => 500]));
            $this->fail('Expected the redeem to be rejected.');
        } catch (PosTransactionException) {
            // expected
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(50, $customer->fresh()->loyalty_points);
        $this->assertSame(0, CustomerPoint::count());
    }

    public function test_redeeming_the_entire_balance_is_allowed(): void
    {
        $this->setUpPos(['point_value_rupiah' => 100]);
        $product  = $this->product(price: 100000);
        $customer = $this->customer(points: 200);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['customer_id' => $customer->id, 'redeem_points' => 200]));

        $this->assertEquals(20000, $order->discount_amount);
        $this->assertSame(0, $customer->fresh()->loyalty_points);
    }

    public function test_only_the_points_the_bill_can_absorb_are_spent(): void
    {
        $this->setUpPos(['point_value_rupiah' => 100]);
        $product  = $this->product(price: 10000);
        $customer = $this->customer(points: 500);

        // 500 points would be worth Rp 50.000 against a Rp 10.000 bill.
        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 10000],
        ], ['customer_id' => $customer->id, 'redeem_points' => 500]));

        $this->assertEquals(10000, $order->discount_amount);
        $this->assertEquals(0, $order->grand_total);

        // Only 100 points were needed; the other 400 stay on the balance.
        $this->assertSame(400, $customer->fresh()->loyalty_points);
        $this->assertSame(-100, CustomerPoint::where('order_id', $order->id)->where('type', 'redeem')->sole()->points);
    }

    public function test_points_only_cover_what_is_left_after_a_manual_discount(): void
    {
        $this->setUpPos(['point_value_rupiah' => 100]);
        $product  = $this->product(price: 50000);
        $customer = $this->customer(points: 500);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 50000],
        ], [
            'customer_id'     => $customer->id,
            'redeem_points'   => 500,
            'manual_discount' => 30000,
        ]));

        // Rp 20.000 left to cover → 200 points.
        $this->assertEquals(50000, $order->discount_amount);
        $this->assertEquals(0, $order->grand_total);
        $this->assertSame(300, $customer->fresh()->loyalty_points);
    }

    public function test_no_points_are_spent_when_the_discount_already_covers_the_bill(): void
    {
        $this->setUpPos(['point_value_rupiah' => 100]);
        $product  = $this->product(price: 50000);
        $customer = $this->customer(points: 500);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 50000],
        ], [
            'customer_id'     => $customer->id,
            'redeem_points'   => 500,
            'manual_discount' => 50000,
        ]));

        $this->assertEquals(50000, $order->discount_amount);
        $this->assertSame(500, $customer->fresh()->loyalty_points);
        $this->assertSame(0, CustomerPoint::where('type', 'redeem')->count());
    }

    public function test_partial_points_are_not_spent_when_they_cannot_be_used_whole(): void
    {
        $this->setUpPos(['point_value_rupiah' => 1000]);
        $product  = $this->product(price: 2500);
        $customer = $this->customer(points: 10);

        // Rp 2.500 bill, each point worth Rp 1.000 → only 2 whole points fit.
        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 2500],
        ], ['customer_id' => $customer->id, 'redeem_points' => 10]));

        $this->assertEquals(2000, $order->discount_amount);
        $this->assertEquals(500, $order->grand_total);
        $this->assertSame(8, $customer->fresh()->loyalty_points);
    }

    public function test_the_pos_response_reports_the_points_actually_spent(): void
    {
        $this->setUpPos(['point_value_rupiah' => 100]);
        $product  = $this->product(price: 10000);
        $customer = $this->customer(points: 500);

        $response = $this->actingAs($this->cashier)->postJson(route('pos.store'), [
            'items'          => [['product_id' => $product->id, 'qty' => 1, 'price' => 10000]],
            'customer_id'    => $customer->id,
            'redeem_points'  => 500,
            'payment_method' => 'cash',
            'paid_amount'    => 0,
        ]);

        $response->assertOk()->assertJson(['success' => true, 'points_redeemed' => 100]);
    }

    public function test_points_are_ignored_when_no_customer_is_attached(): void
    {
        $this->setUpPos(['point_value_rupiah' => 100]);
        $product = $this->product(price: 100000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['redeem_points' => 200]));

        $this->assertEquals(0, $order->discount_amount);
        $this->assertEquals(100000, $order->grand_total);
    }

    public function test_a_pending_payment_does_not_touch_loyalty_or_spending_yet(): void
    {
        $this->setUpPos(['points_per_rupiah' => 0.001]);
        $product  = $this->product(price: 100000);
        $customer = $this->customer();

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['customer_id' => $customer->id]), paymentPending: true);

        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);

        $customer->refresh();
        $this->assertSame(0, $customer->loyalty_points);
        $this->assertSame(0, $customer->total_transactions);
        $this->assertSame(0, CustomerPoint::count());
    }
}
