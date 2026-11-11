<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    public function test_google_login_redirects_to_the_provider(): void
    {
        Socialite::fake('google');

        $this->get(route('auth.google'))
            ->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function test_an_existing_account_can_login_with_a_verified_google_email(): void
    {
        $this->setUpPos();
        $owner = User::factory()->unverified()->create([
            'business_id' => $this->business->id,
            'outlet_id' => $this->outlet->id,
            'role' => 'owner',
            'email' => 'owner@yokapos.test',
            'is_active' => true,
        ]);

        Socialite::fake('google', GoogleUser::fake([
            'id' => 'google-user-123',
            'email' => 'OWNER@yokapos.test',
            'email_verified' => true,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($owner);
        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'google_id' => 'google-user-123',
        ]);
        $this->assertNotNull($owner->fresh()->email_verified_at);
    }

    public function test_an_unknown_google_email_does_not_create_an_incomplete_tenant(): void
    {
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'unknown-google-user',
            'email' => 'unknown@yokapos.test',
            'email_verified' => true,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unknown@yokapos.test']);
    }

    public function test_an_unverified_google_email_is_rejected(): void
    {
        $this->setUpPos();
        User::factory()->create([
            'business_id' => $this->business->id,
            'email' => 'owner@yokapos.test',
            'is_active' => true,
        ]);

        Socialite::fake('google', GoogleUser::fake([
            'id' => 'google-user-123',
            'email' => 'owner@yokapos.test',
            'email_verified' => false,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_an_inactive_yoka_account_cannot_login_with_google(): void
    {
        $this->setUpPos();
        User::factory()->create([
            'business_id' => $this->business->id,
            'email' => 'disabled@yokapos.test',
            'is_active' => false,
        ]);

        Socialite::fake('google', GoogleUser::fake([
            'id' => 'disabled-google-user',
            'email' => 'disabled@yokapos.test',
            'email_verified' => true,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_the_login_page_explains_when_google_is_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('auth.google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');
    }
}
