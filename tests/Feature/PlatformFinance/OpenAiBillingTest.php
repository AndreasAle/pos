<?php

namespace Tests\Feature\PlatformFinance;

use App\Models\PlatformAdmin;
use App\Models\PlatformFinance\Expense;
use App\Services\PlatformFinance\ExpenseWorkflow;
use App\Services\PlatformFinance\Ledger;
use App\Services\PlatformFinance\OpenAiBilling;
use App\Services\PlatformFinance\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/** Biaya OpenAI persis dari Costs API, saldo kredit, pembukuan dibayar di muka. */
class OpenAiBillingTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAdmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        config(['services.openai_admin.key' => 'sk-admin-test', 'services.openai_admin.url' => 'https://api.openai.com/v1']);
        $this->owner = PlatformAdmin::create(['name' => 'Owner', 'email' => 'o@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);
    }

    private function bucket(string $date, array $results): array
    {
        return ['object' => 'bucket', 'start_time' => Carbon::parse($date, 'UTC')->timestamp, 'end_time' => Carbon::parse($date, 'UTC')->addDay()->timestamp,
            'results' => array_map(fn ($r) => ['object' => 'organization.costs.result', 'amount' => ['value' => $r[1], 'currency' => 'usd'], 'line_item' => $r[0], 'project_id' => 'proj_1'], $results)];
    }

    private function fakeCosts(): void
    {
        $page1 = ['object' => 'page', 'data' => [$this->bucket('2026-10-30', [['gpt-4.1-mini, input', 0.40], ['gpt-4.1-mini, output', 0.60]])], 'has_more' => true, 'next_page' => 'p2'];
        $page2 = ['object' => 'page', 'data' => [$this->bucket('2026-11-10', [['gpt-4.1-mini, input', 0.25]]), $this->bucket('2026-11-18', [['gpt-4.1-mini, output', 0.0812]])], 'has_more' => false, 'next_page' => null];
        Http::fake(['api.openai.com/v1/organization/costs*' => fn (Request $r) => Http::response(str_contains($r->url(), 'page=p2') ? $page2 : $page1)]);
    }

    public function test_sync_reads_exact_costs_with_admin_key_paginates_and_is_idempotent(): void
    {
        $this->fakeCosts();
        $billing = app(OpenAiBilling::class);
        $this->assertSame(4, $billing->sync(31));
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer sk-admin-test') && str_contains($r->url(), 'bucket_width=1d'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'page=p2'));

        $billing->sync(31);
        $this->assertSame(4, \DB::table('platform_fin_ai_usage')->count(), 'Sinkron ulang memperbarui, tidak dobel.');
        $this->assertSame(0.3312, $billing->status()['this_month_usd']);
    }

    public function test_balance_matches_dashboard_math_and_low_balance_is_flagged(): void
    {
        $this->fakeCosts();
        app(OpenAiBilling::class)->sync(31);
        $billing = app(OpenAiBilling::class);
        $billing->recordCredit(['kind' => 'opening', 'credited_at' => '2026-11-01', 'amount_usd' => 2.66, 'amount_idr' => 43000], $this->owner);

        $s = $billing->status();
        $this->assertSame(2.33, $s['balance_usd'], 'US$2,66 - 0,25 - 0,0812 = US$2,33 (biaya Oktober sebelum saldo awal tidak dihitung).');
        $this->assertTrue($s['low']);
        $this->assertContains('Saldo kredit OpenAI rendah', array_column(app(Review::class)->run()['findings'], 'issue'));

        $billing->recordCredit(['kind' => 'purchase', 'credited_at' => '2026-11-19', 'amount_usd' => 10, 'amount_idr' => 165000, 'paid_from' => '1100', 'reference' => 'INV-OAI-1'], $this->owner);
        $this->assertSame(12.33, $billing->status()['balance_usd']);
        $this->assertFalse($billing->status()['low']);
        $b = app(Ledger::class)->balances();
        $this->assertSame(208000.0, $b['1400']['balance'], 'Kredit = biaya dibayar di muka, bukan langsung beban.');
        $this->assertSame(0.0, $b['5100']['balance']);
    }

    public function test_monthly_ai_expense_is_a_draft_from_exact_usd_times_paid_rate_and_only_once(): void
    {
        $this->fakeCosts();
        $billing = app(OpenAiBilling::class);
        $billing->sync(31);
        $billing->recordCredit(['kind' => 'purchase', 'credited_at' => '2026-10-01', 'amount_usd' => 10, 'amount_idr' => 160000, 'paid_from' => '1100'], $this->owner);

        $x = $billing->draftMonthlyExpense('2026-10', 'admin', $this->owner->id);
        $this->assertSame('submitted', $x->status);
        $this->assertSame(16000.0, (float) $x->amount, 'US$1,00 x Rp16.000.');
        $this->assertSame('ai', $x->cost_center);
        app(ExpenseWorkflow::class)->approveAndPost($x, $this->owner);
        $b = app(Ledger::class)->balances();
        $this->assertSame(16000.0, $b['5100']['balance']);
        $this->assertSame(144000.0, $b['1400']['balance']);

        $this->expectException(InvalidArgumentException::class);
        $billing->draftMonthlyExpense('2026-10', 'admin', $this->owner->id);
    }

    public function test_rejected_key_and_missing_key_give_clear_errors(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'nope']], 401)]);
        try {
            app(OpenAiBilling::class)->sync();
            $this->fail('Harus gagal');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Admin key ditolak', $e->getMessage());
        }
        config(['services.openai_admin.key' => null]);
        $this->assertFalse(app(OpenAiBilling::class)->status()['configured']);
        $this->expectExceptionMessage('OPENAI_ADMIN_KEY');
        app(OpenAiBilling::class)->sync();
    }

    public function test_page_shows_card_and_records_credit_via_http(): void
    {
        $as = $this->actingAs($this->owner, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
        $as->get(route('platform.finance'))->assertOk()->assertSee('Kredit OpenAI');
        $as->post(route('platform.finance.openai.credit'), ['kind' => 'opening', 'credited_at' => '2026-11-20', 'amount_usd' => 2.33, 'amount_idr' => 38000])->assertSessionHasNoErrors();
        $this->assertSame(2.33, app(OpenAiBilling::class)->status()['balance_usd']);
        $as->post(route('platform.finance.openai.credit'), ['kind' => 'opening', 'credited_at' => '2026-11-20', 'amount_usd' => 1, 'amount_idr' => 1])->assertSessionHasErrors('finance');
        $this->assertSame(0, Expense::count());
    }
}
