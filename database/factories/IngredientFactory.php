<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    public function definition(): array
    {
        return [
            'business_id'   => Business::factory(),
            'name'          => fake()->word(),
            'unit'          => 'gram',
            'current_stock' => 1000,
            'minimum_stock' => 100,
            'average_cost'  => 50,
            'is_active'     => true,
        ];
    }
}
