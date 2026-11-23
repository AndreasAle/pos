<?php

namespace Tests\Feature\Subscription;

use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\PlatformFinance\RevenueRecognition;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/** Daftar harga v1.0: YokaBasic bulanan + bayar di muka 3/6/12 bulan. */
class BillingPeriodTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function owner(): User
    {
        return User::factory()->create(['business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'role' => 'owner', 'is_active' => true]);
    }

    private function basic(): SubscriptionPlan
    {
        $this->seed(SubscriptionPlanSeeder::class);

        return SubscriptionPlan::where('slug', 'starter')->sole();
    }

    private function mockMidtrans(?callable $assertItem = null): void
    {
        $this->mock(MidtransService::class, function ($mock) use ($assertItem) {
            $mock->shouldReceive('isConfigured')->andReturnTrue();
            $mock->shouldReceive('createSnapToken')->andReturnUsing(function ($orderId, $amount, $name, $email, $item = null) use ($assertItem) {
                $assertItem && $assertItem($amount, $item);

                return ['token' => 't', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/t'];
            });
        });
    }

    public function test_yokabasic_has_official_period_prices(): void
    {
        $plan = $this->basic();

        $this->assertSame('YokaBasic', $plan->name);
        $this->assertSame([1 => 149000, 3 => 399000, 6 => 749000, 12 => 1399000], $plan->periods());
        $this->assertNull($plan->priceFor(2));
    }

    public function test_checkout_charges_the_period_price_and_records_the_months(): void
    {
        $this->mockMidtrans(function ($amount, $item) {
            $this->assertSame(1399000, $amount);
            $this->assertSame('Langganan YokaBasic 12 bulan', $item);
        });
        $this->setUpPos();
        $plan = $this->basic();
        $current = $this->business->activeSubscription()->first();
        $base = $current?->ends_at && $current->ends_at->isFuture() ? $current->ends_at->copy() : today();

        $this->actingAs($this->owner())->post(route('saas.checkout'), ['plan_id' => $plan->id, 'months' => 12])->assertRedirect();

        $sub = BusinessSubscription::where('business_id', $this->business->id)->where('status', 'pending')->sole();
        $this->assertSame('1399000.00', $sub->amount);
        $this->assertSame(12, $sub->months);
        $this->assertSame($base->addMonthsNoOverflow(12)->toDateString(), $sub->ends_at->toDateString());
        $this->assertSame(12, RevenueRecognition::termMonths($sub), 'Pendapatan diakui 12 bulan walau perpanjangan dihitung dari akhir masa lama.');
    }

    public function test_one_month_checkout_is_unchanged(): void
    {
        $this->mockMidtrans(fn ($amount) => $this->assertSame(149000, $amount));
        $this->setUpPos();
        $plan = $this->basic();

        $this->actingAs($this->owner())->post(route('saas.checkout'), ['plan_id' => $plan->id])->assertRedirect();

        $sub = BusinessSubscription::where('business_id', $this->business->id)->where('status', 'pending')->sole();
        $this->assertSame('149000.00', $sub->amount);
        $this->assertSame(1, $sub->months);
    }

    public function test_an_unpublished_duration_is_refused(): void
    {
        $this->mockMidtrans(fn () => $this->fail('Midtrans tidak boleh dipanggil.'));
        $this->setUpPos();
        $plan = $this->basic();

        $this->actingAs($this->owner())->post(route('saas.checkout'), ['plan_id' => $plan->id, 'months' => 2])
            ->assertRedirect(route('saas.plans'));

        $this->assertSame(0, BusinessSubscription::where('business_id', $this->business->id)->where('status', 'pending')->count());
    }

    public function test_plans_page_offers_the_durations(): void
    {
        $this->setUpPos();
        $this->basic();

        $this->actingAs($this->owner())->get(route('saas.plans'))->assertOk()
            ->assertSee('1399000')->assertSee('name="months"', false);
    }
}
