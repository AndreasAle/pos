<?php

namespace Tests\Feature\Subscription;

use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Taking a plan. With Midtrans unconfigured the app falls back to activating
 * the plan directly — the path every self-hosted install takes by default.
 */
class SubscribeTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function owner(): User
    {
        return User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);
    }

    private function plan(string $slug, float $price): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name'        => ucfirst($slug),
            'slug'        => $slug,
            'price'       => $price,
            'max_outlets' => 5,
            'max_users'   => 30,
            'features'    => ['pos', 'reports'],
            'is_active'   => true,
        ]);
    }

    public function test_subscribing_activates_the_plan_for_thirty_days(): void
    {
        $this->setUpPos();
        BusinessSubscription::where('business_id', $this->business->id)->delete();
        $plan = $this->plan('business', 249000);

        $this->actingAs($this->owner())
            ->post(route('saas.subscribe', $plan))
            ->assertRedirect(route('saas.current'));

        $sub = BusinessSubscription::where('business_id', $this->business->id)->sole();
        $this->assertSame('active', $sub->status);
        $this->assertSame($plan->id, $sub->subscription_plan_id);
        $this->assertSame(today()->toDateString(), $sub->starts_at->toDateString());
        $this->assertSame(today()->addDays(30)->toDateString(), $sub->ends_at->toDateString());
        $this->assertNotNull($sub->paid_at);
    }

    public function test_subscribing_restores_access_for_an_expired_tenant(): void
    {
        $this->setUpPos();
        BusinessSubscription::where('business_id', $this->business->id)->update([
            'status'   => 'active',
            'ends_at'  => now()->subDay()->toDateString(),
        ]);
        $owner = $this->owner();
        $plan  = $this->plan('starter', 99000);

        $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('subscription.expired'));

        $this->actingAs($owner)->post(route('saas.subscribe', $plan))->assertRedirect(route('saas.current'));

        // Re-resolve the user: in a real request the model comes fresh from the
        // session, but actingAs() reuses this instance and its cached relations.
        $this->actingAs($owner->fresh())->get(route('dashboard'))->assertOk();
    }

    public function test_the_newest_subscription_is_the_one_that_counts(): void
    {
        $this->setUpPos();
        $owner   = $this->owner();
        $starter = $this->plan('starter', 99000);

        $this->actingAs($owner)->post(route('saas.subscribe', $starter));

        $current = $this->business->fresh()->activeSubscription()->with('plan')->first();
        $this->assertSame($starter->id, $current->subscription_plan_id);
    }

    public function test_a_business_cannot_subscribe_on_behalf_of_another(): void
    {
        $this->setUpPos();
        $mine = $this->business;
        $plan = $this->plan('business', 249000);

        $outsider = $this->foreignUser('owner');

        $this->actingAs($outsider)->post(route('saas.subscribe', $plan))->assertRedirect();

        // The subscription landed on the outsider's own business, not on mine.
        $this->assertSame(
            1,
            BusinessSubscription::where('business_id', $mine->id)->count(),
            'The original tenant should still have exactly its fixture subscription.'
        );
        $this->assertSame(
            2,
            BusinessSubscription::where('business_id', $outsider->business_id)->count()
        );
    }

    public function test_a_paid_plan_goes_through_midtrans_when_it_is_configured(): void
    {
        config(['midtrans.server_key' => 'SB-Mid-server-dummy']);
        $this->setUpPos();
        $plan = $this->plan('business', 249000);

        $this->actingAs($this->owner())
            ->post(route('saas.subscribe', $plan))
            ->assertRedirect(route('midtrans.subscription', $plan));

        // Nothing is activated until the payment settles.
        $this->assertSame(
            1,
            BusinessSubscription::where('business_id', $this->business->id)->count(),
            'Only the fixture subscription should exist — no free activation.'
        );
    }

    public function test_a_free_plan_is_activated_directly_even_with_midtrans_configured(): void
    {
        config(['midtrans.server_key' => 'SB-Mid-server-dummy']);
        $this->setUpPos();
        BusinessSubscription::where('business_id', $this->business->id)->delete();
        $plan = $this->plan('gratis', 0);

        $this->actingAs($this->owner())
            ->post(route('saas.subscribe', $plan))
            ->assertRedirect(route('saas.current'));

        $this->assertSame('active', BusinessSubscription::where('business_id', $this->business->id)->sole()->status);
    }

    public function test_the_plans_page_lists_active_plans_only(): void
    {
        $this->setUpPos();
        $visible = $this->plan('business', 249000);
        $hidden  = $this->plan('legacy', 10000);
        $hidden->update(['is_active' => false]);

        $response = $this->actingAs($this->owner())->get(route('saas.plans'));

        $response->assertOk();
        $plans = $response->viewData('plans');
        $this->assertTrue($plans->contains('id', $visible->id));
        $this->assertFalse($plans->contains('id', $hidden->id));
    }

    public function test_the_billing_history_page_shows_this_tenants_subscriptions_only(): void
    {
        $this->setUpPos();
        $mine = $this->business;
        $this->setUpPos(); // a second tenant with its own subscription

        $owner = User::factory()->create([
            'business_id' => $mine->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);

        $response = $this->actingAs($owner)->get(route('saas.current'));

        $response->assertOk();
        foreach ($response->viewData('subs') as $sub) {
            $this->assertSame($mine->id, $sub->business_id);
        }
    }
}
