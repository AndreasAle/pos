<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_shows_the_starting_price_and_links_to_both_pricing_pages(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $this->get('/')->assertOk()
            ->assertSee('Mulai Rp99.000 per bulan')
            ->assertSee('href="'.route('pricing').'"', false)
            ->assertSee('href="'.route('pricing.bundles').'"', false);
    }

    public function test_pricing_page_lists_plans_with_period_prices_from_the_database(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $this->get(route('pricing'))->assertOk()
            ->assertSee('YokaBasic')->assertSee('Business')->assertSee('Custom')
            ->assertSee('950000')->assertSee('1869000')->assertSee('Rp399.000')
            ->assertSee('Coba gratis 7 hari')->assertSee('href="'.route('register').'"', false);
    }

    public function test_pricing_page_follows_price_changes(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);
        SubscriptionPlan::where('slug', 'enterprise')->update(['price' => 450000]);

        $this->get(route('pricing'))->assertOk()->assertSee('Rp450.000');
    }

    public function test_bundles_page_shows_every_bundle_with_contents_and_savings(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $res = $this->get(route('pricing.bundles'))->assertOk();
        foreach (config('yoka_store.bundles') as $b) {
            $res->assertSee($b['name'])->assertSee('Rp'.number_format($b['price'], 0, ',', '.'));
        }
        // Paket Printer: 950.000 (YokaBasic 12 bulan) + 349.000 printer = 1.299.000, hemat 100.000.
        $res->assertSee('Rp1.299.000')->assertSee('hemat Rp100.000')
            ->assertSee('Printer struk 58mm')->assertSee('wa.me', false)
            ->assertDontSee('modal')->assertDontSee('impas');
    }

    public function test_sitemap_lists_the_pricing_pages(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('pricing'))->assertSee(route('pricing.bundles'));
    }
}
