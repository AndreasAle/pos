<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Services\EscPosReceipt;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * The receipt layout the shop fills in from Pengaturan → Struk: address,
 * phone, numbered items, total quantity, and switches for each part. Both
 * the browser print view and the RawBT bytes must honour the same settings.
 */
class ReceiptLayoutTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function anOrder(): Order
    {
        $a = $this->product(price: 25000);
        $a->update(['name' => 'Red Velvet']);
        $b = $this->product(price: 7000);
        $b->update(['name' => 'Popcorn Original']);

        return app(PosOrderService::class)->createOrder($this->cashier, $this->payload([
            ['product_id' => $a->id, 'qty' => 2],
            ['product_id' => $b->id, 'qty' => 1],
        ]));
    }

    private function printed(Order $order)
    {
        return $this->actingAs($this->cashier)->get(route('receipt.print', $order))->assertOk();
    }

    public function test_items_are_numbered_with_quantity_and_price(): void
    {
        $this->setUpPos();
        $order = $this->anOrder();

        $this->printed($order)
            ->assertSee('1. Red Velvet')
            ->assertSee('2 x 25.000')
            ->assertSee('2. Popcorn Original')
            ->assertSee('Total QTY : 3');
    }

    public function test_the_shop_can_set_its_own_address_and_phone(): void
    {
        $this->setUpPos([
            'receipt_address'  => 'Jl. Mertua No. 7, Palembang',
            'receipt_phone'    => '0812-1111-2222',
            'receipt_subtitle' => 'Coffee & Snack',
        ]);

        $this->printed($this->anOrder())
            ->assertSee('Jl. Mertua No. 7, Palembang')
            ->assertSee('No. Telp 0812-1111-2222')
            ->assertSee('Coffee &amp; Snack', false);
    }

    public function test_switches_hide_parts_of_the_receipt(): void
    {
        $this->setUpPos([
            'receipt_phone'          => '0812-1111-2222',
            'receipt_show_phone'     => false,
            'receipt_show_total_qty' => false,
            'receipt_show_cashier'   => false,
        ]);

        $this->printed($this->anOrder())
            ->assertDontSee('0812-1111-2222')
            ->assertDontSee('Total QTY')
            ->assertDontSee($this->cashier->name);
    }

    public function test_the_rawbt_receipt_follows_the_same_settings(): void
    {
        $this->setUpPos([
            'receipt_size'           => '58mm',
            'receipt_address'        => 'Jl. Mertua No. 7',
            'receipt_show_total_qty' => true,
        ]);

        $text = app(EscPosReceipt::class)->render($this->anOrder());

        $this->assertStringContainsString('Jl. Mertua No. 7', $text);
        $this->assertStringContainsString('1. Red Velvet', $text);
        $this->assertStringContainsString('Total QTY : 3', $text);
    }

    public function test_the_receipt_settings_are_saved(): void
    {
        $this->setUpPos();
        $owner = \App\Models\User::factory()->create([
            'business_id' => $this->business->id,
            'outlet_id'   => $this->outlet->id,
            'role'        => 'owner',
            'is_active'   => true,
        ]);

        $this->actingAs($owner)->post(route('settings.receipt.update'), [
            'receipt_header'       => 'KOPI MERTUAKU',
            'receipt_address'      => 'Jl. Mertua No. 7',
            'receipt_phone'        => '0812-1111-2222',
            'receipt_show_cashier' => '0',
            'receipt_show_phone'   => '1',
        ])->assertRedirect();

        $s = $this->business->fresh()->settings;
        $this->assertSame('Jl. Mertua No. 7', $s['receipt_address']);
        $this->assertFalse($s['receipt_show_cashier']);
        $this->assertTrue($s['receipt_show_phone']);
    }
}
