<?php

namespace Tests\Feature\Security;

use App\Models\ExpenseCategory;
use App\Models\IntelligenceActionProposal;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\FinanceLedger;
use App\Services\PosOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/**
 * Regression tests for docs/security/01-architecture-security-review.md.
 * IDs (SEC-xx) match the findings register.
 */
class SecurityRegressionTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create([
            'business_id' => $this->business->id, 'outlet_id' => $this->outlet->id,
            'role' => 'owner', 'is_active' => true, 'email_verified_at' => now(),
        ]);
    }

    // SEC-01: baseline security headers
    public function test_responses_carry_baseline_security_headers(): void
    {
        $res = $this->get('/login');

        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertTrue($res->headers->has('Permissions-Policy'));
    }

    // SEC-02: short cash payment is rejected server-side
    public function test_cash_sale_paid_below_total_is_rejected_and_nothing_is_saved(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 50000);

        $this->actingAs($this->cashier)->postJson(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment_method' => 'cash', 'paid_amount' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors('paid_amount');

        $this->assertSame(0, Order::count());
    }

    public function test_split_payment_below_total_is_rejected(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 50000);

        $this->expectException(ValidationException::class);
        app(PosOrderService::class)->createOrder($this->cashier, [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payment_method' => 'cash',
            'split_payments' => [['method' => 'cash', 'amount' => 10000], ['method' => 'qris', 'amount' => 10000]],
        ]);
    }

    public function test_exact_and_over_cash_payment_still_works(): void
    {
        $this->setUpPos();
        $product = $this->product(price: 50000);

        $order = app(PosOrderService::class)->createOrder($this->cashier, $this->payload(
            [['product_id' => $product->id, 'qty' => 1]], ['paid_amount' => 50000]
        ));
        $this->assertSame('paid', $order->payment_status);

        $order = app(PosOrderService::class)->createOrder($this->cashier, $this->payload(
            [['product_id' => $product->id, 'qty' => 1]], ['paid_amount' => 100000]
        ));
        $this->assertEquals(50000, (float) $order->change_amount);
    }

    // SEC-10: cross-tenant finance records (BOLA)
    public function test_owner_cannot_void_another_business_expense(): void
    {
        $this->setUpPos();
        $foreign = $this->foreignUser();
        ExpenseCategory::ensureDefaults($foreign->business_id);
        $cat = ExpenseCategory::where('business_id', $foreign->business_id)->first();
        $expense = app(FinanceLedger::class)->recordExpense($foreign, [
            'expense_category_id' => $cat->id, 'expense_date' => now()->toDateString(), 'amount' => 75000,
        ]);

        $this->actingAs($this->owner())->post(route('expenses.void', $expense), ['reason' => 'x'])
            ->assertStatus(404);
        $this->assertNotSame('void', $expense->fresh()->status);

        $this->expectException(HttpException::class);
        app(FinanceLedger::class)->voidExpense($this->owner(), $expense, 'x');
    }

    public function test_owner_cannot_pay_or_void_another_business_receivable(): void
    {
        $this->setUpPos();
        $foreign = $this->foreignUser();
        $r = app(FinanceLedger::class)->createReceivable($foreign, [
            'party_name' => 'Pihak B', 'issue_date' => now()->toDateString(), 'amount' => 100000,
        ]);
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('receivables.pay', $r), [
            'amount' => 100000, 'payment_method' => 'cash', 'paid_at' => now()->toDateString(),
        ])->assertStatus(404);
        $this->actingAs($owner)->post(route('receivables.void', $r), ['reason' => 'x'])->assertStatus(404);

        $r->refresh();
        $this->assertEquals(0, (float) $r->paid_amount);
        $this->assertSame('open', $r->status);
    }

    // SEC-11: AI action proposals are tenant-bound
    public function test_ai_action_proposal_of_another_business_cannot_be_approved_or_executed(): void
    {
        $this->setUpPos();
        $foreign = $this->foreignUser();
        $proposal = IntelligenceActionProposal::create([
            'business_id' => $foreign->business_id, 'actor_id' => $foreign->id, 'action_type' => 'test',
            'payload' => [], 'preview' => [], 'payload_hash' => str_repeat('a', 64), 'expected_version' => 'v1',
            'idempotency_key' => 'k-'.uniqid(), 'status' => 'pending', 'expires_at' => now()->addMinutes(10),
        ]);
        $owner = $this->owner();

        $this->actingAs($owner)->postJson(route('intelligence.actions.approve', $proposal), ['confirmation' => 'SETUJUI'])
            ->assertStatus(403);
        $this->actingAs($owner)->postJson(route('intelligence.actions.execute', $proposal))->assertStatus(403);
        $this->assertSame('pending', $proposal->fresh()->status);
    }
}
