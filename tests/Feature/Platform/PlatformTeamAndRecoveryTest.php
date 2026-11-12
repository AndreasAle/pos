<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformTeamAndRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'super_admin', string $email = null): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => 'Tim '.$role, 'email' => $email ?? $role.'@yokapos.test', 'password' => 'password-platform-kuat', 'role' => $role, 'is_active' => true]);
    }

    private function as(PlatformAdmin $a): static
    {
        return $this->actingAs($a, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
    }

    public function test_recovery_codes_are_hashed_single_use_and_log_the_admin_in(): void
    {
        $admin = $this->admin();
        $codes = $admin->generateRecoveryCodes();

        $this->assertCount(8, $codes);
        $this->assertStringNotContainsString($codes[0], json_encode($admin->fresh()->getRawOriginal('recovery_codes')));

        $session = ['platform_2fa_passed_at' => 0];
        $this->actingAs($admin, 'platform')->withSession([])->post(route('platform.two-factor.verify'), ['recovery_code' => strtolower($codes[0])])
            ->assertRedirect();
        $this->assertDatabaseHas('platform_activity_logs', ['action' => 'login.recovery']);
        $this->assertCount(7, $admin->fresh()->recovery_codes);

        $this->assertFalse($admin->fresh()->consumeRecoveryCode($codes[0])); // sudah dipakai
        $this->assertFalse($admin->fresh()->consumeRecoveryCode('SALAH-SALAH'));
        $this->assertTrue($admin->fresh()->consumeRecoveryCode($codes[1]));
    }

    public function test_wrong_recovery_code_is_rejected_and_counts_toward_lockout(): void
    {
        $admin = $this->admin();
        $admin->generateRecoveryCodes();
        $this->actingAs($admin, 'platform')->post(route('platform.two-factor.verify'), ['recovery_code' => 'ABCDE-FGHIJ'])->assertSessionHasErrors('recovery_code');
        $this->assertCount(8, $admin->fresh()->recovery_codes);
    }

    public function test_changing_password_requires_the_current_password_and_a_strong_new_one(): void
    {
        $admin = $this->admin();
        $this->as($admin)->post(route('platform.account.password'), ['current_password' => 'salah', 'password' => 'PasswordBaru12345', 'password_confirmation' => 'PasswordBaru12345'])->assertSessionHasErrors('current_password');
        $this->as($admin)->post(route('platform.account.password'), ['current_password' => 'password-platform-kuat', 'password' => 'pendek1', 'password_confirmation' => 'pendek1'])->assertSessionHasErrors('password');
        $this->as($admin)->post(route('platform.account.password'), ['current_password' => 'password-platform-kuat', 'password' => 'PasswordBaru12345', 'password_confirmation' => 'PasswordBaru12345'])->assertSessionHas('success');

        $this->assertTrue(Hash::check('PasswordBaru12345', $admin->fresh()->password));
        $this->assertDatabaseHas('platform_activity_logs', ['action' => 'account.password_changed']);
    }

    public function test_recovery_codes_can_be_regenerated_only_with_the_password(): void
    {
        $admin = $this->admin();
        $this->as($admin)->post(route('platform.account.recovery'), ['current_password' => 'salah'])->assertSessionHasErrors('current_password');
        $this->assertEmpty($admin->fresh()->recovery_codes);

        $this->as($admin)->post(route('platform.account.recovery'), ['current_password' => 'password-platform-kuat'])->assertSessionHas('new_recovery_codes');
        $this->assertCount(8, $admin->fresh()->recovery_codes);
    }

    public function test_super_admin_manages_the_team_and_support_cannot(): void
    {
        $super = $this->admin();
        $this->as($super)->get(route('platform.team'))->assertOk()->assertSee('Tambah anggota tim');
        $this->as($this->admin('support'))->get(route('platform.team'))->assertForbidden();

        $this->as($super)->post(route('platform.team.store'), ['name' => 'Rina', 'email' => 'Rina@Yokapos.test', 'role' => 'finance'])
            ->assertSessionHas('temp_password');
        $rina = PlatformAdmin::where('email', 'rina@yokapos.test')->firstOrFail();
        $this->assertSame('finance', $rina->role);
        $this->assertDatabaseHas('platform_activity_logs', ['action' => 'team.admin_created']);

        $this->as($super)->patch(route('platform.team.toggle', $rina))->assertSessionHas('success');
        $this->assertFalse($rina->fresh()->is_active);
        $this->as($this->admin('finance'))->patch(route('platform.team.toggle', $rina))->assertForbidden();
    }

    public function test_cannot_disable_or_demote_yourself_or_the_last_super_admin(): void
    {
        $super = $this->admin();
        $this->as($super)->patch(route('platform.team.toggle', $super))->assertSessionHasErrors('team');
        $this->as($super)->patch(route('platform.team.role', $super), ['role' => 'support'])->assertSessionHasErrors('team');

        $other = $this->admin('super_admin', 'dua@yokapos.test');
        $this->as($super)->patch(route('platform.team.role', $other), ['role' => 'support'])->assertSessionHas('success'); // masih ada $super
        $this->assertSame('support', $other->fresh()->role);

        $this->as($super)->patch(route('platform.team.toggle', $other->fresh()))->assertSessionHas('success'); // support boleh dinonaktifkan
        $this->assertSame(1, PlatformAdmin::where('role', 'super_admin')->where('is_active', true)->count());
    }

    public function test_two_factor_page_offers_recovery_codes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'platform')->get(route('platform.two-factor'))->assertOk()->assertSee('kode pemulihan', false);
    }
}
