<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name'        => 'Promo ' . fake()->word(),
            'code'        => strtoupper(fake()->unique()->bothify('PROMO###')),
            'type'        => 'percent',
            'value'       => 10,
            'min_order'   => 0,
            'is_active'   => true,
        ];
    }

    public function nominal(float $value): static
    {
        return $this->state(fn () => ['type' => 'nominal', 'value' => $value]);
    }
}
