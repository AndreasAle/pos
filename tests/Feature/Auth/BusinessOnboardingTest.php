<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BusinessOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_owner_account_before_creating_a_business(): void
    {
        Notification::fake();

        $this->post(route('register.post'), [
            'name' => 'Putri Azahra',
            'phone' => '081234567890',
            'email' => 'putri@example.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('verification.notice'));

        $owner = User::query()->where('email', 'putri@example.test')->firstOrFail();

        $this->assertNull($owner->business_id);
        $this->assertTrue($owner->requires_business_setup);
        $this->assertSame('owner', $owner->role);
        Notification::assertSentTo($owner, VerifyEmail::class);
    }

    public function test_verified_new_owner_is_sent_to_business_setup_instead_of_dashboard(): void
    {
        $owner = User::factory()->create([
            'business_id' => null,
            'outlet_id' => null,
            'role' => 'owner',
            'is_active' => true,
            'requires_business_setup' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.business'));

        $this->get(route('onboarding.business'))
            ->assertOk()
            ->assertSee('Siapkan profil bisnis');
    }

    public function test_business_setup_creates_tenant_outlet_and_trial_atomically(): void
    {
        SubscriptionPlan::create([
            'name' => 'Gratis',
            'slug' => 'free',
            'price' => 0,
            'max_outlets' => 1,
            'max_users' => 5,
            'is_active' => true,
        ]);

        $owner = User::factory()->create([
            'business_id' => null,
            'outlet_id' => null,
            'role' => 'owner',
            'is_active' => true,
            'requires_business_setup' => true,
        ]);

        $this->actingAs($owner)->post(route('onboarding.business.store'), [
            'business_type' => 'fnb',
            'outlet_scale' => '2-3',
            'city' => 'Bandung',
            'business_name' => 'Brew Cool',
            'outlet_name' => 'Brew Cool Dago',
            'address' => 'Jalan Dago 10',
        ])->assertRedirect(route('dashboard'));

        $owner->refresh();
        $business = Business::query()->findOrFail($owner->business_id);

        $this->assertFalse($owner->requires_business_setup);
        $this->assertSame('Brew Cool', $business->name);
        $this->assertSame('fnb', $business->business_type);
        $this->assertSame('Bandung', $business->city);
        $this->assertNotNull($business->onboarding_completed_at);
        $this->assertNull($business->dashboard_tour_completed_at);
        $this->assertDatabaseHas('outlets', ['business_id' => $business->id, 'name' => 'Brew Cool Dago']);
        $this->assertDatabaseHas('business_subscriptions', ['business_id' => $business->id, 'status' => 'trial']);
    }

    public function test_owner_can_complete_and_restart_the_dashboard_tour(): void
    {
        $business = Business::factory()->create(['dashboard_tour_completed_at' => null]);
        $owner = User::factory()->create([
            'business_id' => $business->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->postJson(route('onboarding.tour.complete'))
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertNotNull($business->fresh()->dashboard_tour_completed_at);

        $this->post(route('onboarding.tour.restart'))
            ->assertRedirect(route('dashboard'));

        $this->assertNull($business->fresh()->dashboard_tour_completed_at);
    }
}
