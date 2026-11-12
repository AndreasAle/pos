<?php

namespace Tests\Feature\Finance;

use App\Models\ExpenseCategory;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\FinanceLedger;
use App\Services\Finance\FinanceReports;
use App\Services\Reporting\SalesReportFilter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

class FinanceServiceTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPos(['enable_tax' => true, 'tax_percent' => 10]);
        $this->business->update(['timezone' => 'Asia/Jakarta']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'outlet_id' => null, 'role' => 'owner']);
        ExpenseCategory::ensureDefaults($this->business->id);
    }

    private function ledger(): FinanceLedger
    {
        return app(FinanceLedger::class);
    }

    private function reports(): FinanceReports
    {
        return app(FinanceReports::class);
    }

    private function filter(array $in = [], ?User $u = null): SalesReportFilter
    {
        return SalesReportFilter::fromRequest($u ?? $this->owner, array_merge(['date_from' => '2026-11-01', 'date_to' => '2026-11-12'], $in), CarbonImmutable::parse('2026-11-12 12:00', 'Asia/Jakarta'));
    }

    private function sale(float $subtotal, float $discount, float $tax, float $cost = 0, string $status = 'paid', array $o = []): Order
    {
        $order = Order::create(array_merge([
            'business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'order_number' => 'F-'.uniqid(),
            'subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax, 'grand_total' => $subtotal - $discount + $tax,
            'payment_method' => 'cash', 'payment_status' => 'paid', 'status' => $status,
        ], $o));
        Order::whereKey($order->id)->update(['created_at' => CarbonImmutable::parse('2026-11-10 10:00', 'Asia/Jakarta')->setTimezone(config('app.timezone'))]);
        OrderItem::create(['order_id' => $order->id, 'product_name' => 'Kopi', 'price' => $subtotal, 'qty' => 1, 'subtotal' => $subtotal, 'cost_price' => $cost]);

        return $order;
    }

    private function cat(string $code): int
    {
        return ExpenseCategory::where('business_id', $this->business->id)->where('code', $code)->value('id');
    }

    private function expense(string $code, float $amount, array $o = [])
    {
        return $this->ledger()->recordExpense($this->owner, array_merge(['expense_category_id' => $this->cat($code), 'expense_date' => '2026-11-10', 'amount' => $amount], $o));
    }

    public function test_tax_report_separates_collected_reversed_and_remitted(): void
    {
        $this->sale(100000, 10000, 9000);
        $this->sale(50000, 0, 5000, status: 'refunded');
        $this->expense('2105', 4000);

        $t = $this->reports()->tax($this->filter());

        $this->assertSame(90000.0, $t['taxable_base']);
        $this->assertSame(9000.0, $t['tax_collected']);
        $this->assertSame(10.0, $t['effective_rate_pct']);
        $this->assertSame(5000.0, $t['tax_reversed']['amount']);
        $this->assertSame(4000.0, $t['tax_remitted']);
        $this->assertSame(5000.0, $t['tax_balance']);
        $this->assertSame([], $t['warnings']);
    }

    public function test_returns_report_counts_refunds_and_cancellations(): void
    {
        $this->sale(100000, 0, 0);
        $this->sale(40000, 0, 0, status: 'refunded', o: ['refund_reason' => 'Salah pesan']);
        $this->sale(20000, 0, 0, status: 'cancelled', o: ['cancel_reason' => 'Salah pesan']);

        $r = $this->reports()->returns($this->filter());

        $this->assertSame(1, $r['refunded']['orders']);
        $this->assertSame(40000.0, $r['refunded']['grand_total']);
        $this->assertSame(1, $r['cancelled']['orders']);
        $this->assertSame(66.67, $r['return_rate_pct']);
        $this->assertSame('Salah pesan', $r['reasons'][0]['reason']);
    }

    public function test_profit_loss_statement_reconciles_by_hand(): void
    {
        // net 90.000, HPP 40.000 -> gross 50.000; opex 10.000 -> operating 40.000; other 2.000 -> 38.000
        $this->sale(100000, 10000, 9000, cost: 40000);
        $this->expense('6102', 10000);
        $this->expense('7101', 2000);
        $void = $this->expense('6101', 999999);
        $this->ledger()->voidExpense($this->owner, $void, 'salah input');

        $p = $this->reports()->profitLoss($this->filter());

        $this->assertSame(90000.0, $p['revenue']['net_sales']);
        $this->assertSame(40000.0, $p['cogs']);
        $this->assertSame(50000.0, $p['gross_profit']);
        $this->assertSame(10000.0, $p['opex_total']);
        $this->assertSame(40000.0, $p['operating_profit']);
        $this->assertSame(2000.0, $p['other_total']);
        $this->assertSame(38000.0, $p['profit_before_tax']);
        $this->assertSame(9000.0, $p['memo']['tax_collected']);
        $this->assertSame(42.2, $p['net_margin_pct']);
        $this->assertSame([], $p['warnings']);
    }

    public function test_profit_loss_warns_when_costs_or_expenses_are_missing(): void
    {
        $this->sale(100000, 0, 0, cost: 0);

        $w = implode(' ', $this->reports()->profitLoss($this->filter())['warnings']);

        $this->assertStringContainsString('HPP tidak tersedia', $w);
        $this->assertStringContainsString('Belum ada biaya', $w);
    }

    public function test_shared_expenses_only_count_when_all_outlets_are_in_scope(): void
    {
        $other = Outlet::factory()->create(['business_id' => $this->business->id]);
        $this->expense('6102', 5000); // shared, no outlet
        $this->expense('6103', 3000, ['outlet_id' => $this->outlet->id]);

        $all = $this->reports()->expenseTotals($this->filter());
        $one = $this->reports()->expenseTotals($this->filter(['outlet_ids' => [$this->outlet->id]]));
        $other = $this->reports()->expenseTotals($this->filter(['outlet_ids' => [$other->id]]));

        $this->assertSame(8000.0, $all['total']);
        $this->assertSame(3000.0, $one['total']);
        $this->assertSame(0.0, $other['total']);
    }

    public function test_expense_validation_and_tenant_boundary(): void
    {
        $foreignCat = ExpenseCategory::create(['business_id' => $this->foreignUser()->business_id, 'code' => '6101', 'name' => 'X']);
        try {
            $this->ledger()->recordExpense($this->owner, ['expense_category_id' => $foreignCat->id, 'expense_date' => '2026-11-10', 'amount' => 1000]);
            $this->fail('foreign category accepted');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $this->expectException(ValidationException::class);
        $this->expense('6101', 0);
    }

    public function test_purchase_receives_stock_updates_cost_and_tracks_partial_payment(): void
    {
        $ing = Ingredient::factory()->create(['business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'current_stock' => 10, 'average_cost' => 1000]);
        $sup = Supplier::create(['business_id' => $this->business->id, 'name' => 'CV Biji', 'payment_terms_days' => 14]);

        $p = $this->ledger()->createPurchase($this->owner, [
            'supplier_id' => $sup->id, 'purchase_date' => '2026-11-10', 'tax_amount' => 2000, 'shipping_amount' => 1000, 'discount_amount' => 500, 'paid_now' => 10000,
            'items' => [['ingredient_id' => $ing->id, 'description' => 'Biji kopi', 'qty' => 10, 'unit_cost' => 2000]],
        ]);

        $this->assertSame(22500.0, (float) $p->total); // 20.000 - 500 + 2.000 + 1.000
        $this->assertSame('partial', $p->payment_status);
        $this->assertSame(12500.0, $p->outstanding);
        $this->assertSame('2026-11-24', $p->due_date->toDateString());
        $this->assertSame(20.0, (float) $ing->fresh()->current_stock);
        $this->assertSame(1500.0, (float) $ing->fresh()->average_cost); // (10x1000 + 10x2000) / 20
        $this->assertMatchesRegularExpression('/^PB-261110-\d{3}$/', $p->purchase_number);

        $this->ledger()->payPurchase($this->owner, $p, 12500, 'transfer', '2026-11-12');
        $this->assertSame('paid', $p->fresh()->payment_status);
    }

    public function test_purchase_overpayment_and_cancel_rules(): void
    {
        $ing = Ingredient::factory()->create(['business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'current_stock' => 0, 'average_cost' => 0]);
        $sup = Supplier::create(['business_id' => $this->business->id, 'name' => 'S']);
        $mk = fn () => $this->ledger()->createPurchase($this->owner, ['supplier_id' => $sup->id, 'purchase_date' => '2026-11-10', 'items' => [['ingredient_id' => $ing->id, 'description' => 'Gula', 'qty' => 5, 'unit_cost' => 1000]]]);

        $a = $mk();
        try {
            $this->ledger()->payPurchase($this->owner, $a, 5001, 'cash', '2026-11-11');
            $this->fail('overpayment accepted');
        } catch (ValidationException) {
        }
        $this->ledger()->cancelPurchase($this->owner, $a, 'salah input');
        $this->assertSame(0.0, (float) $ing->fresh()->current_stock, 'stock received by the purchase is taken back');
        $this->assertSame(0.0, $a->fresh()->outstanding);

        $b = $mk();
        $this->ledger()->payPurchase($this->owner, $b, 1000, 'cash', '2026-11-11');
        $this->expectException(ValidationException::class);
        $this->ledger()->cancelPurchase($this->owner, $b, 'tidak boleh');
    }

    public function test_foreign_supplier_and_foreign_purchase_are_rejected(): void
    {
        $foreign = $this->foreignUser();
        $fSup = Supplier::create(['business_id' => $foreign->business_id, 'name' => 'Asing']);
        try {
            $this->ledger()->createPurchase($this->owner, ['supplier_id' => $fSup->id, 'purchase_date' => '2026-11-10', 'items' => [['description' => 'x', 'qty' => 1, 'unit_cost' => 1]]]);
            $this->fail('foreign supplier accepted');
        } catch (ValidationException) {
        }
        $theirs = $this->ledger()->createPurchase($foreign, ['supplier_id' => $fSup->id, 'purchase_date' => '2026-11-10', 'items' => [['description' => 'x', 'qty' => 1, 'unit_cost' => 1000]]]);

        $this->expectException(NotFoundHttpException::class);
        $this->ledger()->payPurchase($this->owner, $theirs, 500, 'cash', '2026-11-10');
    }

    public function test_payables_aging_buckets_by_due_date(): void
    {
        $sup = Supplier::create(['business_id' => $this->business->id, 'name' => 'S']);
        $mk = fn (string $due, float $cost) => $this->ledger()->createPurchase($this->owner, ['supplier_id' => $sup->id, 'purchase_date' => '2026-08-01', 'due_date' => $due, 'items' => [['description' => 'x', 'qty' => 1, 'unit_cost' => $cost]]]);
        $mk('2026-12-01', 1000); // current
        $mk('2026-11-02', 2000); // 10 days
        $mk('2026-09-30', 4000); // 43 days
        $mk('2026-06-01', 8000); // > 90 days

        $a = $this->reports()->payablesAging($this->business->id, CarbonImmutable::parse('2026-11-12 12:00'));

        $this->assertSame(1000.0, $a['buckets']['current']);
        $this->assertSame(2000.0, $a['buckets']['d1_30']);
        $this->assertSame(4000.0, $a['buckets']['d31_60']);
        $this->assertSame(8000.0, $a['buckets']['d90_plus']);
        $this->assertSame(15000.0, $a['total']);
        $this->assertSame(14000.0, $a['overdue']);
    }

    public function test_receivables_track_payments_and_block_overpayment(): void
    {
        $r = $this->ledger()->createReceivable($this->owner, ['party_name' => 'Pak Budi', 'amount' => 100000, 'issue_date' => '2026-11-01', 'due_date' => '2026-11-08']);
        $this->ledger()->payReceivable($this->owner, $r, 40000, 'cash', '2026-11-05');

        $this->assertSame(60000.0, $r->fresh()->outstanding);
        $this->assertSame('partial', $r->fresh()->payment_status);
        $aging = $this->reports()->receivablesAging($this->business->id, CarbonImmutable::parse('2026-11-12 12:00'));
        $this->assertSame(60000.0, $aging['buckets']['d1_30']);

        $this->expectException(ValidationException::class);
        $this->ledger()->payReceivable($this->owner, $r, 60001, 'cash', '2026-11-06');
    }
}
