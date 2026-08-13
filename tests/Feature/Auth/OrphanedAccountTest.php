<?php

namespace Tests\Feature\Auth;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Accounts whose business no longer exists.
 *
 * users.business_id is nullOnDelete, so deleting a tenant leaves its staff rows
 * behind without one. That used to redirect to a route the app never defined,
 * producing a 500 — and since /login sends signed-in users to the dashboard,
 * the account was stuck in a loop with no way back out.
 */
class OrphanedAccountTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    public function test_an_account_without_a_business_is_signed_out_rather_than_erroring(): void
    {
        $this->setUpPos();

        $orphan = User::factory()->create([
            'business_id' => null,
            'outlet_id'   => null,
            'role'        => 'owner',
            'is_active'   => true,
        ]);

        $this->actingAs($orphan)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_account_whose_business_was_deleted_is_signed_out(): void
    {
        $this->setUpPos();
        $owner = User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);

        // Mirrors a seeder re-run: the tenant goes, the account stays.
        Business::whereKey($this->business->id)->delete();

        $this->actingAs($owner->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_signed_out_account_is_not_bounced_straight_back(): void
    {
        $this->setUpPos();

        $orphan = User::factory()->create([
            'business_id' => null,
            'role'        => 'owner',
            'is_active'   => true,
        ]);

        $this->actingAs($orphan)->get(route('dashboard'));

        // The login page has to be reachable, otherwise the account is trapped.
        $this->get(route('login'))->assertOk();
    }

    public function test_an_inactive_business_still_reports_403_rather_than_signing_out(): void
    {
        $this->setUpPos();
        $this->business->update(['is_active' => false]);

        $owner = User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertForbidden();

        $this->assertAuthenticated();
    }
}
