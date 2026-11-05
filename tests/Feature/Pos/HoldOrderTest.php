<?php

namespace Tests\Feature\Pos;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * A held order is a promise to a waiting customer: it must come back as it was
 * rung up, be findable by name and table, and disappear once it is paid.
 */
class HoldOrderTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function hold(array $extra = []): Order
    {
        $product = $this->product(price: 25000);

        $this->actingAs($this->cashier)
            ->postJson(route('pos.hold'), array_merge([
                'items'        => [['product_id' => $product->id, 'qty' => 2, 'notes' => 'Pedas']],
                'hold_name'    => 'Pak Budi',
                'table_number' => '5',
            ], $extra))
            ->assertOk()
            ->assertJson(['success' => true]);

        return Order::where('status', 'draft')->latest('id')->first();
    }

    public function test_a_hold_keeps_name_table_and_item_notes(): void
    {
        $this->setUpPos();
        $draft = $this->hold();

        $this->assertSame('Pak Budi', $draft->hold_name);
        $this->assertSame('5', $draft->table_number);
        $this->assertSame('Pedas', $draft->items->first()->notes);
        $this->assertEquals(50000, $draft->grand_total);
    }

    public function test_a_hold_needs_a_name(): void
    {
        $this->setUpPos();
        $product = $this->product();

        $this->actingAs($this->cashier)
            ->postJson(route('pos.hold'), ['items' => [['product_id' => $product->id, 'qty' => 1]]])
            ->assertStatus(422);
    }

    public function test_the_draft_list_shows_name_and_table(): void
    {
        $this->setUpPos();
        $this->hold();

        $this->actingAs($this->cashier)
            ->getJson(route('pos.drafts'))
            ->assertOk()
            ->assertJsonFragment(['hold_name' => 'Pak Budi', 'table_number' => '5']);
    }

    public function test_a_draft_can_be_deleted(): void
    {
        $this->setUpPos();
        $draft = $this->hold();

        $this->actingAs($this->cashier)
            ->deleteJson(route('pos.drafts.delete', $draft))
            ->assertOk();

        $this->assertModelMissing($draft);
    }

    public function test_a_paid_order_cannot_be_deleted_as_a_draft(): void
    {
        $this->setUpPos();
        $product = $this->product();
        app(\App\Services\PosOrderService::class)->createOrder($this->cashier, $this->payload(
            [['product_id' => $product->id, 'qty' => 1]], ['payment_method' => 'cash', 'paid_amount' => 100000]
        ));
        $paid = Order::where('status', 'paid')->sole();

        $this->actingAs($this->cashier)
            ->deleteJson(route('pos.drafts.delete', $paid))
            ->assertStatus(422);

        $this->assertModelExists($paid);
    }

    public function test_paying_a_resumed_draft_removes_it_and_keeps_the_table(): void
    {
        $this->setUpPos();
        $draft = $this->hold();
        $productId = $draft->items->first()->product_id;

        app(\App\Services\PosOrderService::class)->createOrder($this->cashier, $this->payload(
            [['product_id' => $productId, 'qty' => 2]],
            ['payment_method' => 'cash', 'paid_amount' => 100000, 'draft_id' => $draft->id, 'table_number' => '5']
        ));

        $this->assertModelMissing($draft);
        $this->assertSame('5', Order::where('status', 'paid')->sole()->table_number);
    }
}
