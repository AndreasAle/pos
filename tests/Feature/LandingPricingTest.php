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
        SubscriptionPlan::updateOrCreate(['slug' => 'starter'], ['name' => 'YokaBasic', 'price' => 149000, 'period_prices' => ['12' => 1399000], 'is_active' => true]);
        SubscriptionPlan::updateOrCreate(['slug' => 'business'], ['name' => 'Business', 'price' => 249000, 'is_active' => true]);
        SubscriptionPlan::updateOrCreate(['slug' => 'enterprise'], ['name' => 'Custom', 'price' => 499000, 'is_active' => true]);

        $this->get('/')->assertOk()
            ->assertSee('Rp149.000')->assertSee('YokaBasic')->assertSee('Rp1.399.000')->assertSee('Rp249.000')->assertSee('Rp499.000')
            ->assertSee('/ bulan')->assertSee('Harga dalam Rupiah (IDR)')
            ->assertSee('href="'.route('register').'"', false);
    }
}
