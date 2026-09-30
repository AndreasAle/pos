<?php

namespace Tests\Feature\Pos;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\User;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * "Beli 1 gratis 1" on drinks, the way a coffee shop runs it: any two drinks
 * from the category, pay for one, the cheaper one is free. Snacks in the same
 * order are paid in full. The server decides; the register only previews.
 */
class BuyGetPromotionTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private ProductCategory $drinks;
    private ProductCategory $snacks;
    private Promotion $promo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPos();

        $this->drinks = ProductCategory::create(['business_id' => $this->business->id, 'name' => 'Minuman', 'slug' => 'minuman']);
        $this->snacks = ProductCategory::create(['business_id' => $this->business->id, 'name' => 'Snack', 'slug' => 'snack']);

        $this->promo = Promotion::factory()->create([
            'business_id'         => $this->business->id,
            'name'                => 'Buy 1 Get 1 Minuman',
            'type'                => 'buy_get',
            'value'               => 0,
            'buy_qty'             => 1,
            'get_qty'             => 1,
            'product_category_id' => $this->drinks->id,
        ]);
    }

    private function item(ProductCategory $cat, float $price): Product
    {
        $p = $this->product(price: $price);
        $p->update(['product_category_id' => $cat->id]);

        return $p;
    }

    private function sell(array $lines, ?int $promoId = null): Order
    {
        return app(PosOrderService::class)->createOrder($this->cashier, $this->payload(
            array_map(fn ($l) => ['product_id' => $l[0]->id, 'qty' => $l[1]], $lines),
            ['promotion_id' => $promoId ?? $this->promo->id]
        ));
    }

    public function test_two_drinks_pay_for_one(): void
    {
        $matcha = $this->item($this->drinks, 25000);

        $order = $this->sell([[$matcha, 2]]);

        $this->assertEquals(25000, $order->discount_amount);
        $this->assertEquals(25000, $order->grand_total);
    }

    public function test_different_drinks_count_together(): void
    {
        $order = $this->sell([
            [$this->item($this->drinks, 25000), 1],
            [$this->item($this->drinks, 25000), 1],
        ]);

        $this->assertEquals(25000, $order->grand_total);
    }

    public function test_an_odd_drink_is_paid_in_full(): void
    {
        $order = $this->sell([[$this->item($this->drinks, 25000), 3]]);

        $this->assertEquals(25000, $order->discount_amount);
        $this->assertEquals(50000, $order->grand_total);
    }

    public function test_four_drinks_pay_for_two(): void
    {
        $order = $this->sell([[$this->item($this->drinks, 25000), 4]]);

        $this->assertEquals(50000, $order->grand_total);
    }

    public function test_the_cheaper_drink_is_the_free_one(): void
    {
        $order = $this->sell([
            [$this->item($this->drinks, 30000), 1],
            [$this->item($this->drinks, 20000), 1],
        ]);

        $this->assertEquals(20000, $order->discount_amount);
        $this->assertEquals(30000, $order->grand_total);
    }

    public function test_items_outside_the_category_are_not_discounted(): void
    {
        $order = $this->sell([
            [$this->item($this->drinks, 25000), 2],
            [$this->item($this->snacks, 10000), 2],
        ]);

        $this->assertEquals(25000, $order->discount_amount);
        $this->assertEquals(45000, $order->grand_total);
    }

    public function test_a_single_drink_gets_nothing(): void
    {
        $order = $this->sell([[$this->item($this->drinks, 25000), 1]]);

        $this->assertEquals(0, $order->discount_amount);
        $this->assertEquals(25000, $order->grand_total);
    }

    public function test_buy_two_get_one(): void
    {
        $this->promo->update(['buy_qty' => 2, 'get_qty' => 1]);

        $order = $this->sell([[$this->item($this->drinks, 25000), 3]]);

        $this->assertEquals(50000, $order->grand_total);
    }

    public function test_without_a_category_every_product_counts(): void
    {
        $this->promo->update(['product_category_id' => null]);

        $order = $this->sell([
            [$this->item($this->drinks, 25000), 1],
            [$this->item($this->snacks, 10000), 1],
        ]);

        $this->assertEquals(10000, $order->discount_amount);
    }

    public function test_an_owner_can_create_a_buy_get_promotion(): void
    {
        $owner = User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);

        $this->actingAs($owner)->post(route('promotions.store'), [
            'name'                => 'B1G1 Minuman',
            'type'                => 'buy_get',
            'buy_qty'             => 1,
            'get_qty'             => 1,
            'product_category_id' => $this->drinks->id,
            'is_active'           => 1,
        ])->assertRedirect(route('promotions.index'));

        $promo = Promotion::where('name', 'B1G1 Minuman')->firstOrFail();
        $this->assertSame('buy_get', $promo->type);
        $this->assertSame($this->drinks->id, $promo->product_category_id);
        $this->assertSame('Beli 1 Gratis 1', $promo->label());
    }

    public function test_a_promotion_cannot_point_at_another_shops_category(): void
    {
        $owner = User::factory()->create([
            'business_id' => $this->business->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);
        $other = ProductCategory::create([
            'business_id' => \App\Models\Business::factory()->create()->id,
            'name'        => 'Asing',
            'slug'        => 'asing',
        ]);

        $this->actingAs($owner)->post(route('promotions.store'), [
            'name'                => 'Nakal',
            'type'                => 'buy_get',
            'buy_qty'             => 1,
            'get_qty'             => 1,
            'product_category_id' => $other->id,
        ])->assertSessionHasErrors('product_category_id');
    }

    public function test_the_promotion_forms_offer_the_new_type(): void
    {
        $owner = User::factory()->create([
            'business_id' => $this->business->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);

        $this->actingAs($owner)->get(route('promotions.create'))
            ->assertOk()->assertSee('Beli X Gratis Y')->assertSee('Minuman');

        $this->actingAs($owner)->get(route('promotions.edit', $this->promo))
            ->assertOk()->assertSee('Beli X Gratis Y');

        $this->actingAs($owner)->get(route('promotions.index'))
            ->assertOk()->assertSee('Beli 1 Gratis 1');
    }
}
