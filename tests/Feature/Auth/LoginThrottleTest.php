<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Brute-force protection on the login form. Without this, a password list can
 * be ground against a known owner email at full speed.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
    }

    private function owner(): User
    {
        $this->setUpPos();

        return User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'owner',
            'email'       => 'owner@kafe.test',
            'password'    => Hash::make('rahasia-banget'),
            'is_active'   => true,
        ]);
    }

    private function attempt(string $password): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('login.post'), [
            'email'    => 'owner@kafe.test',
            'password' => $password,
        ]);
    }

    public function test_the_sixth_wrong_password_is_locked_out(): void
    {
        $this->owner();

        for ($i = 0; $i < 5; $i++) {
            $this->attempt('salah')->assertSessionHasErrors('email');
        }

        $this->attempt('salah')->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            session('errors')->first('email')
        );
    }

    public function test_the_lockout_blocks_even_the_correct_password(): void
    {
        $this->owner();

        for ($i = 0; $i < 5; $i++) {
            $this->attempt('salah');
        }

        // An attacker who guesses right on attempt six still gets nothing.
        $this->attempt('rahasia-banget');
        $this->assertGuest();
    }

    public function test_a_successful_login_clears_the_counter(): void
    {
        $this->owner();

        $this->attempt('salah');
        $this->attempt('salah');
        $this->attempt('rahasia-banget')->assertRedirect();
        $this->assertAuthenticated();

        auth()->logout();

        // The earlier failures must not count toward a later lockout.
        for ($i = 0; $i < 5; $i++) {
            $this->attempt('salah')->assertSessionHasErrors('email');
        }

        $this->assertStringNotContainsString(
            'Terlalu banyak percobaan',
            session('errors')->first('email')
        );
    }

    public function test_an_inactive_account_cannot_be_probed_without_limit(): void
    {
        $this->setUpPos();
        User::factory()->create([
            'business_id' => $this->business->id,
            'email'       => 'owner@kafe.test',
            'password'    => Hash::make('rahasia-banget'),
            'is_active'   => false,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->attempt('rahasia-banget')->assertSessionHasErrors('email');
        }

        $this->attempt('rahasia-banget');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            session('errors')->first('email')
        );
        $this->assertGuest();
    }
}
