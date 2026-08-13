<?php

namespace Tests\Feature\Balance;

use App\Exceptions\BalanceException;
use App\Models\BalanceTransaction;
use App\Models\Business;
use App\Models\Order;
use App\Models\WithdrawalRequest;
use App\Services\BalanceService;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * The merchant balance ledger — this is the platform's own money path, so every
 * movement must be exactly once, locked, and reconcilable with the ledger rows.
 */
class BalanceServiceTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    private function balance(): BalanceService
    {
        return app(BalanceService::class);
    }

    private function qrisOrder(float $total = 100000): Order
    {
        $product = $this->product(price: $total);

        return app(PosOrderService::class)->createOrder($this->cashier, $this->payload([
            ['product_id' => $product->id, 'qty' => 1, 'price' => $total],
        ], ['payment_method' => 'qris']));
    }

    private function withdrawal(float $amount): WithdrawalRequest
    {
        return WithdrawalRequest::create([
            'business_id'    => $this->business->id,
            'amount'         => $amount,
            'bank_name'      => 'BCA',
            'account_number' => '1234567890',
            'account_name'   => 'Varil Wijaya',
            'status'         => 'processing',
        ]);
    }

    public function test_a_paid_qris_order_credits_the_merchant_balance(): void
    {
        $this->setUpPos();
        $order = $this->qrisOrder(100000);

        $tx = $this->balance()->creditFromQris($order);

        $this->assertEquals(100000, $this->business->fresh()->balance);
        $this->assertEquals(100000, $this->business->fresh()->total_earned);
        $this->assertSame('credit', $tx->type);
        $this->assertEquals(0, $tx->balance_before);
        $this->assertEquals(100000, $tx->balance_after);
        $this->assertSame($order->order_number, $tx->reference);
    }

    public function test_the_platform_fee_is_withheld_from_the_credit(): void
    {
        config(['app.platform_fee_percent' => 2]);
        $this->setUpPos();
        $order = $this->qrisOrder(100000);

        $tx = $this->balance()->creditFromQris($order);

        $this->assertEquals(2000, $tx->platform_fee);
        $this->assertEquals(98000, $tx->net_amount);
        $this->assertEquals(98000, $this->business->fresh()->balance);
    }

    public function test_crediting_the_same_order_twice_does_not_double_the_balance(): void
    {
        $this->setUpPos();
        $order = $this->qrisOrder(100000);

        // Midtrans retries its webhook — this must be harmless.
        $first  = $this->balance()->creditFromQris($order);
        $second = $this->balance()->creditFromQris($order);

        $this->assertSame($first->id, $second->id);
        $this->assertEquals(100000, $this->business->fresh()->balance);
        $this->assertSame(1, BalanceTransaction::where('type', 'credit')->count());
    }

    public function test_refunding_a_credited_order_takes_the_money_back(): void
    {
        $this->setUpPos();
        $order = $this->qrisOrder(100000);
        $this->balance()->creditFromQris($order);

        $tx = $this->balance()->reverseFromRefund($order);

        $this->assertNotNull($tx);
        $this->assertSame('debit', $tx->type);
        $this->assertSame('refund', $tx->source);
        $this->assertEquals(0, $this->business->fresh()->balance);
        $this->assertEquals(0, $this->business->fresh()->total_earned);
    }

    public function test_refunding_an_order_that_was_never_credited_changes_nothing(): void
    {
        $this->setUpPos();
        $order = $this->qrisOrder(100000); // never credited — payment never settled

        $tx = $this->balance()->reverseFromRefund($order);

        $this->assertNull($tx);
        $this->assertEquals(0, $this->business->fresh()->balance);
        $this->assertSame(0, BalanceTransaction::count());
    }

    public function test_reversing_twice_only_debits_once(): void
    {
        $this->setUpPos();
        $order = $this->qrisOrder(100000);
        $this->balance()->creditFromQris($order);

        $this->balance()->reverseFromRefund($order);
        $this->assertNull($this->balance()->reverseFromRefund($order));

        $this->assertEquals(0, $this->business->fresh()->balance);
        $this->assertSame(1, BalanceTransaction::where('type', 'debit')->count());
    }

    public function test_a_completed_withdrawal_debits_the_balance(): void
    {
        $this->setUpPos();
        $this->balance()->creditFromQris($this->qrisOrder(500000));
        $wd = $this->withdrawal(200000);

        $tx = $this->balance()->debitForWithdrawal($wd);

        $this->assertEquals(300000, $this->business->fresh()->balance);
        $this->assertEquals(200000, $this->business->fresh()->total_withdrawn);
        $this->assertSame('withdrawal', $tx->source);
        $this->assertEquals(500000, $tx->balance_before);
        $this->assertEquals(300000, $tx->balance_after);
    }

    public function test_a_withdrawal_larger_than_the_balance_is_refused(): void
    {
        $this->setUpPos();
        $this->balance()->creditFromQris($this->qrisOrder(100000));
        $wd = $this->withdrawal(500000);

        $this->expectException(BalanceException::class);
        $this->expectExceptionMessageMatches('/Saldo tidak mencukupi/');

        $this->balance()->debitForWithdrawal($wd);
    }

    public function test_a_refused_withdrawal_leaves_the_balance_and_ledger_untouched(): void
    {
        $this->setUpPos();
        $this->balance()->creditFromQris($this->qrisOrder(100000));
        $wd = $this->withdrawal(500000);

        try {
            $this->balance()->debitForWithdrawal($wd);
            $this->fail('Expected the withdrawal to be refused.');
        } catch (BalanceException) {
            // expected
        }

        $this->assertEquals(100000, $this->business->fresh()->balance);
        $this->assertSame(0, BalanceTransaction::where('type', 'debit')->count());
    }

    public function test_the_same_withdrawal_cannot_be_processed_twice(): void
    {
        $this->setUpPos();
        $this->balance()->creditFromQris($this->qrisOrder(500000));
        $wd = $this->withdrawal(200000);

        $this->balance()->debitForWithdrawal($wd);

        $this->expectException(BalanceException::class);
        $this->expectExceptionMessageMatches('/sudah pernah diproses/');

        $this->balance()->debitForWithdrawal($wd);
    }

    public function test_the_balance_check_uses_the_current_row_not_a_stale_model(): void
    {
        $this->setUpPos();
        $this->balance()->creditFromQris($this->qrisOrder(500000));

        // Grab a withdrawal whose ->business relation was loaded while the balance
        // was still 500.000, then drain the account through a different instance.
        $wd = $this->withdrawal(400000);
        $wd->load('business');
        $this->assertEquals(500000, $wd->business->balance);

        $drain = $this->withdrawal(450000);
        $this->balance()->debitForWithdrawal($drain);

        // Only 50.000 left — the stale 500.000 on $wd must not authorise this.
        $this->expectException(BalanceException::class);
        $this->balance()->debitForWithdrawal($wd);
    }

    public function test_the_ledger_reconciles_with_the_balance_after_a_full_cycle(): void
    {
        $this->setUpPos();
        $this->balance()->creditFromQris($this->qrisOrder(300000));
        $refunded = $this->qrisOrder(100000);
        $this->balance()->creditFromQris($refunded);
        $this->balance()->reverseFromRefund($refunded);
        $this->balance()->debitForWithdrawal($this->withdrawal(50000));

        $ledger = BalanceTransaction::where('business_id', $this->business->id)->get();
        $net    = $ledger->sum(fn ($t) => $t->type === 'credit' ? (float) $t->net_amount : -(float) $t->net_amount);

        $this->assertEquals(250000, $net);
        $this->assertEquals(250000, $this->business->fresh()->balance);
    }

    public function test_a_business_balance_is_never_touched_by_another_tenants_order(): void
    {
        $this->setUpPos();
        $other = Business::factory()->create();

        $this->balance()->creditFromQris($this->qrisOrder(100000));

        $this->assertEquals(100000, $this->business->fresh()->balance);
        $this->assertEquals(0, $other->fresh()->balance);
    }
}
