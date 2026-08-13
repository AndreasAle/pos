<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\CashierShift;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashierShift>
 */
class CashierShiftFactory extends Factory
{
    protected $model = CashierShift::class;

    public function definition(): array
    {
        return [
            'business_id'  => Business::factory(),
            'outlet_id'    => Outlet::factory(),
            'user_id'      => User::factory(),
            'opening_cash' => 100000,
            'opened_at'    => now(),
            'status'       => 'open',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed', 'closed_at' => now()]);
    }
}
