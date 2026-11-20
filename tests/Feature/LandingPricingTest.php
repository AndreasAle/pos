<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Midtrans onboarding mensyaratkan harga IDR terlihat di website. */
class LandingPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_shows_idr_monthly_prices_from_plans(): void
    {
        SubscriptionPlan::updateOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price' => 99000, 'is_active' => true]);
        SubscriptionPlan::updateOrCreate(['slug' => 'business'], ['name' => 'Business', 'price' => 249000, 'is_active' => true]);
        SubscriptionPlan::updateOrCreate(['slug' => 'enterprise'], ['name' => 'Enterprise', 'price' => 499000, 'is_active' => true]);

        $this->get('/')->assertOk()
            ->assertSee('Rp99.000')->assertSee('Rp249.000')->assertSee('Rp499.000')
            ->assertSee('/ bulan')->assertSee('Harga dalam Rupiah (IDR)');
    }
}
