<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Audit isolasi antar toko: kasir toko A tidak boleh menautkan transaksi ke pelanggan toko B
 * (atau menambah poin/total belanja pelanggan itu) dengan mengirim customer_id milik toko lain.
 */
class TenantIsolationCustomerTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    private function sale(array $extra = []): array
    {
        $this->setUpPos();
        $product = $this->product(20000);

        return [$product, array_merge([
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment_method' => 'cash', 'paid_amount' => 20000,
        ], $extra)];
    }

    public function test_an_order_cannot_attach_a_customer_from_another_business(): void
    {
        [$product, $payload] = $this->sale();
        $foreign = $this->foreignUser();
        $victim = Customer::factory()->create(['business_id' => $foreign->business_id, 'total_transactions' => 3, 'total_spending' => 100000]);

        $order = app(PosOrderService::class)->createOrder($this->cashier, $payload + ['customer_id' => $victim->id]);

        $this->assertNull($order->fresh()->customer_id);
        $victim->refresh();
        $this->assertSame(3, (int) $victim->total_transactions);
        $this->assertEquals(100000, (float) $victim->total_spending);
    }

    public function test_own_customer_is_still_attached_and_credited(): void
    {
        [$product, $payload] = $this->sale();
        $own = Customer::factory()->create(['business_id' => $this->business->id, 'total_transactions' => 0, 'total_spending' => 0]);

        $order = app(PosOrderService::class)->createOrder($this->cashier, $payload + ['customer_id' => $own->id]);

        $this->assertSame($own->id, $order->fresh()->customer_id);
        $this->assertSame(1, (int) $own->fresh()->total_transactions);
    }

    public function test_a_recipe_cannot_use_an_ingredient_from_another_business(): void
    {
        $this->setUpPos();
        $this->business->update(['is_active' => true]);
        $owner = \App\Models\User::factory()->create(['business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'role' => 'owner', 'is_active' => true]);
        $product = $this->product();
        $foreign = $this->foreignUser();
        $foreignIngredient = \App\Models\Ingredient::factory()->create(['business_id' => $foreign->business_id]);
        $own = \App\Models\Ingredient::factory()->create(['business_id' => $this->business->id]);

        $this->actingAs($owner)->post(route('recipes.update', $product), ['items' => [['ingredient_id' => $foreignIngredient->id, 'qty' => 1]]])->assertSessionHasErrors('items.0.ingredient_id');
        $this->assertSame(0, \App\Models\RecipeItem::where('ingredient_id', $foreignIngredient->id)->count());

        $this->actingAs($owner)->post(route('recipes.update', $product), ['items' => [['ingredient_id' => $own->id, 'qty' => 1]]])->assertSessionHasNoErrors();
    }

    public function test_pos_validation_rejects_foreign_products_and_customers(): void
    {
        $this->setUpPos();
        $foreign = $this->foreignUser();
        $foreignProduct = \App\Models\Product::factory()->create(['business_id' => $foreign->business_id, 'outlet_id' => $foreign->outlet_id]);
        $foreignCustomer = Customer::factory()->create(['business_id' => $foreign->business_id]);
        $mine = $this->product();

        $this->actingAs($this->cashier)->postJson(route('pos.store'), ['items' => [['product_id' => $foreignProduct->id, 'qty' => 1]], 'payment_method' => 'cash', 'paid_amount' => 99999])->assertStatus(422);
        $this->actingAs($this->cashier)->postJson(route('pos.store'), ['items' => [['product_id' => $mine->id, 'qty' => 1]], 'customer_id' => $foreignCustomer->id, 'payment_method' => 'cash', 'paid_amount' => 99999])->assertStatus(422);
    }
}
