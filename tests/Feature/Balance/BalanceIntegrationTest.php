<?php

namespace Tests\Feature\Balance;

use App\Models\BalanceTransaction;
use App\Models\Order;
use App\Services\BalanceService;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * The balance ledger wired into the flows that actually move money: a cashier
 * confirming a QRIS payment by hand, and an order being refunded or voided.
 *
 * Both paths silently lost or kept money before these tests existed.
 */
class BalanceIntegrationTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function qrisDraft(float $total = 100000): Order
    {
        $product = $this->product(price: $total);

        return app(PosOrderService::class)->createOrder(
            $this->cashier,
            $this->payload(
                [['product_id' => $product->id, 'qty' => 1, 'price' => $total]],
                ['payment_method' => 'qris', 'paid_amount' => 0]
            ),
            paymentPending: true
        );
    }

    public function test_manually_confirming_a_qris_order_credits_the_merchant(): void
    {
        $this->setUpPos();
        $order = $this->qrisDraft(100000);

        $this->assertEquals(0, $this->business->fresh()->balance);

        $this->actingAs($this->cashier)
            ->postJson(route('pos.qris-confirm', $order))
            ->assertOk()
            ->assertJson(['success' => true]);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);

        // This is the bug that used to lose the money for good.
        $this->assertEquals(100000, $this->business->fresh()->balance);
        $this->assertSame(1, BalanceTransaction::where('type', 'credit')->count());
    }

    public function test_a_late_webhook_after_a_manual_confirm_does_not_double_credit(): void
    {
        $this->setUpPos();
        $order = $this->qrisDraft(100000);

        $this->actingAs($this->cashier)->postJson(route('pos.qris-confirm', $order))->assertOk();

        // The webhook Midtrans sends afterwards runs the same credit.
        app(BalanceService::class)->creditFromQris($order->fresh());

        $this->assertEquals(100000, $this->business->fresh()->balance);
        $this->assertSame(1, BalanceTransaction::where('type', 'credit')->count());
    }

    public function test_refunding_a_qris_order_returns_the_credited_balance(): void
    {
        $this->setUpPos();
        $order = $this->qrisDraft(100000);
        $this->actingAs($this->cashier)->postJson(route('pos.qris-confirm', $order))->assertOk();
        $this->assertEquals(100000, $this->business->fresh()->balance);

        $this->actingAs($this->cashier)
            ->post(route('orders.refund', $order), ['reason' => 'Komplain'])
            ->assertRedirect();

        $this->assertEquals(0, $this->business->fresh()->balance);
        $this->assertSame(1, BalanceTransaction::where('type', 'debit')->count());
    }

    public function test_voiding_a_qris_order_returns_the_credited_balance(): void
    {
        $this->setUpPos();
        $order = $this->qrisDraft(100000);
        $this->actingAs($this->cashier)->postJson(route('pos.qris-confirm', $order))->assertOk();

        $this->actingAs($this->cashier)
            ->post(route('orders.void', $order), ['reason' => 'Salah input'])
            ->assertRedirect();

        $this->assertEquals(0, $this->business->fresh()->balance);
    }

    public function test_refunding_a_cash_order_never_touches_the_balance(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 100000);
        $order   = app(PosOrderService::class)->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => 100000],
        ]));

        $this->actingAs($this->cashier)
            ->post(route('orders.refund', $order), ['reason' => 'Komplain'])
            ->assertRedirect();

        $this->assertEquals(0, $this->business->fresh()->balance);
        $this->assertSame(0, BalanceTransaction::count());
    }
}
