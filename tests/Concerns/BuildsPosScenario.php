<?php

namespace Tests\Concerns;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\CashierShift;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\SubscriptionPlan;
use App\Models\User;

/**
 * Shared fixture builder for the POS transaction tests.
 *
 * Gives every test the minimum a real sale needs: a business with its settings,
 * an outlet, a cashier with an open shift, and helpers to build stock-tracked
 * products so inventory deduction can be asserted.
 */
trait BuildsPosScenario
{
    protected Business $business;
    protected Outlet $outlet;
    protected User $cashier;
    protected CashierShift $shift;

    /**
     * @param  array<string, mixed>  $settings  Merged into businesses.settings JSON.
     */
    protected function setUpPos(array $settings = []): void
    {
        $this->business = Business::factory()->settings($settings)->create();
        $this->subscribe($this->business);
        $this->outlet = Outlet::factory()->create(['business_id' => $this->business->id]);

        $this->cashier = User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'cashier',
        ]);

        $this->shift = CashierShift::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'user_id'     => $this->cashier->id,
        ]);

        // The service reads $user->business and $user->activeShift(); make sure the
        // instance we hand it is not carrying stale relations from before the shift existed.
        $this->cashier->refresh();
    }

    /**
     * Give a business an active subscription so the `subscription` middleware
     * lets HTTP requests through instead of bouncing them to the upgrade page.
     */
    protected function subscribe(Business $business): BusinessSubscription
    {
        $plan = SubscriptionPlan::firstOrCreate(
            ['slug' => 'test-plan'],
            ['name' => 'Test Plan', 'price' => 0, 'max_outlets' => 10, 'max_users' => 50]
        );

        return BusinessSubscription::create([
            'business_id'          => $business->id,
            'subscription_plan_id' => $plan->id,
            'starts_at'            => now()->subDay(),
            'ends_at'              => now()->addYear(),
            'status'               => 'active',
        ]);
    }

    /**
     * A user belonging to a different tenant, used to assert the business boundary.
     */
    protected function foreignUser(string $role = 'owner'): User
    {
        $business = Business::factory()->create();
        $this->subscribe($business);

        return User::factory()->create([
            'business_id' => $business->id,
            'outlet_id'   => Outlet::factory()->create(['business_id' => $business->id])->id,
            'role'        => $role,
            'is_active'   => true,
        ]);
    }

    /**
     * A plain product with no recipe — nothing to deduct from inventory.
     */
    protected function product(float $price = 20000, float $costPrice = 8000): Product
    {
        return Product::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'price'       => $price,
            'cost_price'  => $costPrice,
        ]);
    }

    /**
     * A stock-tracked product backed by a one-ingredient recipe.
     *
     * @param  float  $perUnit  Ingredient qty consumed per 1 product sold.
     */
    protected function trackedProduct(float $perUnit = 10, float $stock = 1000, float $price = 20000): array
    {
        $product = Product::factory()->stockTracked()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'price'       => $price,
        ]);

        $ingredient = Ingredient::factory()->create([
            'business_id'   => $this->business->id,
            'outlet_id'     => $this->outlet->id,
            'current_stock' => $stock,
        ]);

        $recipe = Recipe::create([
            'product_id'  => $product->id,
            'business_id' => $this->business->id,
        ]);

        RecipeItem::create([
            'recipe_id'     => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'qty'           => $perUnit,
        ]);

        return [$product->fresh(), $ingredient];
    }

    /**
     * A real add-on row attached to a product. Add-ons must exist in the
     * database — the POS no longer accepts free-text ones.
     */
    protected function addon(Product $product, string $name, float $price): ProductAddon
    {
        return ProductAddon::create([
            'product_id' => $product->id,
            'name'       => $name,
            'price'      => $price,
            'is_active'  => true,
        ]);
    }

    /**
     * A priced variant of a product (price_adjustment is added to the base price).
     */
    protected function variant(Product $product, string $name, float $adjustment): ProductVariant
    {
        return ProductVariant::create([
            'product_id'       => $product->id,
            'name'             => $name,
            'price_adjustment' => $adjustment,
            'is_active'        => true,
        ]);
    }

    /**
     * A bundle containing $qty of $product.
     */
    protected function bundle(Product $product, float $qty = 2, float $price = 35000): ProductBundle
    {
        $bundle = ProductBundle::factory()->create([
            'business_id' => $this->business->id,
            'price'       => $price,
        ]);

        ProductBundleItem::create([
            'product_bundle_id' => $bundle->id,
            'product_id'        => $product->id,
            'qty'               => $qty,
        ]);

        return $bundle->fresh();
    }

    /**
     * Minimal valid payload for PosOrderService::createOrder().
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $items, array $overrides = []): array
    {
        return array_merge([
            'items'          => $items,
            'payment_method' => 'cash',
            'paid_amount'    => 1000000,
        ], $overrides);
    }
}
