<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'business_id'        => Business::factory(),
            'name'               => fake()->name(),
            'phone'              => fake()->numerify('08##########'),
            'total_transactions' => 0,
            'total_spending'     => 0,
            'loyalty_points'     => 0,
            'is_active'          => true,
        ];
    }
}
