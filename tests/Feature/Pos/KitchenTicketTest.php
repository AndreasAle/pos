<?php

namespace Tests\Feature\Pos;

use App\Models\Order;
use App\Models\ProductCategory;
use App\Services\KitchenTicket;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * The kitchen cooks from its ticket. Drinks on it waste the cook's time;
 * a missing dish means a customer who never gets fed.
 */
class KitchenTicketTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function category(string $name, bool $kitchen): ProductCategory
    {
        return ProductCategory::create([
            'business_id'     => $this->business->id,
            'name'            => $name,
            'is_active'       => true,
            'send_to_kitchen' => $kitchen,
        ]);
    }

    private function sell(array $lines): Order
    {
        $items = [];
        foreach ($lines as [$category, $name]) {
            $product = $this->product();
            $product->update(['name' => $name, 'product_category_id' => $category->id]);
            $items[] = ['product_id' => $product->id, 'qty' => 1];
        }

        app(PosOrderService::class)->createOrder($this->cashier, $this->payload($items, [
            'payment_method' => 'cash', 'paid_amount' => 1000000,
        ]));

        return Order::latest('id')->first();
    }

    public function test_only_kitchen_categories_are_on_the_ticket(): void
    {
        $this->setUpPos();
        $food  = $this->category('Makanan', true);
        $drink = $this->category('Minuman', false);

        $order = $this->sell([[$food, 'Nasi Goreng'], [$drink, 'Es Teh']]);

        $names = KitchenTicket::items($order)->pluck('product_name')->all();
        $this->assertSame(['Nasi Goreng'], $names);

        $bytes = app(KitchenTicket::class)->render($order);
        $this->assertStringContainsString('Nasi Goreng', $bytes);
        $this->assertStringNotContainsString('Es Teh', $bytes);
        $this->assertStringNotContainsString('Rp', $bytes);
    }

    public function test_an_order_without_kitchen_items_prints_nothing(): void
    {
        $this->setUpPos();
        $drink = $this->category('Minuman', false);

        $order = $this->sell([[$drink, 'Kopi Susu']]);

        $this->assertNull(app(KitchenTicket::class)->render($order));

        $this->actingAs($this->cashier)
            ->get(route('receipt.kitchen', $order))
            ->assertOk()
            ->assertSee('<title>Dapur', false)
            ->assertDontSee('rawbt:base64,', false);
    }

    public function test_the_kitchen_page_carries_the_payload_for_the_tablet(): void
    {
        $this->setUpPos();
        $food = $this->category('Makanan', true);

        $order = $this->sell([[$food, 'Mie Rebus']]);

        $this->actingAs($this->cashier)
            ->get(route('receipt.kitchen', $order))
            ->assertOk()
            ->assertSee('rawbt:base64,', false)
            ->assertSee('Mie Rebus');
    }

    public function test_the_category_toggle_flips_send_to_kitchen(): void
    {
        $this->setUpPos();
        $food = $this->category('Makanan', false);

        $owner = \App\Models\User::factory()->create([
            'business_id' => $this->business->id,
            'role'        => 'owner',
        ]);

        $this->actingAs($owner)
            ->patch(route('categories.kitchen', $food))
            ->assertRedirect();

        $this->assertTrue($food->fresh()->send_to_kitchen);
    }
}
