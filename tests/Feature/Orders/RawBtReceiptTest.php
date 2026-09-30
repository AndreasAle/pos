<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Services\EscPosReceipt;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Bluetooth thermal printers (e.g. Blueprint BP-ECO58D) on an Android tablet
 * are unreachable from the browser print dialog, so the register hands raw
 * ESC/POS bytes to the RawBT app instead.
 */
class RawBtReceiptTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function anOrder(): Order
    {
        $product = $this->product(price: 25000);

        return app(PosOrderService::class)->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 2],
        ]));
    }

    public function test_the_browser_dialog_stays_the_default(): void
    {
        $this->setUpPos();
        $order = $this->anOrder();

        $this->actingAs($this->cashier)
            ->get(route('receipt.print', $order))
            ->assertSee('window.print()', false)
            ->assertDontSee('rawbt:base64', false);
    }

    public function test_rawbt_mode_hands_the_receipt_to_rawbt(): void
    {
        $this->setUpPos(['receipt_size' => '58mm', 'print_method' => 'rawbt']);
        $order = $this->anOrder();

        $this->actingAs($this->cashier)
            ->get(route('receipt.print', $order))
            ->assertSee('rawbt:base64,', false)
            ->assertDontSee('window.print()', false);
    }

    public function test_58mm_lines_fit_32_columns(): void
    {
        $this->setUpPos(['receipt_size' => '58mm', 'print_method' => 'rawbt']);
        $order = $this->anOrder();

        $bytes = app(EscPosReceipt::class)->render($order);
        $text  = preg_replace('/\x1B\x40|\x1B[aE][\x00-\x02]|\x1D\x21[\x00-\x11]|\x1D\x56\x41\x00/', '', $bytes);

        $this->assertStringContainsString($order->order_number, $text);
        $this->assertStringContainsString('Rp50.000', $text);

        foreach (explode("\n", $text) as $line) {
            $this->assertLessThanOrEqual(32, strlen($line), "Too wide: [{$line}]");
        }
    }

    // A laptop and an Android phone at the same shop print differently, so the
    // register passes the device's own choice to the print page.

    public function test_a_device_can_ask_for_rawbt_when_the_shop_default_is_the_browser(): void
    {
        $this->setUpPos(['receipt_size' => '58mm']);
        $order = $this->anOrder();

        $this->actingAs($this->cashier)
            ->get(route('receipt.print', $order) . '?via=rawbt')
            ->assertSee('rawbt:base64,', false)
            ->assertDontSee('window.print()', false);
    }

    public function test_a_device_can_ask_for_the_browser_when_the_shop_default_is_rawbt(): void
    {
        $this->setUpPos(['print_method' => 'rawbt']);
        $order = $this->anOrder();

        $this->actingAs($this->cashier)
            ->get(route('receipt.print', $order) . '?via=browser')
            ->assertSee('window.print()', false)
            ->assertDontSee('rawbt:base64', false);
    }

    public function test_an_unknown_choice_falls_back_to_the_shop_default(): void
    {
        $this->setUpPos(['print_method' => 'rawbt']);
        $order = $this->anOrder();

        $this->actingAs($this->cashier)
            ->get(route('receipt.print', $order) . '?via=nonsense')
            ->assertSee('rawbt:base64,', false);
    }

    public function test_the_register_offers_a_per_device_printer_choice(): void
    {
        $this->setUpPos();

        $this->actingAs($this->cashier)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('pos_print_via', false)
            ->assertSee('Bluetooth (RawBT)');
    }
}
