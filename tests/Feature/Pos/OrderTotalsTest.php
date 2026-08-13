<?php

namespace Tests\Feature\Pos;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Promotion;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Money math for a sale: subtotal, add-ons, discount, tax, service, delivery,
 * change, and split payments. These are the numbers printed on the receipt and
 * rolled into every report, so they get asserted to the rupiah.
 */
class OrderTotalsTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function service(): PosOrderService
    {
        return app(PosOrderService::class);
    }

    public function test_it_totals_a_simple_cash_sale_and_returns_change(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 20000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 2, 'price' => 20000],
        ], ['paid_amount' => 50000]));

        $this->assertEquals(40000, $order->subtotal);
        $this->assertEquals(40000, $order->grand_total);
        $this->assertEquals(50000, $order->paid_amount);
        $this->assertEquals(10000, $order->change_amount);
        $this->assertSame('paid', $order->status);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_change_is_never_negative_on_underpayment(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 20000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 2, 'price' => 20000],
        ], ['paid_amount' => 30000]));

        $this->assertEquals(0, $order->change_amount);
    }

    public function test_addons_are_priced_per_unit_not_per_line(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 20000);
        $shot    = $this->addon($product, 'Extra Shot', 5000);
        $oat     = $this->addon($product, 'Oat Milk', 3000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            [
                'product_id' => $product->id,
                'qty'        => 2,
                'addons'     => [
                    ['addon_id' => $shot->id],
                    ['addon_id' => $oat->id],
                ],
            ],
        ]));

        // (20000 + 5000 + 3000) * 2
        $this->assertEquals(56000, $order->subtotal);
        $this->assertCount(2, $order->items->first()->addons);
    }

    public function test_tax_and_service_are_charged_on_the_post_discount_amount(): void
    {
        $this->setUpPos([
            'enable_tax'      => true,
            'tax_percent'     => 10,
            'enable_service'  => true,
            'service_percent' => 5,
        ]);
        $product = $this->product(price: 100000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['manual_discount' => 20000]));

        // after discount 80000 → tax 8000, service 4000
        $this->assertEquals(100000, $order->subtotal);
        $this->assertEquals(20000, $order->discount_amount);
        $this->assertEquals(8000, $order->tax_amount);
        $this->assertEquals(4000, $order->service_amount);
        $this->assertEquals(92000, $order->grand_total);
    }

    public function test_no_tax_is_charged_when_the_setting_is_off(): void
    {
        $this->setUpPos(['enable_tax' => false, 'tax_percent' => 10]);
        $product = $this->product(price: 100000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ]));

        $this->assertEquals(0, $order->tax_amount);
        $this->assertEquals(100000, $order->grand_total);
    }

    public function test_percent_promotion_is_applied_and_linked_to_the_order(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);
        $promo   = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'type'        => 'percent',
            'value'       => 15,
        ]);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['promotion_id' => $promo->id]));

        $this->assertEquals(15000, $order->discount_amount);
        $this->assertEquals(85000, $order->grand_total);
        $this->assertEquals($promo->id, $order->promotion_id);
    }

    public function test_promotion_below_its_minimum_order_gives_no_discount(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 30000);
        $promo   = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'type'        => 'nominal',
            'value'       => 10000,
            'min_order'   => 50000,
        ]);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 30000],
        ], ['promotion_id' => $promo->id]));

        $this->assertEquals(0, $order->discount_amount);
        $this->assertEquals(30000, $order->grand_total);
    }

    public function test_an_inactive_promotion_is_ignored(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);
        $promo   = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'is_active'   => false,
        ]);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['promotion_id' => $promo->id]));

        $this->assertEquals(0, $order->discount_amount);
        $this->assertNull($order->promotion_id);
    }

    public function test_a_promotion_from_another_business_is_ignored(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);
        $foreign = Promotion::factory()->create(['type' => 'nominal', 'value' => 50000]);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['promotion_id' => $foreign->id]));

        $this->assertEquals(0, $order->discount_amount);
        $this->assertNull($order->promotion_id);
    }

    public function test_manual_discount_wins_only_when_larger_than_the_promotion(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);
        $promo   = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'type'        => 'nominal',
            'value'       => 30000,
        ]);

        $smallerManual = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['promotion_id' => $promo->id, 'manual_discount' => 5000]));

        $biggerManual = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['promotion_id' => $promo->id, 'manual_discount' => 45000]));

        $this->assertEquals(30000, $smallerManual->discount_amount);
        $this->assertEquals(45000, $biggerManual->discount_amount);
    }

    public function test_discount_can_never_exceed_the_subtotal(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 10000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 10000],
        ], ['manual_discount' => 999999]));

        $this->assertEquals(10000, $order->discount_amount);
        $this->assertEquals(0, $order->grand_total);
    }

    public function test_delivery_fee_is_added_after_tax_and_is_not_taxed(): void
    {
        $this->setUpPos(['enable_tax' => true, 'tax_percent' => 10]);
        $product = $this->product(price: 100000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], [
            'order_type'        => 'delivery',
            'delivery_platform' => 'gofood',
            'delivery_fee'      => 15000,
        ]));

        $this->assertEquals(10000, $order->tax_amount);
        $this->assertEquals(125000, $order->grand_total);
        $this->assertSame('delivery', $order->order_type);
        $this->assertSame('gofood', $order->delivery_platform);
    }

    public function test_split_payment_sums_the_parts_and_records_each_one(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], [
            'paid_amount'    => 0,
            'split_payments' => [
                ['method' => 'cash', 'amount' => 60000],
                ['method' => 'qris', 'amount' => 40000],
            ],
        ]));

        $this->assertTrue((bool) $order->is_split_payment);
        $this->assertEquals(100000, $order->paid_amount);
        $this->assertEquals(0, $order->change_amount);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame(2, OrderPayment::where('order_id', $order->id)->count());
        $this->assertEquals(100000, OrderPayment::where('order_id', $order->id)->sum('amount'));
    }

    public function test_a_single_entry_in_split_payments_is_not_treated_as_a_split(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], [
            'paid_amount'    => 100000,
            'split_payments' => [['method' => 'cash', 'amount' => 100000]],
        ]));

        $this->assertFalse((bool) $order->is_split_payment);
        $this->assertSame(0, OrderPayment::where('order_id', $order->id)->count());
    }

    public function test_order_is_attached_to_the_cashiers_open_shift(): void
    {
        $this->setUpPos();
        $product = $this->product();

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 20000],
        ]));

        $this->assertSame($this->shift->id, $order->cashier_shift_id);
        $this->assertSame($this->outlet->id, $order->outlet_id);
        $this->assertSame($this->cashier->id, $order->user_id);
    }

    public function test_order_numbers_are_unique_and_sequential_within_a_business(): void
    {
        $this->setUpPos();
        $product = $this->product();

        $numbers = collect(range(1, 3))->map(fn () => $this->service()->createOrder(
            $this->cashier,
            $this->payload([['product_id' => $product->id, 'qty' => 1, 'price' => 20000]])
        )->order_number);

        $this->assertSame(3, $numbers->unique()->count());
        $this->assertSame(
            $numbers->sort()->values()->all(),
            $numbers->values()->all(),
            'Order numbers should increase monotonically.'
        );
    }

    public function test_a_product_from_another_business_cannot_be_sold(): void
    {
        $this->setUpPos();
        $foreign = \App\Models\Product::factory()->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $foreign->id, 'qty' => 1, 'price' => 20000],
        ]));

        $this->assertSame(0, Order::count());
    }
}
