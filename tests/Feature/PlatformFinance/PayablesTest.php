<?php

namespace Tests\Feature\PlatformFinance;

use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformFinance\AuditEvent;
use App\Models\PlatformFinance\Entry;
use App\Models\PlatformFinance\Expense;
use App\Services\PlatformAiOperations;
use App\Services\PlatformFinance\AgentFinanceTools;
use App\Services\PlatformFinance\ExpenseWorkflow;
use App\Services\PlatformFinance\Ledger;
use App\Services\PlatformFinance\MonthEndClose;
use App\Services\PlatformFinance\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/** Finance V2: tagihan (hutang usaha) dipisah dari pembayarannya. */
class PayablesTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAdmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->owner = PlatformAdmin::create(['name' => 'Owner', 'email' => 'o@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);
    }

    private function wf(): ExpenseWorkflow
    {
        return app(ExpenseWorkflow::class);
    }

    private function bill(array $over = []): Expense
    {
        $x = $this->wf()->submit($over + ['expense_date' => '2026-11-01', 'account_code' => '5000', 'paid_from' => 'payable', 'due_date' => '2026-11-15', 'amount' => 450000, 'description' => 'Hostinger 3 bulan'], 'admin', $this->owner->id);

        return $this->wf()->approveAndPost($x, $this->owner);
    }

    public function test_bill_recognises_expense_and_payable_without_touching_cash(): void
    {
        $x = $this->bill();
        $this->assertSame('unpaid', $x->payment_status);
        $b = app(Ledger::class)->balances();
        $this->assertSame(450000.0, $b['5000']['balance']);
        $this->assertSame(450000.0, $b['2000']['balance']);
        $this->assertSame(0.0, $b['1100']['balance']);
    }

    public function test_payment_clears_payable_once_and_hits_cash_in_payment_period(): void
    {
        $x = $this->bill();
        $this->wf()->payBill($x, '1100', '2026-11-18', $this->owner);
        $this->wf()->payBill($x->fresh(), '1100', '2026-11-18', $this->owner);

        $b = app(Ledger::class)->balances();
        $this->assertSame(0.0, $b['2000']['balance']);
        $this->assertSame(-450000.0, $b['1100']['balance']);
        $this->assertSame(450000.0, $b['5000']['balance'], 'Beban tidak dobel saat dibayar.');
        $this->assertSame(2, Entry::count());
        $this->assertSame('paid', $x->fresh()->payment_status);
        $this->assertSame(1, AuditEvent::where('action', 'expense.paid')->count());
    }

    public function test_invalid_payments_are_rejected(): void
    {
        $x = $this->bill();
        $direct = $this->wf()->approveAndPost($this->wf()->submit(['expense_date' => '2026-11-02', 'account_code' => '6000', 'paid_from' => '1100', 'amount' => 100000, 'description' => 'Meta'], 'admin', $this->owner->id), $this->owner);
        $support = PlatformAdmin::create(['name' => 'S', 'email' => 's@yoka.test', 'password' => 'password-kuat', 'role' => 'support', 'is_active' => true]);

        foreach ([
            fn () => $this->wf()->payBill($direct, '1100', '2026-11-18', $this->owner),   // sudah lunas saat dicatat
            fn () => $this->wf()->payBill($x, '1100', '2026-10-30', $this->owner),        // sebelum tanggal tagihan
            fn () => $this->wf()->payBill($x, '1100', '2026-11-25', $this->owner),        // masa depan
            fn () => $this->wf()->payBill($x, '9999', '2026-11-18', $this->owner),        // akun tak dikenal
            fn () => $this->wf()->payBill($x, '1100', '2026-11-18', $support),            // bukan admin keuangan
        ] as $i => $attempt) {
            try {
                $attempt();
                $this->fail("Percobaan {$i} harus ditolak");
            } catch (InvalidArgumentException) {
                $this->assertSame('unpaid', $x->fresh()->payment_status);
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $this->wf()->submit(['expense_date' => '2026-11-10', 'account_code' => '5000', 'paid_from' => 'payable', 'due_date' => '2026-11-01', 'amount' => 1, 'description' => 'x'], 'admin', $this->owner->id);
    }

    public function test_aging_buckets_and_overdue_flags_in_review_and_close(): void
    {
        $this->bill(['expense_date' => '2026-09-01', 'due_date' => '2026-09-10', 'amount' => 100000, 'description' => 'lama']);   // 71 hari
        $this->bill(['expense_date' => '2026-11-01', 'due_date' => '2026-11-10', 'amount' => 200000, 'description' => 'baru']);   // 10 hari
        $this->bill(['expense_date' => '2026-11-15', 'due_date' => '2026-12-15', 'amount' => 300000, 'description' => 'belum']);

        $ap = $this->wf()->payables();
        $this->assertSame(600000.0, $ap['total']);
        $this->assertSame(300000.0, $ap['overdue']);
        $this->assertSame(['belum_jatuh_tempo' => 300000.0, '1_30' => 200000.0, '31_60' => 0.0, 'lebih_60' => 100000.0], $ap['buckets']);

        $this->assertContains('Tagihan lewat jatuh tempo', array_column(app(Review::class)->run()['findings'], 'issue'));
        $this->assertSame('warn', collect(app(MonthEndClose::class)->checklist('2026-10')['items'])->firstWhere('key', 'payables')['status']);
    }

    public function test_nanda_can_draft_a_bill_and_read_payables(): void
    {
        app(PlatformAiOperations::class)->ensureAgents();
        $nanda = PlatformAiAgent::where('key', 'accounting')->firstOrFail();
        $tools = app(AgentFinanceTools::class);
        $r = $tools->draftExpense($nanda, ['expense_date' => '2026-11-19', 'account_code' => '5100', 'paid_from' => 'payable', 'due_date' => '2026-12-05', 'amount' => 800000, 'description' => 'Tagihan OpenAI', 'vendor' => 'OpenAI']);
        $this->assertTrue($r['ok']);
        $this->assertSame('2026-12-05', Expense::find($r['draft_id'])->due_date->toDateString());
        $this->wf()->approveAndPost(Expense::find($r['draft_id']), $this->owner);
        $this->assertSame(800000.0, $tools->ledger(['report' => 'payables'])['total']);
    }

    public function test_page_lists_bill_and_pays_via_http(): void
    {
        $x = $this->bill();
        $as = $this->actingAs($this->owner, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
        $as->get(route('platform.finance'))->assertOk()->assertSee('Hutang usaha')->assertSee('Hostinger 3 bulan');
        $as->post(route('platform.finance.expenses.pay', $x), ['paid_with' => '1100', 'date' => '2026-11-20'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $x->fresh()->payment_status);
    }
}
