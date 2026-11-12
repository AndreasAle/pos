<?php

namespace Tests\Feature\Subscription;

use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Paid plans must remain pending until Midtrans confirms the payment.
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

    public function test_a_free_plan_can_still_be_activated_without_a_payment_gateway(): void
    {
        $this->setUpPos();
        BusinessSubscription::where('business_id', $this->business->id)->delete();
        $plan = $this->plan('free-extra', 0);

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
        $plan  = $this->plan('free-recovery', 0);

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
        $starter = $this->plan('free-newest', 0);

        $this->actingAs($owner)->post(route('saas.subscribe', $starter));

        $current = $this->business->fresh()->activeSubscription()->with('plan')->first();
        $this->assertSame($starter->id, $current->subscription_plan_id);
    }

    public function test_a_business_cannot_subscribe_on_behalf_of_another(): void
    {
        $this->setUpPos();
        $mine = $this->business;
        $plan = $this->plan('free-outsider', 0);

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

    public function test_checkout_creates_a_pending_midtrans_payment(): void
    {
        $this->mock(MidtransService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldReceive('createSnapToken')->once()->andReturn([
                'token' => 'sandbox-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/test-token',
            ]);
        });

        $this->setUpPos();
        $plan = $this->plan('business', 249000);

        $this->actingAs($this->owner())
            ->post(route('saas.checkout'), ['plan_id' => $plan->id])
            ->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/test-token');

        $pending = BusinessSubscription::where('business_id', $this->business->id)
            ->where('status', 'pending')
            ->sole();

        $this->assertSame($plan->id, $pending->subscription_plan_id);
        $this->assertSame('249000.00', $pending->amount);
        $this->assertNotNull($pending->payment_token);
        $this->assertNotNull($pending->payment_url);
    }

    public function test_a_paid_plan_is_never_activated_when_midtrans_is_not_configured(): void
    {
        config(['midtrans.server_key' => '']);
        $this->setUpPos();
        $plan = $this->plan('business', 249000);

        $this->actingAs($this->owner())
            ->post(route('saas.checkout'), ['plan_id' => $plan->id])
            ->assertRedirect(route('saas.plans'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('business_subscriptions', [
            'business_id' => $this->business->id,
            'subscription_plan_id' => $plan->id,
        ]);
    }

    public function test_valid_midtrans_webhook_activates_the_pending_plan(): void
    {
        config(['midtrans.server_key' => 'sandbox-server-key']);
        $this->setUpPos();
        $plan = $this->plan('business', 249000);
        $orderId = 'SUB-'.$this->business->id.'-TEST';

        $pending = BusinessSubscription::create([
            'business_id' => $this->business->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => today(),
            'ends_at' => today()->addDays(30),
            'status' => 'pending',
            'payment_token' => $orderId,
            'amount' => 249000,
        ]);
        $grossAmount = '249000.00';
        $statusCode = '200';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.'sandbox-server-key');

        $this->postJson(route('midtrans.callback'), [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ])->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame('active', $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->paid_at);
        $this->assertSame('expired', BusinessSubscription::where('business_id', $this->business->id)->oldest('id')->first()->status);
    }

    public function test_webhook_with_the_wrong_amount_cannot_activate_a_plan(): void
    {
        config(['midtrans.server_key' => 'sandbox-server-key']);
        $this->setUpPos();
        $plan = $this->plan('business', 249000);
        $orderId = 'SUB-'.$this->business->id.'-MISMATCH';
        $pending = BusinessSubscription::create([
            'business_id' => $this->business->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => today(),
            'ends_at' => today()->addDays(30),
            'status' => 'pending',
            'payment_token' => $orderId,
            'amount' => 249000,
        ]);
        $grossAmount = '99000.00';
        $signature = hash('sha512', $orderId.'200'.$grossAmount.'sandbox-server-key');

        $this->postJson(route('midtrans.callback'), [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
        ])->assertStatus(400);

        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_webhook_is_rejected_when_the_server_key_is_not_configured(): void
    {
        config(['midtrans.server_key' => '']);

        $this->postJson(route('midtrans.callback'), [
            'order_id' => 'SUB-1-FORGED',
            'status_code' => '200',
            'gross_amount' => '99000.00',
            'signature_key' => hash('sha512', 'SUB-1-FORGED20099000.00'),
            'transaction_status' => 'settlement',
        ])->assertStatus(400)->assertJson(['status' => 'invalid signature']);
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
