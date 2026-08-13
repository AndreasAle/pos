<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\CustomerPoint;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Voiding and refunding a paid order: the two paths that hand money and stock
 * back. Both must restore inventory, unwind loyalty points, and stay inside
 * the tenant boundary.
 */
class VoidAndRefundTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function service(): PosOrderService
    {
        return app(PosOrderService::class);
    }

    public function test_voiding_an_order_cancels_it_and_returns_the_stock(): void
    {
        $this->setUpPos();
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 1000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 3, 'price' => 20000],
        ]));
        $this->assertEquals(970, $ingredient->fresh()->current_stock);

        $this->actingAs($this->cashier)
            ->post(route('orders.void', $order), ['reason' => 'Salah input'])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('Salah input', $order->cancel_reason);
        $this->assertEquals(1000, $ingredient->fresh()->current_stock);

        $this->assertSame(1, StockMovement::where('order_id', $order->id)->where('type', 'return')->count());
    }

    public function test_voiding_requires_a_reason(): void
    {
        $this->setUpPos();
        $product = $this->product();
        $order   = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 20000],
        ]));

        $this->actingAs($this->cashier)
            ->post(route('orders.void', $order), [])
            ->assertSessionHasErrors('reason');

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_an_already_cancelled_order_cannot_be_voided_twice(): void
    {
        $this->setUpPos();
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 1000);
        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 3, 'price' => 20000],
        ]));

        $this->actingAs($this->cashier)->post(route('orders.void', $order), ['reason' => 'Salah input']);
        $this->actingAs($this->cashier)
            ->post(route('orders.void', $order), ['reason' => 'Lagi'])
            ->assertStatus(422);

        // Stock must not be credited a second time.
        $this->assertEquals(1000, $ingredient->fresh()->current_stock);
    }

    public function test_voiding_reverses_earned_and_redeemed_loyalty_points(): void
    {
        $this->setUpPos(['points_per_rupiah' => 0.001, 'point_value_rupiah' => 100]);
        $product  = $this->product(price: 100000);
        $customer = Customer::factory()->create([
            'business_id'    => $this->business->id,
            'loyalty_points' => 500,
        ]);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ], ['customer_id' => $customer->id, 'redeem_points' => 200]));

        // 500 - 200 redeemed + floor(80.000 * 0.001) = 380
        $this->assertSame(380, $customer->fresh()->loyalty_points);

        $this->actingAs($this->cashier)
            ->post(route('orders.void', $order), ['reason' => 'Batal'])
            ->assertRedirect();

        // Earned points clawed back, redeemed points handed back → original balance.
        $customer->refresh();
        $this->assertSame(500, $customer->loyalty_points);
        $this->assertSame(0, $customer->total_transactions);
        $this->assertEquals(0, $customer->total_spending);

        $this->assertSame(
            $customer->loyalty_points,
            500 + CustomerPoint::where('customer_id', $customer->id)->sum('points'),
            'Point ledger must reconcile with the balance on the customer.'
        );
    }

    public function test_refunding_marks_the_order_refunded_and_returns_the_stock(): void
    {
        $this->setUpPos();
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 1000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 2, 'price' => 20000],
        ]));

        $this->actingAs($this->cashier)
            ->post(route('orders.refund', $order), ['reason' => 'Komplain rasa'])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('refunded', $order->status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('Komplain rasa', $order->refund_reason);
        $this->assertNotNull($order->refunded_at);
        $this->assertEquals(1000, $ingredient->fresh()->current_stock);
    }

    public function test_refunding_a_bundle_order_returns_every_component(): void
    {
        $this->setUpPos();
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 1000);
        $bundle = $this->bundle($product, qty: 2);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['bundle_id' => $bundle->id, 'qty' => 3],
        ]));
        $this->assertEquals(940, $ingredient->fresh()->current_stock);

        $this->actingAs($this->cashier)
            ->post(route('orders.refund', $order), ['reason' => 'Batal pesanan'])
            ->assertRedirect();

        $this->assertEquals(1000, $ingredient->fresh()->current_stock);
    }

    public function test_void_returns_what_was_actually_deducted_even_if_the_recipe_changed_after_the_sale(): void
    {
        $this->setUpPos();
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 1000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 3, 'price' => 20000],
        ]));
        $this->assertEquals(970, $ingredient->fresh()->current_stock);

        // Kitchen reworks the recipe after the sale — the void must still hand
        // back the 30 units the order actually consumed, not 3 x the new figure.
        $product->recipe->items()->update(['qty' => 25]);

        $this->actingAs($this->cashier)
            ->post(route('orders.void', $order), ['reason' => 'Salah input'])
            ->assertRedirect();

        $this->assertEquals(1000, $ingredient->fresh()->current_stock);
    }

    public function test_restoring_twice_never_credits_the_stock_twice(): void
    {
        $this->setUpPos();
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 1000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 3, 'price' => 20000],
        ]));

        $inventory = app(\App\Services\InventoryService::class);
        $inventory->restoreFromOrder($order, $this->cashier);
        $inventory->restoreFromOrder($order, $this->cashier);

        $this->assertEquals(1000, $ingredient->fresh()->current_stock);
        $this->assertSame(1, StockMovement::where('order_id', $order->id)->where('type', 'return')->count());
    }

    public function test_a_cashier_cannot_void_an_order_belonging_to_another_business(): void
    {
        $this->setUpPos();
        $product = $this->product();
        $order   = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 20000],
        ]));

        $outsider = $this->foreignUser();

        $this->actingAs($outsider)
            ->post(route('orders.void', $order), ['reason' => 'Hack'])
            ->assertForbidden();

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_a_cashier_cannot_view_an_order_belonging_to_another_business(): void
    {
        $this->setUpPos();
        $product = $this->product();
        $order   = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 20000],
        ]));

        $outsider = $this->foreignUser();

        $this->actingAs($outsider)
            ->get(route('orders.show', $order))
            ->assertForbidden();
    }
}
