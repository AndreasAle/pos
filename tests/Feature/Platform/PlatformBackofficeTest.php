<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Notifications\PlatformTwoFactorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

class PlatformBackofficeTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    private function admin(string $role = 'super_admin'): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'Tim Yoka',
            'email' => $role.'@yokapos.test',
            'password' => 'password-platform-kuat',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function platformSession(PlatformAdmin $admin): static
    {
        return $this->actingAs($admin, 'platform')
            ->withSession(['platform_2fa_passed_at' => now()->timestamp]);
    }

    public function test_store_owner_cannot_open_the_platform_dashboard(): void
    {
        $this->setUpPos();
        $owner = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('platform.dashboard'))
            ->assertRedirect(route('platform.login'));
    }

    public function test_platform_login_requires_the_emailed_two_factor_code(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $code = null;

        $this->post(route('platform.login.store'), [
            'email' => $admin->email,
            'password' => 'password-platform-kuat',
        ])->assertRedirect(route('platform.two-factor'));

        $this->assertAuthenticatedAs($admin, 'platform');
        $this->get(route('platform.dashboard'))->assertRedirect(route('platform.two-factor'));

        Notification::assertSentTo($admin, PlatformTwoFactorCode::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->post(route('platform.two-factor.verify'), ['code' => $code])
            ->assertRedirect(route('platform.dashboard'));

        $this->get(route('platform.dashboard'))->assertOk();
        $this->assertDatabaseHas('platform_activity_logs', [
            'platform_admin_id' => $admin->id,
            'action' => 'login',
        ]);
    }

    public function test_platform_directory_can_see_multiple_tenants(): void
    {
        $admin = $this->admin('support');
        $first = Business::factory()->create(['name' => 'Kopi Utara']);
        $second = Business::factory()->create(['name' => 'Brew Cool']);

        $this->platformSession($admin)
            ->get(route('platform.businesses.index'))
            ->assertOk()
            ->assertSee($first->name)
            ->assertSee($second->name);
    }

    public function test_support_cannot_change_a_tenant_subscription(): void
    {
        $admin = $this->admin('support');
        $business = Business::factory()->create();
        $plan = SubscriptionPlan::create([
            'name' => 'Starter', 'slug' => 'starter', 'price' => 100000,
            'max_outlets' => 1, 'max_users' => 5, 'is_active' => true,
        ]);

        $this->platformSession($admin)
            ->post(route('platform.businesses.subscription', $business), [
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
            ])->assertForbidden();

        $this->assertDatabaseCount('business_subscriptions', 0);
    }

    public function test_super_admin_changes_a_subscription_and_the_action_is_audited(): void
    {
        $admin = $this->admin();
        $business = Business::factory()->create();
        $plan = SubscriptionPlan::create([
            'name' => 'Growth', 'slug' => 'growth', 'price' => 250000,
            'max_outlets' => 3, 'max_users' => 20, 'is_active' => true,
        ]);

        $this->platformSession($admin)
            ->post(route('platform.businesses.subscription', $business), [
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'ends_at' => today()->addMonth()->toDateString(),
            ])->assertRedirect();

        $this->assertDatabaseHas('business_subscriptions', [
            'business_id' => $business->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('platform_activity_logs', [
            'platform_admin_id' => $admin->id,
            'business_id' => $business->id,
            'action' => 'subscription.changed',
        ]);
    }

    public function test_impersonation_uses_the_owner_account_and_is_audited(): void
    {
        $admin = $this->admin('support');
        $business = Business::factory()->create();
        $owner = User::factory()->create([
            'business_id' => $business->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->platformSession($admin)
            ->post(route('platform.impersonate.start', $business), ['reason' => 'Membantu setup kategori produk'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($owner, 'web');
        $this->assertAuthenticatedAs($admin, 'platform');
        $this->assertTrue(session('platform_impersonating'));
        $this->assertDatabaseHas('platform_activity_logs', [
            'business_id' => $business->id,
            'action' => 'impersonation.started',
        ]);
    }

    public function test_owner_cannot_reach_the_global_withdrawal_queue(): void
    {
        $this->setUpPos();

        $this->actingAs($this->cashier)
            ->get('/platform/withdrawals')
            ->assertRedirect(route('platform.login'));
    }

    public function test_only_finance_roles_can_process_the_global_withdrawal_queue(): void
    {
        $support = $this->admin('support');
        $business = Business::factory()->create(['balance' => 500000]);
        $withdrawal = WithdrawalRequest::create([
            'business_id' => $business->id,
            'amount' => 100000,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_name' => 'Pemilik Toko',
            'status' => 'pending',
        ]);

        $this->platformSession($support)
            ->get(route('platform.withdrawals.index'))
            ->assertForbidden();

        auth('platform')->logout();
        $finance = $this->admin('finance');
        $this->platformSession($finance)
            ->patch(route('platform.withdrawals.approve', $withdrawal))
            ->assertRedirect();

        $this->assertDatabaseHas('withdrawal_requests', [
            'id' => $withdrawal->id,
            'status' => 'processing',
        ]);
        $this->assertDatabaseHas('platform_activity_logs', [
            'platform_admin_id' => $finance->id,
            'business_id' => $business->id,
            'action' => 'withdrawal.approved',
        ]);
    }
}
