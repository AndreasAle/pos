<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\ProductBundle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBundle>
 */
class ProductBundleFactory extends Factory
{
    protected $model = ProductBundle::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name'        => 'Paket ' . fake()->word(),
            'price'       => 35000,
            'is_active'   => true,
        ];
    }
}
