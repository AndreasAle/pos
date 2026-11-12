<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use Database\Seeders\PlatformAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformLoginAndSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (['PLATFORM_ADMIN_EMAIL', 'PLATFORM_ADMIN_PASSWORD', 'PLATFORM_ADMIN_RESET', 'PLATFORM_ADMIN_ROLE'] as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
        parent::tearDown();
    }

    private function env(array $vars): void
    {
        foreach ($vars as $k => $v) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }
    }

    public function test_login_page_renders_the_redesigned_platform_screen(): void
    {
        $this->get(route('platform.login'))->assertOk()
            ->assertSee('Masuk ke backoffice')->assertSee('Ruang kendali', false)->assertSee('noindex', false);
    }

    public function test_seeder_creates_a_super_admin_from_environment_without_hardcoded_secrets(): void
    {
        $this->env(['PLATFORM_ADMIN_EMAIL' => 'Tim@Contoh.test']);
        $this->seed(PlatformAdminSeeder::class);

        $admin = PlatformAdmin::where('email', 'tim@contoh.test')->firstOrFail();
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertGreaterThan(30, strlen($admin->password)); // hashed
    }

    public function test_seeder_does_not_touch_an_existing_account_unless_reset_is_requested(): void
    {
        $this->env(['PLATFORM_ADMIN_EMAIL' => 'tim@contoh.test', 'PLATFORM_ADMIN_PASSWORD' => 'PasswordPertama-123']);
        $this->seed(PlatformAdminSeeder::class);
        $this->env(['PLATFORM_ADMIN_PASSWORD' => 'PasswordKedua-12345']);
        $this->seed(PlatformAdminSeeder::class);
        $this->assertTrue(Hash::check('PasswordPertama-123', PlatformAdmin::first()->password));

        $this->env(['PLATFORM_ADMIN_RESET' => '1']);
        $this->seed(PlatformAdminSeeder::class);
        $this->assertTrue(Hash::check('PasswordKedua-12345', PlatformAdmin::first()->password));
    }

    public function test_seeder_rejects_weak_passwords_and_missing_email(): void
    {
        $this->seed(PlatformAdminSeeder::class);
        $this->assertSame(0, PlatformAdmin::count());

        $this->env(['PLATFORM_ADMIN_EMAIL' => 'tim@contoh.test', 'PLATFORM_ADMIN_PASSWORD' => 'pendek']);
        $this->seed(PlatformAdminSeeder::class);
        $this->assertSame(0, PlatformAdmin::count());
    }
}
