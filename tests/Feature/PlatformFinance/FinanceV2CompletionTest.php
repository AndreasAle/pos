<?php

namespace Tests\Feature\PlatformFinance;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformFinance\Budget;
use App\Models\PlatformFinance\BudgetVersion;
use App\Models\PlatformFinance\Entry;
use App\Models\PlatformFinance\RevenueSchedule;
use App\Models\SubscriptionPlan;
use App\Services\PlatformAiOperations;
use App\Services\PlatformFinance\AgentFinanceTools;
use App\Services\PlatformFinance\BudgetPlanner;
use App\Services\PlatformFinance\CashForecast;
use App\Services\PlatformFinance\ExpenseWorkflow;
use App\Services\PlatformFinance\FixedAssets;
use App\Services\PlatformFinance\Ledger;
use App\Services\PlatformFinance\MonthEndClose;
use App\Services\PlatformFinance\Metrics;
use App\Services\PlatformFinance\RevenueRecognition;
use App\Services\PlatformFinance\Transactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/** Finance V2 tahap akhir: skenario, forecast, retensi, perbandingan, pendapatan di muka, aset, cost center, peran, ekspor, UI modul. */
class FinanceV2CompletionTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAdmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->owner = PlatformAdmin::create(['name' => 'Owner', 'email' => 'o@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);
    }

    private function admin(string $role, string $email): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => $role, 'email' => $email, 'password' => 'password-kuat', 'role' => $role, 'is_active' => true]);
    }

    private function sub(int $amount, string $starts, string $ends, string $paid, string $status = 'active'): BusinessSubscription
    {
        $business = Business::factory()->create();
        $plan = SubscriptionPlan::query()->first() ?? SubscriptionPlan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price' => 90000, 'is_active' => true]);
        $s = BusinessSubscription::create(['business_id' => $business->id, 'subscription_plan_id' => $plan->id, 'starts_at' => $starts, 'ends_at' => $ends, 'status' => $status]);
        $s->forceFill(['amount' => $amount, 'paid_at' => $paid, 'payment_token' => 'SUB-'.$business->id.'-'.$s->id])->save();

        return $s->fresh();
    }

    public function test_annual_plan_is_deferred_and_recognised_monthly_while_monthly_plan_is_unchanged(): void
    {
        $monthly = $this->sub(90000, '2026-11-01', '2026-12-01', '2026-11-01');
        app(Transactions::class)->subscriptionPaid($monthly);
        $this->assertSame(90000.0, app(Ledger::class)->balances()['4000']['balance']);

        $annual = $this->sub(1200000, '2026-09-01', '2027-09-01', '2026-09-01');
        app(Transactions::class)->subscriptionPaid($annual);
        app(Transactions::class)->subscriptionPaid($annual->fresh()); // webhook dobel

        $this->assertSame(12, RevenueSchedule::where('business_subscription_id', $annual->id)->count());
        $this->assertSame(1200000.0, (float) RevenueSchedule::where('business_subscription_id', $annual->id)->sum('amount'));
        $b = app(Ledger::class)->balances();
        $this->assertSame(90000.0 + 300000.0, $b['4000']['balance'], 'Sep, Okt, Nov diakui; sisanya masih di muka.');
        $this->assertSame(900000.0, $b['2400']['balance']);
        $this->assertSame(0, app(RevenueRecognition::class)->recognizeDue(), 'Idempoten.');

        Carbon::setTestNow('2026-12-02 09:00:00');
        $this->assertSame(1, app(RevenueRecognition::class)->recognizeDue());
        $this->assertSame(800000.0, app(Ledger::class)->balances()['2400']['balance']);

        $mrr = app(Metrics::class)->mrr(today());
        $this->assertSame(100000.0, (float) $mrr['value'], 'Paket tahunan dihitung 1/12 per bulan di MRR (yang bulanan sudah habis).');
    }

    public function test_fixed_asset_depreciation_is_straight_line_idempotent_and_reduces_assets(): void
    {
        app(Transactions::class)->ownerCapital('2026-08-01', 20000000, '1100', 'Modal', $this->owner->id);
        $asset = app(FixedAssets::class)->acquire(['name' => 'Laptop', 'acquired_at' => '2026-08-10', 'cost' => 12000000, 'salvage_value' => 0, 'useful_life_months' => 48, 'paid_from' => '1100', 'cost_center' => 'platform'], $this->owner);
        $this->assertSame(250000.0, $asset->monthlyDepreciation());
        $this->assertSame([], app(FixedAssets::class)->depreciate('2026-08'), 'Mulai bulan setelah perolehan.');
        app(FixedAssets::class)->depreciate('2026-09');
        app(FixedAssets::class)->depreciate('2026-09');
        app(FixedAssets::class)->depreciate('2026-10');

        $b = app(Ledger::class)->balances();
        $this->assertSame(500000.0, $b['1510']['balance']);
        $this->assertSame(500000.0, $b['7300']['balance']);
        $bs = app(Ledger::class)->balanceSheet();
        $this->assertTrue($bs['balanced']);
        $this->assertSame(20000000.0 - 500000.0, $bs['total_assets']);
        $this->assertSame(500000.0, app(Ledger::class)->costCenterReport(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-31'))['totals']['platform']);
        $this->assertSame('ok', collect(app(MonthEndClose::class)->checklist('2026-10')['items'])->firstWhere('key', 'depreciation')['status']);

        $this->expectException(InvalidArgumentException::class);
        app(FixedAssets::class)->acquire(['name' => 'X', 'acquired_at' => '2026-11-01', 'cost' => 100, 'salvage_value' => 100, 'useful_life_months' => 12, 'paid_from' => '1100'], $this->owner);
    }

    public function test_missing_depreciation_blocks_month_end_close(): void
    {
        app(FixedAssets::class)->acquire(['name' => 'HP', 'acquired_at' => '2026-09-05', 'cost' => 3000000, 'useful_life_months' => 24, 'paid_from' => 'capital'], $this->owner);
        $check = app(MonthEndClose::class)->checklist('2026-10');
        $this->assertSame('fail', collect($check['items'])->firstWhere('key', 'depreciation')['status']);
        $this->assertFalse($check['can_close']);
    }

    public function test_budget_scenarios_are_ordered_and_approval_activates_budget(): void
    {
        $tx = app(Transactions::class);
        foreach (['2026-08', '2026-09', '2026-10'] as $m) {
            $tx->expense($m.'-05', '5000', 150000, '1100', 'Hosting', $this->owner->id);
            $tx->expense($m.'-06', '6000', 300000, '1100', 'Meta', $this->owner->id);
        }
        $this->sub(90000, '2026-11-01', '2026-12-01', '2026-11-01');

        $cmp = app(BudgetPlanner::class)->compare('2026-12', 6);
        $this->assertGreaterThan($cmp['conservative']['totals']['net'], $cmp['base']['totals']['net']);
        $this->assertGreaterThanOrEqual($cmp['base']['totals']['revenue'], $cmp['growth']['totals']['revenue']);
        $this->assertSame(150000.0, app(BudgetPlanner::class)->project('base', '2026-12', 1)['lines']['2026-12']['5000']);

        $v = app(BudgetPlanner::class)->draft('Q1', 'base', '2026-12', 3, ['arpa' => 100000], 'admin', $this->owner->id);
        $this->assertSame(0, Budget::count(), 'Draf tidak mengubah anggaran aktif.');
        app(BudgetPlanner::class)->approve($v, $this->owner);
        $this->assertSame('approved', $v->fresh()->status);
        $this->assertSame(150000.0, (float) Budget::where('period', '2026-12')->whereHas('account', fn ($q) => $q->where('code', '5000'))->value('amount'));

        $v2 = app(BudgetPlanner::class)->draft('Q1 revisi', 'conservative', '2026-12', 3, [], 'admin', $this->owner->id);
        app(BudgetPlanner::class)->approve($v2, $this->owner);
        $this->assertSame('archived', $v->fresh()->status);

        $rolling = app(BudgetPlanner::class)->rollingForecast();
        $this->assertSame(['aktual', 'aktual', 'aktual'], array_column(array_slice($rolling['rows'], 0, 3), 'type'));
        $this->assertSame('anggaran', collect($rolling['rows'])->firstWhere('period', '2026-12')['type']);
    }

    public function test_luna_can_only_draft_budgets_and_nanda_cannot(): void
    {
        app(PlatformAiOperations::class)->ensureAgents();
        $luna = PlatformAiAgent::where('key', 'finance')->firstOrFail();
        $nanda = PlatformAiAgent::where('key', 'accounting')->firstOrFail();
        $tools = app(AgentFinanceTools::class);
        $this->assertFalse($tools->draftBudget($nanda, ['scenario' => 'base', 'period_from' => '2026-12', 'months' => 3])['ok']);
        $r = $tools->draftBudget($luna, ['scenario' => 'growth', 'period_from' => '2026-12', 'months' => 3]);
        $this->assertTrue($r['ok']);
        $this->assertSame('draft', BudgetVersion::find($r['version_id'])->status);
        $this->assertSame(0, Budget::count());
        $tools->draftBudget($luna, ['scenario' => 'base', 'period_from' => '2026-12', 'months' => 1]);
        $tools->draftBudget($luna, ['scenario' => 'base', 'period_from' => '2026-12', 'months' => 1]);
        $this->assertStringContainsString('Batas 3', $tools->draftBudget($luna, ['scenario' => 'base', 'period_from' => '2026-12', 'months' => 1])['error']);
        $this->assertArrayHasKey('rows', $tools->metrics(['metric' => 'cash_forecast']));
        $this->assertArrayHasKey('base', $tools->metrics(['metric' => 'scenarios']));
    }

    public function test_cash_forecast_includes_midtrans_and_bills_and_flags_negative_month(): void
    {
        app(Transactions::class)->ownerCapital('2026-11-01', 500000, '1100', 'Modal', $this->owner->id);
        $sub = $this->sub(90000, '2026-11-01', '2026-12-01', '2026-11-02');
        app(Transactions::class)->subscriptionPaid($sub);
        $x = app(ExpenseWorkflow::class)->submit(['expense_date' => '2026-11-03', 'account_code' => '7000', 'paid_from' => 'payable', 'due_date' => '2026-12-10', 'amount' => 2000000, 'description' => 'Freelance'], 'admin', $this->owner->id);
        app(ExpenseWorkflow::class)->approveAndPost($x, $this->owner);

        $f = app(CashForecast::class)->run(3);
        $this->assertSame(500000.0, $f['opening_cash']);
        $this->assertSame(90000.0, $f['midtrans_pending']);
        $this->assertSame(2000000.0, collect($f['rows'])->firstWhere('period', '2026-12')['bills']);
        $this->assertSame('2026-12', $f['first_negative_month']);
    }

    public function test_revenue_retention_and_comparison(): void
    {
        $a = $this->sub(100000, '2026-10-01', '2026-10-31', '2026-10-01', 'expired');   // churn di akhir Okt
        $b = $this->sub(300000, '2026-10-01', '2026-12-31', '2026-10-01'); // 3 bulan = 100rb/bulan
        $retention = app(Metrics::class)->revenueRetention('2026-10');
        $this->assertSame('ok', $retention['status']);
        $this->assertSame(200000.0, $retention['start_mrr']);

        $tx = app(Transactions::class);
        $tx->otherIncome('2026-10-10', 100000, '1100', '4100', 'Setup', $this->owner->id);
        $tx->otherIncome('2026-11-10', 150000, '1100', '4100', 'Setup', $this->owner->id);
        $c = app(Metrics::class)->comparison('2026-11');
        $this->assertSame(150000.0, $c['mom']['net_revenue']['current']);
        $this->assertSame(0.5, $c['mom']['net_revenue']['change_pct']);
        $this->assertSame(250000.0, $c['ytd']['net_revenue']['current']);
        $this->assertNull($c['ytd']['net_revenue']['change_pct'], 'Tahun lalu kosong: persentase tidak dikarang.');
    }

    public function test_approval_matrix_limits_finance_role_and_blocks_self_approval(): void
    {
        $finance = $this->admin('finance', 'f@yoka.test');
        $wf = app(ExpenseWorkflow::class);
        $own = $wf->submit(['expense_date' => '2026-11-10', 'account_code' => '7100', 'paid_from' => '1100', 'amount' => 100000, 'description' => 'Tool'], 'admin', $finance->id);
        try {
            $wf->approveAndPost($own, $finance);
            $this->fail('Finance tidak boleh menyetujui pengajuannya sendiri');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Pemisahan tugas', $e->getMessage());
        }
        $big = $wf->submit(['expense_date' => '2026-11-10', 'account_code' => '7000', 'paid_from' => '1100', 'amount' => 6000000, 'description' => 'Gaji'], 'admin', $this->owner->id);
        try {
            $wf->approveAndPost($big, $finance);
            $this->fail('Di atas batas');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('super admin', $e->getMessage());
        }
        $small = $wf->submit(['expense_date' => '2026-11-10', 'account_code' => '7100', 'paid_from' => '1100', 'amount' => 200000, 'description' => 'Tool 2'], 'admin', $this->owner->id);
        $this->assertSame('posted', $wf->approveAndPost($small, $finance)->status);
        $this->assertSame('posted', $wf->approveAndPost($big, $this->owner)->status);
    }

    public function test_auditor_can_view_and_export_but_not_write(): void
    {
        $auditor = $this->admin('finance_auditor', 'a@yoka.test');
        $as = $this->actingAs($auditor, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
        $as->get(route('platform.finance'))->assertOk()->assertSee('Mode auditor');
        $as->get(route('platform.finance.export', ['report' => 'journal']))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $as->post(route('platform.finance.store'), ['kind' => 'capital', 'date' => '2026-11-01', 'amount' => 1000, 'paid_from' => '1100', 'description' => 'x'])->assertForbidden();
        $as->post(route('platform.finance.budget-versions.store'), ['scenario' => 'base', 'period_from' => '2026-12', 'months' => 1])->assertForbidden();
        $this->assertSame(0, Entry::count());
        $this->assertFalse($auditor->canViewFinancials(), 'Auditor tidak otomatis melihat halaman keuangan lain.');
    }

    public function test_every_export_and_module_renders(): void
    {
        app(Transactions::class)->ownerCapital('2026-11-01', 1000000, '1100', '=HYPERLINK("x")', $this->owner->id);
        $as = $this->actingAs($this->owner, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
        $page = $as->get(route('platform.finance'))->assertOk();
        foreach (['Ringkasan', 'Anggaran & forecast', 'Rolling forecast 12 bulan', 'Peramalan kas', 'Aset tetap', 'Pendapatan diterima di muka', 'Retensi pendapatan', 'Ekspor laporan', 'Beban per cost center'] as $text) {
            $page->assertSee($text, false);
        }
        foreach (['income', 'balance', 'trial', 'journal', 'comparison', 'budget', 'cash_forecast', 'payables', 'cost_centers', 'assets'] as $r) {
            $res = $as->get(route('platform.finance.export', ['report' => $r, 'period' => '2026-11']))->assertOk();
            if ($r === 'journal') {
                $this->assertStringContainsString("'=HYPERLINK", $res->streamedContent(), 'Formula injection dinetralkan.');
            }
        }
        $as->get(route('platform.finance.export', ['report' => 'rahasia']))->assertNotFound();
    }

    public function test_reversal_keeps_cost_center_and_unknown_cost_center_is_rejected(): void
    {
        $e = app(Transactions::class)->expense('2026-11-05', '5100', 80000, '1100', 'OpenAI', $this->owner->id, null, null, 'ai');
        app(Ledger::class)->reverse($e, 'salah', $this->owner->id);
        $this->assertSame(0.0, app(Ledger::class)->costCenterReport(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'))['totals']['ai']);
        $this->expectException(InvalidArgumentException::class);
        app(Transactions::class)->expense('2026-11-05', '5100', 1, '1100', 'x', null, null, null, 'rahasia');
    }
}
