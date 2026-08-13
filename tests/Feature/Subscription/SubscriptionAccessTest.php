<?php

namespace Tests\Feature\Subscription;

use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * The gate that turns this into a SaaS: an unpaid tenant must lose access to the
 * app, but must never lose the ability to pay, view their account, or log out.
 */
class SubscriptionAccessTest extends TestCase
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

    /**
     * Replace the fixture's active subscription with one in the given state.
     */
    private function setSubscription(string $status, ?string $endsAt): BusinessSubscription
    {
        BusinessSubscription::where('business_id', $this->business->id)->delete();

        return BusinessSubscription::create([
            'business_id'          => $this->business->id,
            'subscription_plan_id' => SubscriptionPlan::first()->id,
            'starts_at'            => now()->subMonth(),
            'ends_at'              => $endsAt,
            'status'               => $status,
        ]);
    }

    // ── The gate ─────────────────────────────────────────────────────────────

    public function test_an_active_subscription_grants_access_to_the_app(): void
    {
        $this->setUpPos();

        $this->actingAs($this->owner())->get(route('dashboard'))->assertOk();
    }

    public function test_a_trial_subscription_also_grants_access(): void
    {
        $this->setUpPos();
        $this->setSubscription('trial', now()->addDays(7)->toDateString());

        $this->actingAs($this->owner())->get(route('dashboard'))->assertOk();
    }

    public function test_a_subscription_without_an_end_date_never_expires(): void
    {
        $this->setUpPos();
        $this->setSubscription('active', null);

        $this->actingAs($this->owner())->get(route('dashboard'))->assertOk();
    }

    public function test_an_expired_subscription_is_bounced_to_the_upgrade_page(): void
    {
        $this->setUpPos();
        $this->setSubscription('active', now()->subDay()->toDateString());

        $this->actingAs($this->owner())
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.expired'));
    }

    public function test_a_cancelled_subscription_loses_access(): void
    {
        $this->setUpPos();
        $this->setSubscription('cancelled', now()->addYear()->toDateString());

        $this->actingAs($this->owner())
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.expired'));
    }

    public function test_a_pending_payment_does_not_grant_access_yet(): void
    {
        $this->setUpPos();
        $this->setSubscription('pending', now()->addYear()->toDateString());

        $this->actingAs($this->owner())
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.expired'));
    }

    public function test_a_business_with_no_subscription_at_all_is_bounced(): void
    {
        $this->setUpPos();
        BusinessSubscription::where('business_id', $this->business->id)->delete();

        $this->actingAs($this->owner())
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.expired'));
    }

    public function test_the_pos_screen_is_closed_to_an_expired_tenant(): void
    {
        $this->setUpPos();
        $this->setSubscription('active', now()->subDay()->toDateString());

        $this->actingAs($this->cashier)
            ->get(route('pos.index'))
            ->assertRedirect(route('subscription.expired'));
    }

    // ── Escape hatches that must stay open ───────────────────────────────────

    public function test_an_expired_tenant_can_still_reach_the_plans_page(): void
    {
        $this->setUpPos();
        $this->setSubscription('active', now()->subDay()->toDateString());

        $this->actingAs($this->owner())->get(route('saas.plans'))->assertOk();
    }

    public function test_an_expired_tenant_can_still_log_out(): void
    {
        $this->setUpPos();
        $this->setSubscription('active', now()->subDay()->toDateString());

        $this->actingAs($this->owner())->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_expired_page_sends_an_active_tenant_back_to_the_dashboard(): void
    {
        $this->setUpPos();

        $this->actingAs($this->owner())
            ->get(route('subscription.expired'))
            ->assertRedirect(route('dashboard'));
    }

    // ── Tenant boundary ──────────────────────────────────────────────────────

    public function test_one_tenants_subscription_does_not_unlock_another(): void
    {
        $this->setUpPos();
        $expiredOwner = $this->owner();
        $this->setSubscription('active', now()->subDay()->toDateString());

        // A second, fully paid-up tenant exists — that must not help the first.
        $this->setUpPos();

        $this->actingAs($expiredOwner)
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.expired'));
    }

    // ── Module switch ────────────────────────────────────────────────────────

    public function test_the_paywall_stands_down_when_the_subscription_module_is_off(): void
    {
        $this->setUpPos();
        $owner = $this->owner();
        $this->setSubscription('active', now()->subDay()->toDateString());

        // Sanity: the gate is doing its job while the module is on.
        $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('subscription.expired'));

        // With the module off there is no plans page to send anyone to, so an
        // expired tenant must keep working rather than be stranded.
        config(['pos.features.subscription' => false]);

        $this->actingAs($owner->fresh())->get(route('dashboard'))->assertOk();
    }

    // ── Role gate ────────────────────────────────────────────────────────────

    public function test_only_an_owner_can_reach_the_billing_pages(): void
    {
        $this->setUpPos();

        $this->actingAs($this->cashier)->get(route('saas.plans'))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('saas.current'))->assertForbidden();
        $this->actingAs($this->owner())->get(route('saas.current'))->assertOk();
    }

    public function test_a_cashier_cannot_subscribe_the_business_to_a_plan(): void
    {
        $this->setUpPos();
        $plan = SubscriptionPlan::first();

        $this->actingAs($this->cashier)
            ->post(route('saas.subscribe', $plan))
            ->assertForbidden();
    }
}
