<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Outlet>
 */
class OutletFactory extends Factory
{
    protected $model = Outlet::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name'        => fake()->city() . ' Branch',
            'code'        => strtoupper(fake()->unique()->bothify('OUT###')),
            'is_active'   => true,
        ];
    }
}
