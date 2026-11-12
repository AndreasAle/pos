<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfilePasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pos.features.subscription' => false]);
    }

    private function user(): User
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        return User::factory()->create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'role' => 'owner', 'password' => 'Lama12345', 'email' => 'budi.santoso@example.com']);
    }

    public function test_profile_form_can_no_longer_change_the_password_directly(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('profile.update'), ['name' => 'Budi Baru', 'phone' => '0812', 'password' => 'Baru123456', 'password_confirmation' => 'Baru123456'])->assertRedirect();

        $user->refresh();
        $this->assertSame('Budi Baru', $user->name);
        $this->assertTrue(Hash::check('Lama12345', $user->password));
    }

    public function test_profile_sends_a_reset_link_by_email_and_masks_the_address(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->actingAs($user)->postJson(route('profile.password.link'))
            ->assertOk()->assertJson(['email' => 'b***********@example.com', 'expires_minutes' => 60]);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_requesting_again_right_away_is_throttled(): void
    {
        Notification::fake();
        $user = $this->user();
        $this->actingAs($user)->postJson(route('profile.password.link'))->assertOk();

        $this->actingAs($user)->postJson(route('profile.password.link'))->assertStatus(429);
    }

    public function test_the_emailed_link_works_while_logged_in_and_returns_to_the_profile(): void
    {
        $user = $this->user();
        $token = app('auth.password.broker')->createToken($user);

        $this->actingAs($user)->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->actingAs($user)->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'Baru123456', 'password_confirmation' => 'Baru123456'])
            ->assertRedirect(route('profile'));

        $this->assertTrue(Hash::check('Baru123456', $user->fresh()->password));
    }

    public function test_the_link_cannot_be_reused_or_forged(): void
    {
        $user = $this->user();
        $token = app('auth.password.broker')->createToken($user);
        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'Baru123456', 'password_confirmation' => 'Baru123456'])->assertRedirect(route('login'));

        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'Lagi123456', 'password_confirmation' => 'Lagi123456'])->assertSessionHasErrors('email');
        $this->post(route('password.update'), ['token' => 'palsu', 'email' => $user->email, 'password' => 'Lagi123456', 'password_confirmation' => 'Lagi123456'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Baru123456', $user->fresh()->password));
    }
}
