<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Dari aplikasi Android Yoka (user agent "YokaPOSApp/"), halaman memakai bingkai aplikasi:
 * bar atas + navigasi bawah, tanpa sidebar web. Browser biasa dan aplikasi klien lama tidak berubah.
 */
class InAppModeTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    private const APP_UA = 'Mozilla/5.0 (Linux; Android 14) Chrome/130 Mobile YokaPOSApp/1.0.1';

    private function cashier(): User
    {
        $this->setUpPos();

        return User::factory()->create([
            'business_id' => $this->business->id, 'outlet_id' => $this->outlet->id,
            'role' => 'cashier', 'is_active' => true, 'email_verified_at' => now(),
        ]);
    }

    public function test_app_gets_app_chrome_and_focused_shift_screen(): void
    {
        $res = $this->actingAs($this->cashier())
            ->withHeader('User-Agent', self::APP_UA)
            ->get('/shifts/open');

        $res->assertOk()
            ->assertSee('app-tabs', false)
            ->assertSee('Uang tunai di laci saat ini')
            ->assertDontSee('Butuh bantuan?')
            ->assertDontSee('Cari menu, produk, atau laporan');
    }

    public function test_browser_and_client_app_keep_web_layout(): void
    {
        $user = $this->cashier();

        foreach (['Mozilla/5.0 Chrome/130', 'Mozilla/5.0 Chrome/130 POSKasirApp/2.7'] as $ua) {
            $this->actingAs($user)->withHeader('User-Agent', $ua)->get('/shifts/open')
                ->assertOk()
                ->assertDontSee('app-tabs', false)
                ->assertSee('Buka Shift Kasir');
        }
    }

    public function test_app_shift_form_still_opens_a_shift(): void
    {
        $user = $this->cashier();

        $this->actingAs($user)->withHeader('User-Agent', self::APP_UA)
            ->post('/shifts/open', ['outlet_id' => $this->outlet->id, 'opening_cash' => 250000])
            ->assertRedirect();

        $this->assertSame(250000.0, (float) $user->activeShift()->first()->opening_cash);
    }

    public function test_app_orders_list_is_grouped_without_numbered_pagination(): void
    {
        $this->actingAs($this->cashier())->withHeader('User-Agent', self::APP_UA)
            ->get('/orders')
            ->assertOk()
            ->assertSee('pesanan')
            ->assertSee('Hari ini')
            ->assertDontSee('Semua transaksi toko, lengkap dengan status')
            ->assertDontSee('Unduh');
    }

    public function test_only_owner_and_admin_can_export_from_orders(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->get('/orders/export/xlsx')->assertForbidden();

        $owner = User::factory()->create([
            'business_id' => $this->business->id, 'outlet_id' => $this->outlet->id,
            'role' => 'owner', 'is_active' => true, 'email_verified_at' => now(),
        ]);
        $this->actingAs($owner)->withHeader('User-Agent', self::APP_UA)->get('/orders')->assertSee('Unduh');

        $res = $this->actingAs($owner)->get('/orders/export/xlsx?date_from='.now()->toDateString());
        $res->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $res->headers->get('content-disposition'));

        $this->actingAs($owner)->get('/orders/export/csv')->assertNotFound();
    }

    public function test_app_shift_order_and_receipt_pages_are_calm_and_emoji_free(): void
    {
        $user = $this->cashier();
        $order = \App\Models\Order::create([
            'business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'user_id' => $user->id,
            'order_number' => 'ORD-T-1', 'subtotal' => 30000, 'grand_total' => 30000, 'paid_amount' => 30000,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'status' => 'paid', 'ordered_at' => now(),
        ]);

        $this->actingAs($user)->withHeader('User-Agent', self::APP_UA)->get('/shifts')
            ->assertOk()->assertSee('Buka shift dulu sebelum mulai jualan.')->assertDontSee('Riwayat Shift');

        $this->actingAs($user)->withHeader('User-Agent', self::APP_UA)->get('/orders/'.$order->id)
            ->assertOk()->assertSee('Cetak struk')->assertDontSee('Informasi Order');

        $receipt = $this->actingAs($user)->withHeader('User-Agent', self::APP_UA)->get('/receipt/'.$order->id)
            ->assertOk()->assertDontSee('Kembali ke POS');
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}]/u', $receipt->getContent());

        $this->actingAs($user)->withHeader('User-Agent', 'Mozilla/5.0 Chrome/130')
            ->get('/receipt/'.$order->id)->assertOk()->assertSee('Kembali ke POS');
    }

    public function test_app_shift_detail_and_register_use_calm_layout(): void
    {
        $this->setUpPos();
        $cashier = $this->cashier;

        $this->actingAs($cashier)->withHeader('User-Agent', self::APP_UA)->get('/shifts/'.$this->shift->id)
            ->assertOk()->assertSee('Uang tunai di laci')->assertDontSee('Rekap Penutupan');

        $this->actingAs($cashier)->withHeader('User-Agent', self::APP_UA)->get('/pos')
            ->assertOk()->assertSee('in-app', false)->assertDontSee('bg-[#cfeadb]', false);

        $this->actingAs($cashier)->withHeader('User-Agent', 'Mozilla/5.0 Chrome/130')->get('/pos')
            ->assertOk()->assertSee('bg-[#cfeadb]', false);
    }

    public function test_app_close_shift_is_one_calm_screen_and_still_closes(): void
    {
        $this->setUpPos();

        $this->actingAs($this->cashier)->withHeader('User-Agent', self::APP_UA)->get('/shifts/'.$this->shift->id.'/close')
            ->assertOk()->assertSee('Hitung uang tunai di laci sekarang')->assertDontSee('Hitung Kas Akhir');

        $this->actingAs($this->cashier)->withHeader('User-Agent', self::APP_UA)
            ->post('/shifts/'.$this->shift->id.'/close', ['closing_cash_actual' => 150000, 'notes' => 'tes'])
            ->assertRedirect();

        $this->assertSame('closed', $this->shift->fresh()->status);
    }
}
