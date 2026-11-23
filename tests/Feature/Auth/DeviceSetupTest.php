<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\PinLoginController;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Binding a device to an outlet by signing in with an account
 * (docs/login-flow-audit.md), then the owner's PIN and quick setup.
 */
class DeviceSetupTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private const APP_UA = 'Mozilla/5.0 (Linux; Android 14) Chrome/157 Safari/537.36 YokaPOSApp/1.0.0';

    private function owner(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'owner',
            'is_active'   => true,
            'pin'         => null,
        ], $overrides));
    }

    public function test_an_owner_with_one_outlet_is_bound_and_asked_for_a_pin(): void
    {
        $this->setUpPos();
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('device.setup'))
            ->assertRedirect(route('device.pin'))
            ->assertCookie(PinLoginController::OUTLET_COOKIE);
    }

    public function test_several_outlets_are_offered_as_a_list(): void
    {
        $this->setUpPos();
        Outlet::factory()->create(['business_id' => $this->business->id, 'name' => 'Cabang Dua', 'is_active' => true]);
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('device.setup'))
            ->assertOk()
            ->assertSee('Pilih outlet')
            ->assertSee('Cabang Dua')
            ->assertCookieMissing(PinLoginController::OUTLET_COOKIE);
    }

    public function test_picking_an_outlet_binds_the_device(): void
    {
        $this->setUpPos();
        $second = Outlet::factory()->create(['business_id' => $this->business->id, 'is_active' => true]);
        $owner  = $this->owner(['pin' => Hash::make('482916')]);
        $this->product();

        $res = $this->actingAs($owner)->post(route('device.setup.store'), ['outlet_id' => $second->id])
            ->assertRedirect(route('pos.index'));

        $this->assertSame(PinLoginController::pairingToken($second), $res->getCookie(PinLoginController::OUTLET_COOKIE, false)->getValue());
    }

    public function test_an_outlet_of_another_business_cannot_be_picked(): void
    {
        $this->setUpPos();
        $foreign = $this->foreignUser();
        $owner   = $this->owner();

        $this->actingAs($owner)->post(route('device.setup.store'), ['outlet_id' => $foreign->outlet_id])
            ->assertForbidden()
            ->assertCookieMissing(PinLoginController::OUTLET_COOKIE);
    }

    public function test_a_cashier_tied_to_an_outlet_binds_only_that_outlet(): void
    {
        $this->setUpPos();
        Outlet::factory()->create(['business_id' => $this->business->id, 'is_active' => true]);

        $res = $this->actingAs($this->cashier)->get(route('device.setup'))
            ->assertRedirect(route('pos.index'));

        $this->assertSame(PinLoginController::pairingToken($this->outlet), $res->getCookie(PinLoginController::OUTLET_COOKIE, false)->getValue());
    }

    public function test_an_owner_creates_a_pin_and_lands_on_quick_setup_without_products(): void
    {
        $this->setUpPos();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('device.pin.store'), ['pin' => '482916', 'pin_confirmation' => '482916'])
            ->assertRedirect(route('device.start'));

        $this->assertTrue(Hash::check('482916', $owner->fresh()->pin));
    }

    public function test_a_pin_already_used_in_the_business_is_refused(): void
    {
        $this->setUpPos();
        User::factory()->create(['business_id' => $this->business->id, 'role' => 'cashier', 'pin' => Hash::make('482916')]);
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('device.pin.store'), ['pin' => '482916', 'pin_confirmation' => '482916'])
            ->assertSessionHasErrors('pin');

        $this->assertNull($owner->fresh()->pin);
    }

    public function test_quick_setup_adds_a_product_and_a_pin_only_cashier(): void
    {
        $this->setUpPos();
        $owner = $this->owner(['pin' => Hash::make('111222')]);

        $this->actingAs($owner)->post(route('device.start.product'), ['name' => 'Es Teh Manis', 'price' => 8000])
            ->assertRedirect(route('device.start'));
        $this->assertDatabaseHas('products', ['business_id' => $this->business->id, 'name' => 'Es Teh Manis', 'price' => 8000]);

        $this->actingAs($owner)->post(route('device.start.cashier'), ['name' => 'Rina', 'pin' => '935172'])
            ->assertRedirect(route('device.start'));

        $rina = User::where('business_id', $this->business->id)->where('name', 'Rina')->firstOrFail();
        $this->assertSame('cashier', $rina->role);
        $this->assertTrue(Hash::check('935172', $rina->pin));
        $this->assertNotNull($rina->email_verified_at);
    }

    public function test_cashiers_cannot_use_quick_setup(): void
    {
        $this->setUpPos();

        $this->actingAs($this->cashier)->post(route('device.start.product'), ['name' => 'X', 'price' => 1])
            ->assertForbidden();
        $this->assertSame(0, Product::where('name', 'X')->count());
    }

    public function test_signing_in_from_the_tablet_app_goes_to_device_setup(): void
    {
        $this->setUpPos();
        $owner = $this->owner(['email' => 'pemilik@contoh.test', 'password' => 'Rahasia123', 'email_verified_at' => now()]);

        $this->withHeader('User-Agent', self::APP_UA)
            ->post(route('login.post'), ['email' => 'pemilik@contoh.test', 'password' => 'Rahasia123'])
            ->assertRedirect(route('device.setup'));
    }

    public function test_signing_in_from_a_browser_still_goes_to_the_dashboard(): void
    {
        $this->setUpPos();
        $this->owner(['email' => 'pemilik@contoh.test', 'password' => 'Rahasia123', 'email_verified_at' => now()]);

        $this->post(route('login.post'), ['email' => 'pemilik@contoh.test', 'password' => 'Rahasia123'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_the_pin_pad_offers_account_sign_in_to_owners_without_a_pin(): void
    {
        $this->setUpPos(); // the scenario cashier has no PIN
        User::factory()->create(['business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'role' => 'cashier', 'is_active' => true, 'pin' => Hash::make('555111')]);
        $this->owner(['name' => 'Bu Sari']);

        $this->withUnencryptedCookie(PinLoginController::OUTLET_COOKIE, PinLoginController::pairingToken($this->outlet))
            ->get(route('pin.show'))
            ->assertOk()
            ->assertSee('Bu Sari belum punya PIN.')
            ->assertSee(route('device.setup'), false);
    }

    public function test_setup_requires_a_login(): void
    {
        $this->get(route('device.setup'))->assertRedirect(route('login'));
    }
}
