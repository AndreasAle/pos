<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * SEC-04: with trustProxies('*'), request()->ip() follows X-Forwarded-For.
 * Lockouts keyed only on IP could be reset by rotating that header; these
 * tests prove the IP-independent caps hold.
 */
class SpoofedIpThrottleTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
    }

    private function spoofed(int $i): array
    {
        return ['REMOTE_ADDR' => '203.0.113.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.'.($i % 250 + 1)];
    }

    public function test_client_ip_follows_forwarded_header_under_current_proxy_config(): void
    {
        Route::get('/__ip', fn () => request()->ip());

        $this->withServerVariables($this->spoofed(7))->get('/__ip')->assertSee('198.51.100.8');
    }

    public function test_rotating_forwarded_ip_does_not_bypass_owner_login_lockout(): void
    {
        $this->setUpPos();
        User::factory()->create([
            'business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'role' => 'owner',
            'email' => 'owner@kafe.test', 'password' => Hash::make('benar-sekali'), 'is_active' => true,
        ]);

        for ($i = 0; $i < 20; $i++) {
            $this->withServerVariables($this->spoofed($i))
                ->post(route('login.post'), ['email' => 'owner@kafe.test', 'password' => 'salah'.$i]);
        }

        $this->withServerVariables($this->spoofed(99))
            ->post(route('login.post'), ['email' => 'owner@kafe.test', 'password' => 'benar-sekali'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_rotating_forwarded_ip_does_not_bypass_pin_lockout(): void
    {
        $this->setUpPos();
        User::factory()->create([
            'business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'role' => 'cashier',
            'is_active' => true, 'pin' => Hash::make('482916'),
        ]);
        $cookie = new \Symfony\Component\HttpFoundation\Cookie('pos_outlet', \App\Http\Controllers\Auth\PinLoginController::pairingToken($this->outlet));

        for ($i = 0; $i < 30; $i++) {
            $this->withUnencryptedCookie('pos_outlet', $cookie->getValue())->withServerVariables($this->spoofed($i))
                ->post(route('pin.login'), ['pin' => str_pad((string) $i, 6, '0', STR_PAD_LEFT)]);
        }

        $this->withUnencryptedCookie('pos_outlet', $cookie->getValue())->withServerVariables($this->spoofed(200))
            ->post(route('pin.login'), ['pin' => '482916'])
            ->assertSessionHasErrors('pin');
        $this->assertGuest();
    }
}
