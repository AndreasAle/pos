<?php

namespace Tests\Feature\Subscription;

use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_public_pricing_plans_idempotently(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);

        $this->assertDatabaseCount('subscription_plans', 4);
        $this->assertDatabaseHas('subscription_plans', [
            'name' => 'YokaBasic',
            'slug' => 'starter',
            'price' => 99000,
            'max_outlets' => 2,
            'max_users' => 10,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('subscription_plans', [
            'name' => 'Business',
            'slug' => 'business',
            'price' => 199000,
            'max_outlets' => 5,
            'max_users' => 30,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('subscription_plans', [
            'name' => 'Custom',
            'slug' => 'enterprise',
            'price' => 399000,
            'max_outlets' => 999,
            'max_users' => 999,
            'is_active' => true,
        ]);
    }
}
