<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CharyneShopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * A clothing shop runs on the same POS as the cafes, with the F&B parts —
 * dine-in, kitchen display, recipes, raw ingredients — out of its way.
 */
class RetailModeTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function owner(): User
    {
        return User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);
    }

    public function test_a_shop_is_food_and_beverage_unless_set_otherwise(): void
    {
        $this->setUpPos();

        $this->assertFalse($this->business->isRetail());
    }

    public function test_the_retail_register_has_no_dine_in(): void
    {
        $this->setUpPos(['business_type' => 'retail']);

        $this->actingAs($this->cashier)->get(route('pos.index'))
            ->assertOk()
            ->assertDontSee('Dine In')
            ->assertSee('Beli di Toko')
            ->assertSee('Kirim / Online');
    }

    public function test_the_cafe_register_keeps_dine_in(): void
    {
        $this->setUpPos();

        $this->actingAs($this->cashier)->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Dine In');
    }

    public function test_the_retail_sidebar_hides_kitchen_and_recipes(): void
    {
        $this->setUpPos(['business_type' => 'retail']);

        $this->actingAs($this->owner())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Dapur (KDS)')
            ->assertDontSee('Resep')
            ->assertDontSee('Bahan Baku');
    }

    public function test_the_owner_can_switch_the_shop_to_retail(): void
    {
        $this->setUpPos();

        $this->actingAs($this->owner())->post(route('settings.business.update'), [
            'name'          => $this->business->name,
            'business_type' => 'retail',
        ])->assertRedirect();

        $this->assertTrue($this->business->fresh()->isRetail());
    }

    public function test_the_charyne_seeder_builds_the_catalogue_with_sizes(): void
    {
        foreach (['CH_PASSWORD' => 'rahasia-uji', 'CH_PIN_ADMIN' => '135790', 'CH_PIN_KASIR1' => '246800'] as $k => $v) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
        }

        $this->seed(CharyneShopSeeder::class);

        $shop = Business::where('slug', 'charyne-shop')->firstOrFail();
        $this->assertTrue($shop->isRetail());

        $kaos = Product::where('business_id', $shop->id)->where('name', 'Kaos 30s Lengan Pendek')->firstOrFail();
        $this->assertEquals(70000, $kaos->price);
        $this->assertSame(['S', 'M', 'L', 'XL', 'XXL'], $kaos->variants()->orderBy('sort_order')->pluck('name')->all());

        $tote = Product::where('business_id', $shop->id)->where('name', 'Totebag')->firstOrFail();
        $prices = $tote->variants()->orderBy('sort_order')->get()
            ->mapWithKeys(fn ($v) => [$v->name => (float) $tote->price + (float) $v->price_adjustment])->all();
        $this->assertEquals(['S' => 20000, 'M' => 35000, 'L' => 55000], $prices);

        $this->assertSame(24, Product::where('business_id', $shop->id)->count());

        // Running it again changes nothing.
        $this->seed(CharyneShopSeeder::class);
        $this->assertSame(1, Business::where('slug', 'charyne-shop')->count());
    }
}
