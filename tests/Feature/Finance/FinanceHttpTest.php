<?php

namespace Tests\Feature\Finance;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Purchase;
use App\Models\Receivable;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\FinanceLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

class FinanceHttpTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPos(['enable_tax' => true, 'tax_percent' => 10]);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'outlet_id' => null, 'role' => 'owner']);
        ExpenseCategory::ensureDefaults($this->business->id);
    }

    private function sale(): Order
    {
        $o = Order::create(['business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'user_id' => $this->cashier->id, 'order_number' => 'H-'.uniqid(), 'subtotal' => 100000,
            'discount_amount' => 10000, 'tax_amount' => 9000, 'grand_total' => 99000, 'payment_method' => 'cash', 'payment_status' => 'paid', 'status' => 'paid']);
        OrderItem::create(['order_id' => $o->id, 'product_name' => 'Kopi', 'price' => 100000, 'qty' => 1, 'subtotal' => 100000, 'cost_price' => 40000]);

        return $o;
    }

    private function routes(): array
    {
        return ['reports.tax', 'reports.returns', 'reports.profit-loss', 'expenses.index', 'expenses.create', 'expense-categories.index', 'suppliers.index', 'purchases.index', 'purchases.create', 'payables.index', 'receivables.index', 'receivables.create'];
    }

    public function test_cashier_is_forbidden_everywhere_and_owner_sees_every_page(): void
    {
        $this->sale();
        foreach ($this->routes() as $r) {
            $this->actingAs($this->cashier)->get(route($r))->assertForbidden();
            $this->actingAs($this->owner)->get(route($r))->assertOk();
        }
    }

    public function test_profit_loss_page_shows_the_hand_computed_numbers_and_exports(): void
    {
        $this->sale();
        app(FinanceLedger::class)->recordExpense($this->owner, ['expense_category_id' => ExpenseCategory::where('code', '6102')->value('id'), 'expense_date' => now()->toDateString(), 'amount' => 10000]);

        $this->actingAs($this->owner)->get(route('reports.profit-loss'))->assertOk()
            ->assertSee('LABA KOTOR')->assertSee('Rp 50.000')->assertSee('LABA OPERASIONAL')->assertSee('Rp 40.000')->assertSee('bukan laporan keuangan teraudit', false);

        $pdf = $this->actingAs($this->owner)->get(route('reports.profit-loss', ['export' => 'pdf']));
        $pdf->assertOk();
        $this->assertStringContainsString('pdf', $pdf->headers->get('content-type'));
    }

    public function test_xlsx_exports_download(): void
    {
        Excel::fake();
        $this->sale();
        foreach (['reports.profit-loss', 'reports.tax', 'reports.returns'] as $r) {
            $this->actingAs($this->owner)->get(route($r, ['export' => 'xlsx']))->assertOk();
        }
        $span = now()->startOfMonth()->toDateString().'-sd-'.now()->endOfMonth()->toDateString().'.xlsx';
        foreach (['laba-rugi-', 'pajak-', 'retur-'] as $prefix) {
            Excel::assertDownloaded($prefix.$span);
        }
    }

    public function test_expense_flow_post_and_void_keeps_the_row(): void
    {
        $cat = ExpenseCategory::where('code', '6103')->value('id');
        $this->actingAs($this->owner)->post(route('expenses.store'), ['expense_category_id' => $cat, 'expense_date' => now()->toDateString(), 'amount' => 25000, 'payment_method' => 'cash', 'payee' => 'PLN'])
            ->assertRedirect(route('expenses.index'));
        $e = Expense::firstOrFail();
        $this->actingAs($this->owner)->get(route('expenses.index'))->assertOk()->assertSee('PLN')->assertSee('Rp 25.000');

        $this->actingAs($this->owner)->post(route('expenses.void', $e), ['reason' => 'salah'])->assertRedirect();
        $this->assertSame('void', $e->fresh()->status);
        $this->assertSame(1, Expense::count(), 'voiding never deletes');
    }

    public function test_future_dated_and_zero_expenses_are_rejected(): void
    {
        $cat = ExpenseCategory::where('code', '6103')->value('id');
        $this->actingAs($this->owner)->post(route('expenses.store'), ['expense_category_id' => $cat, 'expense_date' => now()->addDays(3)->toDateString(), 'amount' => 1000, 'payment_method' => 'cash'])->assertSessionHasErrors('expense_date');
        $this->actingAs($this->owner)->post(route('expenses.store'), ['expense_category_id' => $cat, 'expense_date' => now()->toDateString(), 'amount' => 0, 'payment_method' => 'cash'])->assertSessionHasErrors('amount');
        $this->assertSame(0, Expense::count());
    }

    public function test_purchase_payment_flow_over_http(): void
    {
        $sup = Supplier::create(['business_id' => $this->business->id, 'name' => 'CV A', 'payment_terms_days' => 7]);
        $this->actingAs($this->owner)->post(route('purchases.store'), ['supplier_id' => $sup->id, 'purchase_date' => now()->toDateString(), 'items' => [['description' => 'Gula', 'qty' => 2, 'unit_cost' => 5000]]])->assertRedirect();
        $p = Purchase::firstOrFail();
        $this->assertSame(10000.0, (float) $p->total);

        $this->actingAs($this->owner)->post(route('purchases.pay', $p), ['amount' => 20000, 'payment_method' => 'cash', 'paid_at' => now()->toDateString()])->assertSessionHasErrors('amount');
        $this->actingAs($this->owner)->post(route('purchases.pay', $p), ['amount' => 10000, 'payment_method' => 'cash', 'paid_at' => now()->toDateString()])->assertRedirect();
        $this->assertSame('paid', $p->fresh()->payment_status);
        $this->actingAs($this->owner)->get(route('purchases.show', $p))->assertOk()->assertSee('Lunas');
        $this->actingAs($this->owner)->get(route('payables.index'))->assertOk()->assertDontSee($p->purchase_number);
    }

    public function test_other_tenants_documents_are_not_reachable(): void
    {
        $foreign = $this->foreignUser();
        $fSup = Supplier::create(['business_id' => $foreign->business_id, 'name' => 'Asing']);
        $fp = app(FinanceLedger::class)->createPurchase($foreign, ['supplier_id' => $fSup->id, 'purchase_date' => now()->toDateString(), 'items' => [['description' => 'x', 'qty' => 1, 'unit_cost' => 1000]]]);
        $fr = app(FinanceLedger::class)->createReceivable($foreign, ['party_name' => 'Asing', 'amount' => 5000, 'issue_date' => now()->toDateString()]);
        ExpenseCategory::ensureDefaults($foreign->business_id);
        $fe = app(FinanceLedger::class)->recordExpense($foreign, ['expense_category_id' => ExpenseCategory::where('business_id', $foreign->business_id)->value('id'), 'expense_date' => now()->toDateString(), 'amount' => 1000]);

        $this->actingAs($this->owner)->get(route('purchases.show', $fp))->assertNotFound();
        $this->actingAs($this->owner)->post(route('purchases.pay', $fp), ['amount' => 100, 'payment_method' => 'cash', 'paid_at' => now()->toDateString()])->assertNotFound();
        $this->actingAs($this->owner)->get(route('receivables.show', $fr))->assertNotFound();
        $this->actingAs($this->owner)->post(route('receivables.pay', $fr), ['amount' => 100, 'payment_method' => 'cash', 'paid_at' => now()->toDateString()])->assertNotFound();
        $this->actingAs($this->owner)->post(route('expenses.void', $fe), ['reason' => 'x'])->assertNotFound();
        $this->actingAs($this->owner)->get(route('suppliers.edit', $fSup))->assertNotFound();
        $this->assertSame('posted', $fe->fresh()->status);
    }

    public function test_receivable_flow_over_http(): void
    {
        $this->actingAs($this->owner)->post(route('receivables.store'), ['party_name' => 'Pak Budi', 'issue_date' => now()->toDateString(), 'amount' => 50000])->assertRedirect();
        $r = Receivable::firstOrFail();
        $this->actingAs($this->owner)->post(route('receivables.pay', $r), ['amount' => 50000, 'payment_method' => 'cash', 'paid_at' => now()->toDateString()])->assertRedirect();
        $this->assertSame(0.0, $r->fresh()->outstanding);
        $this->actingAs($this->owner)->post(route('receivables.void', $r), ['reason' => 'x'])->assertSessionHasErrors('reason');
    }

    public function test_supplier_create_and_update(): void
    {
        $this->actingAs($this->owner)->post(route('suppliers.store'), ['name' => 'PT Baru', 'payment_terms_days' => 30])->assertRedirect();
        $s = Supplier::firstOrFail();
        $this->actingAs($this->owner)->post(route('suppliers.update', $s), ['name' => 'PT Baru 2', 'payment_terms_days' => 14])->assertRedirect();
        $this->assertSame('PT Baru 2', $s->fresh()->name);
        $this->assertSame($this->business->id, $s->business_id);
    }
}
