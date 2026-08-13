<?php

namespace Tests\Feature\Pos;

use App\Exceptions\PosTransactionException;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * The cashier's device is not trusted. Prices, variants and add-ons all come
 * from the database, so a tampered payload cannot ring up a Rp 100.000 drink
 * for Rp 1 or attach a negative-priced "add-on" as an off-books discount.
 */
class CartIntegrityTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function service(): PosOrderService
    {
        return app(PosOrderService::class);
    }

    // ── Price ────────────────────────────────────────────────────────────────

    public function test_a_price_sent_by_the_client_is_ignored(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 1],
        ]));

        $this->assertEquals(100000, $order->subtotal);
        $this->assertEquals(100000, $order->grand_total);
        $this->assertEquals(100000, $order->items->sole()->price);
    }

    public function test_the_pos_endpoint_charges_the_catalogue_price(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);

        $this->actingAs($this->cashier)->postJson(route('pos.store'), [
            'items'          => [['product_id' => $product->id, 'qty' => 2, 'price' => 1]],
            'payment_method' => 'cash',
            'paid_amount'    => 250000,
        ])
            ->assertOk()
            // The screen is told the real total, not the one it asked for.
            ->assertJson(['grand_total' => 200000, 'change' => 50000]);

        $this->assertEquals(200000, Order::sole()->grand_total);
    }

    public function test_a_price_change_in_the_catalogue_applies_immediately(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);
        $product->update(['price' => 120000]);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ]));

        $this->assertEquals(120000, $order->grand_total);
    }

    // ── Variants ─────────────────────────────────────────────────────────────

    public function test_a_variant_adjustment_is_read_from_the_database(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 20000);
        $large   = $this->variant($product, 'Large', 5000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 2, 'variant_id' => $large->id, 'price' => 1],
        ]));

        $this->assertEquals(50000, $order->subtotal);
        $this->assertSame('Large', $order->items->sole()->variant_name);
    }

    public function test_a_variant_name_sent_by_the_client_cannot_override_the_real_one(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 20000);
        $large   = $this->variant($product, 'Large', 5000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            [
                'product_id'   => $product->id,
                'qty'          => 1,
                'variant_id'   => $large->id,
                'variant_name' => 'Gratis',
            ],
        ]));

        $this->assertSame('Large', $order->items->sole()->variant_name);
    }

    public function test_a_variant_belonging_to_another_product_is_rejected(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 20000);
        $other   = $this->product(price: 50000);
        $foreign = $this->variant($other, 'Large', -19000);

        $this->expectException(PosTransactionException::class);
        $this->expectExceptionMessageMatches('/Varian yang dipilih tidak tersedia/');

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'variant_id' => $foreign->id],
        ]));
    }

    public function test_an_inactive_variant_is_rejected(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 20000);
        $variant = $this->variant($product, 'Large', 5000);
        $variant->update(['is_active' => false]);

        $this->expectException(PosTransactionException::class);

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'variant_id' => $variant->id],
        ]));
    }

    // ── Add-ons ──────────────────────────────────────────────────────────────

    public function test_an_addon_price_is_read_from_the_database(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 20000);
        $shot    = $this->addon($product, 'Extra Shot', 5000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            [
                'product_id' => $product->id,
                'qty'        => 1,
                'addons'     => [['addon_id' => $shot->id, 'name' => 'Gratis', 'price' => -19000]],
            ],
        ]));

        $this->assertEquals(25000, $order->grand_total);

        $saved = $order->items->sole()->addons->sole();
        $this->assertSame('Extra Shot', $saved->addon_name);
        $this->assertEquals(5000, $saved->price);
    }

    public function test_a_free_text_addon_is_rejected(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);

        $this->expectException(PosTransactionException::class);
        $this->expectExceptionMessageMatches('/Add-on .* tidak dikenali/');

        $this->service()->createOrder($this->cashier, $this->payload([
            [
                'product_id' => $product->id,
                'qty'        => 1,
                'addons'     => [['name' => 'Diskon Gelap', 'price' => -90000]],
            ],
        ]));
    }

    public function test_an_addon_belonging_to_another_product_is_rejected(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 20000);
        $other   = $this->product(price: 50000);
        $foreign = $this->addon($other, 'Diskon', -19000);

        $this->expectException(PosTransactionException::class);
        $this->expectExceptionMessageMatches('/Add-on yang dipilih tidak tersedia/');

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'addons' => [['addon_id' => $foreign->id]]],
        ]));
    }

    public function test_a_tampered_cart_leaves_no_order_behind(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);

        try {
            $this->service()->createOrder($this->cashier, $this->payload([
                [
                    'product_id' => $product->id,
                    'qty'        => 1,
                    'addons'     => [['name' => 'Diskon Gelap', 'price' => -90000]],
                ],
            ]));
            $this->fail('Expected the cart to be rejected.');
        } catch (PosTransactionException) {
            // expected
        }

        $this->assertSame(0, Order::count());
    }

    // ── Quantity ─────────────────────────────────────────────────────────────

    public function test_a_negative_quantity_is_rejected(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);

        $this->expectException(PosTransactionException::class);
        $this->expectExceptionMessageMatches('/harus lebih dari 0/');

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => -5],
        ]));
    }

    public function test_a_negative_quantity_cannot_inflate_ingredient_stock(): void
    {
        $this->setUpPos();
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 100);

        try {
            $this->service()->createOrder($this->cashier, $this->payload([
                ['product_id' => $product->id, 'qty' => -5],
            ]));
            $this->fail('Expected the quantity to be rejected.');
        } catch (PosTransactionException) {
            // expected
        }

        $this->assertEquals(100, $ingredient->fresh()->current_stock);
    }

    public function test_a_zero_quantity_is_rejected(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);

        $this->expectException(PosTransactionException::class);

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 0],
        ]));
    }

    public function test_the_endpoint_rejects_a_non_positive_quantity_before_the_service(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);

        $this->actingAs($this->cashier)->postJson(route('pos.store'), [
            'items'          => [['product_id' => $product->id, 'qty' => -5]],
            'payment_method' => 'cash',
            'paid_amount'    => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.qty');

        $this->assertSame(0, Order::count());
    }

    public function test_the_endpoint_rejects_a_product_that_does_not_exist(): void
    {
        $this->setUpPos();

        $this->actingAs($this->cashier)->postJson(route('pos.store'), [
            'items'          => [['product_id' => 999999, 'qty' => 1]],
            'payment_method' => 'cash',
            'paid_amount'    => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
    }

    // ── Held orders ──────────────────────────────────────────────────────────

    public function test_a_held_order_is_priced_from_the_database_too(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);

        $order = $this->service()->holdOrder($this->cashier, [
            'items' => [['product_id' => $product->id, 'qty' => 2, 'price' => 1]],
        ]);

        $this->assertEquals(200000, $order->fresh()->grand_total);
    }

    // ── Tenant boundary on the new lookups ───────────────────────────────────

    public function test_a_variant_of_another_tenants_product_is_rejected(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 20000);

        $foreignProduct = Product::factory()->create(['price' => 999]);
        $foreignVariant = $this->variant($foreignProduct, 'Bocor', -19000);

        $this->expectException(PosTransactionException::class);

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'variant_id' => $foreignVariant->id],
        ]));
    }
}
