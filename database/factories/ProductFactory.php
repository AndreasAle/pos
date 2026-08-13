<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'business_id'      => Business::factory(),
            'name'             => fake()->words(2, true),
            'sku'              => strtoupper(fake()->unique()->bothify('SKU####')),
            'price'            => 20000,
            'cost_price'       => 8000,
            'is_active'        => true,
            'is_stock_tracked' => false,
        ];
    }

    public function stockTracked(): static
    {
        return $this->state(fn () => ['is_stock_tracked' => true]);
    }
}
