<?php

namespace Tests\Feature\Pos;

use App\Exceptions\PosTransactionException;
use App\Models\Order;
use App\Models\StockMovement;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Ingredient stock is deducted through recipes when a sale is recorded, and
 * every movement leaves an auditable stock_movements row.
 */
class InventoryDeductionTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function service(): PosOrderService
    {
        return app(PosOrderService::class);
    }

    public function test_selling_a_tracked_product_deducts_its_recipe_ingredients(): void
    {
        $this->setUpPos();
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 1000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 3, 'price' => 20000],
        ]));

        $this->assertEquals(970, $ingredient->fresh()->current_stock);

        $movement = StockMovement::where('order_id', $order->id)->sole();
        $this->assertSame('sale', $movement->type);
        $this->assertEquals(-30, $movement->qty);
        $this->assertEquals(1000, $movement->stock_before);
        $this->assertEquals(970, $movement->stock_after);
        $this->assertSame($order->order_number, $movement->reference);
    }

    public function test_a_product_without_stock_tracking_leaves_inventory_alone(): void
    {
        $this->setUpPos();
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 1000);
        $product->update(['is_stock_tracked' => false]);

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 3, 'price' => 20000],
        ]));

        $this->assertEquals(1000, $ingredient->fresh()->current_stock);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_selling_a_bundle_deducts_every_component_recipe(): void
    {
        $this->setUpPos();
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 1000);
        $bundle = $this->bundle($product, qty: 2, price: 35000);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['bundle_id' => $bundle->id, 'qty' => 3],
        ]));

        // 3 bundles x 2 products x 10 units of ingredient
        $this->assertEquals(940, $ingredient->fresh()->current_stock);
        $this->assertEquals(105000, $order->subtotal);

        $item = $order->items->sole();
        $this->assertSame($bundle->id, $item->product_bundle_id);
        $this->assertNull($item->product_id);
    }

    public function test_stock_is_allowed_to_go_negative_when_the_setting_is_on(): void
    {
        $this->setUpPos(['allow_negative_stock' => true]);
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 25);

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 4, 'price' => 20000],
        ]));

        $this->assertEquals(-15, $ingredient->fresh()->current_stock);
    }

    public function test_a_sale_is_rejected_when_stock_is_short_and_negatives_are_disallowed(): void
    {
        $this->setUpPos(['allow_negative_stock' => false]);
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 25);

        $this->expectException(PosTransactionException::class);
        $this->expectExceptionMessageMatches('/Stok .* tidak cukup/');

        $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 4, 'price' => 20000],
        ]));
    }

    public function test_a_rejected_sale_leaves_no_order_and_no_stock_change(): void
    {
        $this->setUpPos(['allow_negative_stock' => false]);
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 25);

        try {
            $this->service()->createOrder($this->cashier, $this->payload([
                ['product_id' => $product->id, 'qty' => 4, 'price' => 20000],
            ]));
            $this->fail('Expected the sale to be rejected.');
        } catch (PosTransactionException) {
            // expected
        }

        // The whole transaction must roll back.
        $this->assertSame(0, Order::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertEquals(25, $ingredient->fresh()->current_stock);
    }

    public function test_a_short_component_rejects_the_whole_bundle_sale(): void
    {
        $this->setUpPos(['allow_negative_stock' => false]);
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 30);
        $bundle = $this->bundle($product, qty: 2);

        // 3 bundles x 2 products x 10 = 60 needed, only 30 on hand.
        $this->expectException(PosTransactionException::class);

        $this->service()->createOrder($this->cashier, $this->payload([
            ['bundle_id' => $bundle->id, 'qty' => 3],
        ]));
    }

    public function test_a_sale_that_exactly_empties_the_stock_is_allowed(): void
    {
        $this->setUpPos(['allow_negative_stock' => false]);
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 40);

        $order = $this->service()->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 4, 'price' => 20000],
        ]));

        $this->assertSame('paid', $order->status);
        $this->assertEquals(0, $ingredient->fresh()->current_stock);
    }

    public function test_the_pos_endpoint_reports_short_stock_as_a_422(): void
    {
        $this->setUpPos(['allow_negative_stock' => false]);
        [$product, $ingredient] = $this->trackedProduct(perUnit: 10, stock: 25);

        // Mirrors the POS fetch(): JSON body, no Accept header.
        $response = $this->actingAs($this->cashier)->call(
            'POST',
            route('pos.store'),
            [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'items'          => [['product_id' => $product->id, 'qty' => 4, 'price' => 20000]],
                'payment_method' => 'cash',
                'paid_amount'    => 100000,
            ])
        );

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString('tidak cukup', $response->json('message'));
        $this->assertSame(0, Order::count());
        $this->assertEquals(25, $ingredient->fresh()->current_stock);
    }
}
