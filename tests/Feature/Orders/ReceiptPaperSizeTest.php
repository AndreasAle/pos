<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Receipt width follows the merchant's printer.
 *
 * 58mm thermal printers are the cheap, common ones in Indonesian cafes. The
 * print view used to hardcode 80mm regardless of the setting, so a shop with a
 * 58mm roll printed receipts clipped down the right edge — while the PDF path,
 * which did read the setting, came out fine. Confusing to diagnose in a shop.
 */
class ReceiptPaperSizeTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function anOrder(): Order
    {
        $product = $this->product(price: 25000);

        return app(PosOrderService::class)->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1],
        ]));
    }

    public function test_the_print_view_uses_80mm_by_default(): void
    {
        $this->setUpPos();
        $order = $this->anOrder();

        $this->actingAs($this->cashier)
            ->get(route('receipt.print', $order))
            ->assertOk()
            ->assertSee('width: 80mm', false)
            ->assertDontSee('width: 58mm', false);
    }

    public function test_the_print_view_narrows_to_58mm_when_configured(): void
    {
        $this->setUpPos(['receipt_size' => '58mm']);
        $order = $this->anOrder();

        $this->actingAs($this->cashier)
            ->get(route('receipt.print', $order))
            ->assertOk()
            ->assertSee('width: 58mm', false)
            ->assertDontSee('width: 80mm', false);
    }

    public function test_the_page_size_rule_follows_the_setting(): void
    {
        $this->setUpPos(['receipt_size' => '58mm']);
        $order = $this->anOrder();

        // Without @page the printer falls back to its own default and the
        // receipt can come out on the wrong width regardless of the body rule.
        $this->actingAs($this->cashier)
            ->get(route('receipt.print', $order))
            ->assertSee('size: 58mm auto', false);
    }

    public function test_the_receipt_still_shows_its_contents_when_narrow(): void
    {
        $this->setUpPos(['receipt_size' => '58mm']);
        $order = $this->anOrder();

        $this->actingAs($this->cashier)
            ->get(route('receipt.print', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee($this->business->name);
    }
}
