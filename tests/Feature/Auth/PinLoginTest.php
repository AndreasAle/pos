<?php

namespace Tests\Feature\Auth;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * PIN sign-in for the tablet register.
 *
 * A four to six digit code is only safe because the device is paired to one
 * outlet first: that is what keeps the guess space small enough to rate limit,
 * and what stops a PIN from matching a stranger at another tenant.
 */
class PinLoginTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private const OUTLET_COOKIE = 'pos_outlet';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
    }

    private function cashierWithPin(string $pin = '234523', array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'cashier',
            'is_active'   => true,
            'pin'         => Hash::make($pin),
        ], $overrides));
    }

    /**
     * Requests carrying the paired-outlet cookie.
     *
     * Pairs for real and reuses the token the server issued, so the test uses
     * exactly what a tablet holds — signature and all.
     */
    private function paired(?Outlet $outlet = null): self
    {
        $outlet = $outlet ?? $this->outlet;

        $cookie = $this->post(route('pin.pair'), ['outlet_code' => $outlet->code])
            ->getCookie(self::OUTLET_COOKIE, false);

        $this->assertNotNull($cookie, 'Pairing did not set the outlet cookie.');

        return $this->withUnencryptedCookie(self::OUTLET_COOKIE, $cookie->getValue());
    }

    // ── Pairing ──────────────────────────────────────────────────────────────

    public function test_an_unpaired_device_is_asked_for_an_outlet_code(): void
    {
        $this->setUpPos();

        $this->get(route('pin.show'))
            ->assertOk()
            ->assertSee('Pasangkan Perangkat');
    }

    public function test_a_valid_outlet_code_pairs_the_device(): void
    {
        $this->setUpPos();

        $this->post(route('pin.pair'), ['outlet_code' => $this->outlet->code])
            ->assertRedirect(route('pin.show'))
            ->assertCookie(self::OUTLET_COOKIE);
    }

    public function test_an_unknown_outlet_code_is_refused(): void
    {
        $this->setUpPos();

        $this->post(route('pin.pair'), ['outlet_code' => 'TIDAKADA'])
            ->assertSessionHasErrors('outlet_code');
    }

    public function test_a_paired_device_shows_the_pin_pad(): void
    {
        $this->setUpPos();
        $cashier = $this->cashierWithPin();

        $this->paired()->get(route('pin.show'))
            ->assertOk()
            ->assertSee($cashier->name)
            ->assertSee('Masukkan PIN')
            // The keypad has to be in the markup, not conjured by a framework
            // that may never load on a tablet with a flaky connection.
            ->assertSee('data-digit="7"', false)
            ->assertSee('Buka Kasir');
    }

    // ── Signing in ───────────────────────────────────────────────────────────

    public function test_a_correct_pin_opens_the_register(): void
    {
        $this->setUpPos();
        $cashier = $this->cashierWithPin('234523');

        $this->paired()
            ->post(route('pin.login'), ['pin' => '234523'])
            ->assertRedirect(route('pos.index'));

        $this->assertAuthenticatedAs($cashier);
    }

    public function test_a_wrong_pin_is_refused(): void
    {
        $this->setUpPos();
        $this->cashierWithPin('234523');

        $this->paired()
            ->post(route('pin.login'), ['pin' => '999999'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_a_pin_cannot_be_used_before_the_device_is_paired(): void
    {
        $this->setUpPos();
        $this->cashierWithPin('234523');

        $this->post(route('pin.login'), ['pin' => '234523'])
            ->assertRedirect(route('pin.show'));

        $this->assertGuest();
    }

    public function test_a_deactivated_cashier_cannot_sign_in(): void
    {
        $this->setUpPos();
        $this->cashierWithPin('234523', ['is_active' => false]);

        $this->paired()
            ->post(route('pin.login'), ['pin' => '234523'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_a_kitchen_account_cannot_open_the_register(): void
    {
        $this->setUpPos();
        $this->cashierWithPin('234523', ['role' => 'kitchen']);

        $this->paired()
            ->post(route('pin.login'), ['pin' => '234523'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    public function test_an_account_without_a_pin_is_never_matched(): void
    {
        $this->setUpPos();
        $this->cashierWithPin('234523', ['pin' => null]);

        $this->paired()
            ->post(route('pin.login'), ['pin' => '234523'])
            ->assertSessionHasErrors('pin');
    }

    // ── Tenant boundary ──────────────────────────────────────────────────────

    public function test_the_same_pin_at_another_outlet_signs_in_the_right_person(): void
    {
        $this->setUpPos();
        $mine = $this->cashierWithPin('234523');
        $myOutlet = $this->outlet;

        // A second tenant whose cashier happens to pick the same PIN.
        $this->setUpPos();
        $theirs = $this->cashierWithPin('234523');

        $this->paired($myOutlet)
            ->post(route('pin.login'), ['pin' => '234523'])
            ->assertRedirect(route('pos.index'));

        $this->assertAuthenticatedAs($mine);
        $this->assertNotSame($theirs->id, auth()->id());
    }

    public function test_a_pin_from_another_business_does_not_work_here(): void
    {
        $this->setUpPos();
        $myOutlet = $this->outlet;

        $this->setUpPos();
        $this->cashierWithPin('777777'); // belongs to the second tenant

        $this->paired($myOutlet)
            ->post(route('pin.login'), ['pin' => '777777'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest();
    }

    // ── Brute force ──────────────────────────────────────────────────────────

    public function test_repeated_wrong_pins_are_locked_out(): void
    {
        $this->setUpPos();
        $this->cashierWithPin('234523');

        for ($i = 0; $i < 5; $i++) {
            $this->paired()->post(route('pin.login'), ['pin' => '000000']);
        }

        $response = $this->paired()->post(route('pin.login'), ['pin' => '000000']);
        $response->assertSessionHasErrors('pin');

        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            session('errors')->first('pin')
        );
    }

    public function test_the_lockout_blocks_even_the_correct_pin(): void
    {
        $this->setUpPos();
        $this->cashierWithPin('234523');

        for ($i = 0; $i < 5; $i++) {
            $this->paired()->post(route('pin.login'), ['pin' => '000000']);
        }

        $this->paired()->post(route('pin.login'), ['pin' => '234523']);

        $this->assertGuest();
    }

    // ── Signing out ──────────────────────────────────────────────────────────

    public function test_signing_out_returns_to_the_pin_pad_and_keeps_the_pairing(): void
    {
        $this->setUpPos();
        $cashier = $this->cashierWithPin();

        $paired = $this->paired();

        $paired->actingAs($cashier)
            ->post(route('pin.logout'))
            ->assertRedirect(route('pin.show'));

        $this->assertGuest();

        // The pairing is untouched, so the next person is met by the PIN pad
        // rather than being asked for the outlet code again.
        $paired->get(route('pin.show'))
            ->assertOk()
            ->assertSee('Masukkan PIN')
            ->assertDontSee('Pasangkan Perangkat');
    }

    public function test_unpairing_clears_the_outlet(): void
    {
        $this->setUpPos();

        $this->paired()
            ->post(route('pin.unpair'))
            ->assertRedirect(route('pin.show'))
            ->assertCookieExpired(self::OUTLET_COOKIE);
    }

    public function test_an_already_signed_in_device_goes_straight_to_the_register(): void
    {
        $this->setUpPos();
        $cashier = $this->cashierWithPin();

        $this->actingAs($cashier)
            ->paired()
            ->get(route('pin.show'))
            ->assertRedirect(route('pos.index'));
    }
}
