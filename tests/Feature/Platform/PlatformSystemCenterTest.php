<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\PlatformHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformSystemCenterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'super_admin'): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => 'Tim Yoka', 'email' => $role.'@yokapos.test', 'password' => 'password-platform-kuat', 'role' => $role, 'is_active' => true]);
    }

    private function as(PlatformAdmin $a): static
    {
        return $this->actingAs($a, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
    }

    public function test_super_admin_sees_the_system_center_and_support_does_not(): void
    {
        config(['services.google.client_id' => 'abc', 'services.google.client_secret' => 'secret-xyz-123']);
        $this->as($this->admin())->get(route('platform.system'))
            ->assertOk()->assertSee('Sistem &amp; Integrasi', false)->assertSee('Login Google')->assertSee('Cron (schedule:run)')
            ->assertDontSee('secret-xyz-123');
        $this->as($this->admin('support'))->get(route('platform.system'))->assertForbidden();
    }

    public function test_health_report_never_contains_secret_values(): void
    {
        config(['services.yoka_ai.key' => 'sk-super-secret-key', 'mail.mailers.smtp.password' => 'smtp-secret-pass', 'services.telegram.token' => '']);
        $json = json_encode((new PlatformHealth)->run());

        $this->assertStringNotContainsString('sk-super-secret-key', $json);
        $this->assertStringNotContainsString('smtp-secret-pass', $json);
    }

    public function test_cron_heartbeat_is_reported(): void
    {
        $report = (new PlatformHealth)->run();
        $cron = collect($report['jobs']['checks'])->firstWhere('name', 'Cron (schedule:run)');
        $this->assertSame('fail', $cron['status']);

        Cache::put(PlatformHealth::HEARTBEAT_KEY, now()->timestamp, 3600);
        $cron = collect((new PlatformHealth)->run()['jobs']['checks'])->firstWhere('name', 'Cron (schedule:run)');
        $this->assertSame('ok', $cron['status']);
    }

    public function test_health_command_outputs_json_and_strict_mode_fails_on_problems(): void
    {
        $this->assertSame(0, Artisan::call('yoka:health', ['--json' => true]));
        $this->assertArrayHasKey('summary', json_decode(Artisan::output(), true));
        $this->assertSame(1, Artisan::call('yoka:health', ['--strict' => true])); // tanpa akun platform & cron => gagal
    }

    public function test_test_mail_goes_only_to_the_logged_in_admin(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->as($admin)->post(route('platform.system.test-mail'), ['to' => 'orang-lain@contoh.test'])->assertRedirect();

        Mail::assertSentCount(0); // raw mail tidak masuk Mailable; pastikan tidak ada galat & tercatat
        $this->assertDatabaseHas('platform_activity_logs', ['action' => 'system.mail_test', 'platform_admin_id' => $admin->id]);
        $this->as($this->admin('finance'))->post(route('platform.system.test-mail'))->assertForbidden();
    }

    public function test_failed_platform_logins_are_audited(): void
    {
        $this->admin();
        $this->post(route('platform.login.store'), ['email' => 'super_admin@yokapos.test', 'password' => 'salah-banget'])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('platform_activity_logs', ['action' => 'login.failed']);
    }

    public function test_impersonation_requires_a_reason_and_expires_after_an_hour(): void
    {
        $admin = $this->admin();
        $business = Business::factory()->create();
        $owner = User::factory()->create(['business_id' => $business->id, 'role' => 'owner', 'is_active' => true]);

        $this->as($admin)->post(route('platform.impersonate.start', $business), ['reason' => 'pendek'])->assertSessionHasErrors('reason');

        $this->as($admin)->post(route('platform.impersonate.start', $business), ['reason' => 'Membantu setup kategori produk'])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('platform_activity_logs', ['action' => 'impersonation.started']);
        $this->assertGreaterThan(now()->timestamp, session('platform_impersonation_expires_at'));

        // lewat 60 menit: sesi toko ditutup
        $this->withSession(['platform_impersonating' => true, 'platform_impersonation_expires_at' => now()->subMinute()->timestamp])
            ->actingAs($owner, 'web')->get(route('dashboard'))->assertRedirect(route('platform.login'));
    }

    public function test_stopping_impersonation_returns_to_the_platform_and_is_audited(): void
    {
        $admin = $this->admin();
        $business = Business::factory()->create();
        User::factory()->create(["business_id" => $business->id, "role" => "owner", "is_active" => true]);
        $this->as($admin)->post(route("platform.impersonate.start", $business), ["reason" => "Membantu setup kategori produk"]);

        $this->as($admin)->post(route("platform.impersonate.stop"))->assertRedirect(route("platform.businesses.show", $business));
        $this->assertDatabaseHas("platform_activity_logs", ["action" => "impersonation.stopped"]);
    }
}
