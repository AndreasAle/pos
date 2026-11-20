<?php

namespace Tests\Feature\PlatformFinance;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\PlatformAdmin;
use App\Models\PlatformFinance\Entry;
use App\Models\SubscriptionPlan;
use App\Services\PlatformAgents\AgentBrain;
use App\Services\PlatformFinance\AgentFinanceTools;
use App\Services\PlatformFinance\Knowledge;
use App\Services\PlatformFinance\Ledger;
use App\Services\PlatformFinance\Metrics;
use App\Services\PlatformFinance\Transactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Akuntansi manajerial (Vol. 2), metrik SaaS, keamanan, dan skenario bisnis lengkap.
 * Semua angka dihitung kode, bukan model AI.
 */
class MetricsAndAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-28 10:00:00');
    }

    // ── 15-18: Managerial Accounting ──────────────────────────────────────────

    public function test_15_16_contribution_margin_and_break_even_for_yoka(): void
    {
        // Paket Rp 99.000; biaya variabel per toko (AI + fee + WA) Rp 19.000; biaya tetap Rp 2.400.000/bulan.
        $cm = Metrics::contributionMargin(99000, 19000);
        $this->assertSame(80000.0, $cm['value']);
        $this->assertSame(0.8081, $cm['ratio']);

        $be = Metrics::breakEven(2400000, 99000, 19000);
        $this->assertSame(30.0, $be['value']);
        $this->assertSame(30, $be['units_rounded_up']);
        $this->assertSame(2970000.0, $be['revenue']);

        $this->assertSame('insufficient_data', Metrics::breakEven(1000000, 50000, 60000)['status'], 'CM negatif: impas tidak tercapai.');
        $this->assertSame(40.0, Metrics::targetProfitUnits(2400000, 800000, 99000, 19000)['value']);
        $this->assertSame(25.0, Metrics::marginOfSafety(3960000, 2970000)['percent']);
        $this->assertSame(4.0, Metrics::operatingLeverage(3200000, 800000)['value']);
    }

    public function test_17_18_budget_variance_with_favorable_and_unfavorable(): void
    {
        $over = Metrics::variance(1000000, 1500000, true);
        $this->assertSame(500000.0, $over['value']);
        $this->assertFalse($over['favorable']);
        $this->assertSame(50.0, $over['percent']);
        $this->assertTrue(Metrics::variance(1000000, 800000, true)['favorable']);
        $this->assertTrue(Metrics::variance(4000000, 4500000, false)['favorable'], 'Pendapatan di atas anggaran = F.');

        $tx = app(Transactions::class);
        $tx->expense('2026-11-10', '6000', 1500000, '1100', 'Meta Ads');
        \App\Models\PlatformFinance\Budget::create(['period' => '2026-11', 'platform_fin_account_id' => \App\Models\PlatformFinance\Account::where('code', '6000')->value('id'), 'amount' => 1000000]);
        $rows = app(Metrics::class)->budgetVsActual('2026-11')['rows'];
        $this->assertSame('tidak menguntungkan (U)', $rows[0]['label']);
    }

    public function test_capital_budgeting_payback_and_npv(): void
    {
        $this->assertSame(2.5, Metrics::paybackPeriod(10000000, 4000000)['value']);
        $npv = Metrics::netPresentValue(10000000, [4000000, 4000000, 4000000], 0.1);
        $this->assertEqualsWithDelta(-52592.04, $npv['value'], 0.01);
        $this->assertFalse($npv['accept']);
    }

    // ── 19-23: SaaS metrics ───────────────────────────────────────────────────

    public function test_19_mrr_arr_arpa_count_only_paying_active_subscriptions(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Pro', 'slug' => 'pro', 'price' => 99000, 'max_outlets' => 1, 'max_users' => 3]);
        $this->paid($plan, 99000, '2026-11-02', '2026-12-02');
        $this->paid($plan, 149000, '2026-11-05', '2026-12-05');
        $trial = Business::factory()->create();
        BusinessSubscription::create(['business_id' => $trial->id, 'subscription_plan_id' => $plan->id, 'starts_at' => now(), 'ends_at' => now()->addDays(7), 'status' => 'trial']);

        $m = app(Metrics::class)->mrr();
        $this->assertSame(248000.0, $m['value']);
        $this->assertSame(2976000.0, $m['arr']);
        $this->assertSame(2, $m['paying_accounts'], 'Toko trial bukan pelanggan berbayar.');
        $this->assertSame(124000.0, $m['arpa']);
    }

    public function test_20_cac_needs_both_spend_and_new_customers(): void
    {
        $metrics = app(Metrics::class);
        $from = Carbon::parse('2026-11-01');
        $to = Carbon::parse('2026-11-30');
        $this->assertSame('insufficient_data', $metrics->cac($from, $to)['status']);

        app(Transactions::class)->expense('2026-11-03', '6000', 600000, '1100', 'Meta');
        $this->assertStringContainsString('Tidak ada pelanggan berbayar baru', $metrics->cac($from, $to)['reason']);

        $plan = SubscriptionPlan::create(['name' => 'Pro', 'slug' => 'pro', 'price' => 99000, 'max_outlets' => 1, 'max_users' => 3]);
        $this->paid($plan, 99000, '2026-11-04', '2026-12-04');
        $this->paid($plan, 99000, '2026-11-06', '2026-12-06');
        $this->assertSame(300000.0, $metrics->cac($from, $to)['value']);
    }

    public function test_21_23_ltv_and_churn_refuse_to_guess_without_history(): void
    {
        $ltv = app(Metrics::class)->ltv('2026-11');
        $this->assertSame('insufficient_data', $ltv['status']);
        $this->assertNull($ltv['value']);
    }

    public function test_22_burn_and_runway(): void
    {
        $tx = app(Transactions::class);
        $tx->ownerCapital('2026-11-01', 6000000, '1100');
        $tx->expense('2026-11-05', '5000', 1000000, '1100', 'Server');
        $tx->expense('2026-11-06', '6000', 1000000, '1100', 'Meta');
        $r = app(Metrics::class)->runway(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));
        $this->assertSame(2000000.0, $r['monthly_net_burn']);
        $this->assertSame(4000000.0, $r['cash']);
        $this->assertSame(2.0, $r['value']);
    }

    // ── 24-27: keamanan, isolasi, satu sumber angka ───────────────────────────

    public function test_24_ai_tools_are_read_only(): void
    {
        $tools = collect((new \ReflectionClass(AgentFinanceTools::class))->getMethods(\ReflectionMethod::IS_PUBLIC))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['__construct', 'ledger', 'metrics', 'draftExpense', 'draftBudget'], $tools, 'Tulis agen hanya draf (biaya Nanda, anggaran Luna) yang menunggu persetujuan manusia.');

        $names = collect((fn () => $this->definitions())->call(app(AgentBrain::class)))->pluck('function.name')->all();
        foreach (['finance_ledger', 'finance_metrics', 'finance_knowledge'] as $t) {
            $this->assertContains($t, $names);
        }
        foreach ($names as $n) {
            $this->assertDoesNotMatchRegularExpression('/post|reverse|transfer|pay|refund|delete|close_period/i', $n, "Agen tidak boleh punya tool tulis keuangan: {$n}");
        }
    }

    public function test_24b_only_finance_roles_can_open_or_write_the_finance_page(): void
    {
        $support = PlatformAdmin::create(['name' => 'S', 'email' => 's@yoka.test', 'password' => 'password-kuat', 'role' => 'support', 'is_active' => true]);
        $this->actingAs($support, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp])->get(route('platform.finance'))->assertForbidden();
        $this->actingAs($support, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp])
            ->post(route('platform.finance.store'), ['kind' => 'capital', 'date' => '2026-11-01', 'amount' => 1000, 'paid_from' => '1100', 'description' => 'x'])->assertForbidden();
        $this->assertSame(0, Entry::count());

        $finance = PlatformAdmin::create(['name' => 'F', 'email' => 'f@yoka.test', 'password' => 'password-kuat', 'role' => 'finance', 'is_active' => true]);
        $as = $this->actingAs($finance, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
        $as->get(route('platform.finance'))->assertOk()->assertSee('Keuangan Yoka');
        $as->post(route('platform.finance.store'), ['kind' => 'expense', 'date' => '2026-11-03', 'amount' => 300000, 'account' => '6000', 'paid_from' => 'reimbursement', 'description' => 'Meta pakai kartu pribadi', 'invoice_no' => 'INV-1', 'approve_now' => '1'])->assertRedirect();
        $this->assertSame(0, Entry::count(), 'Pemisahan tugas: finance tidak bisa langsung menyetujui pengajuannya sendiri.');
        $expense = \App\Models\PlatformFinance\Expense::firstOrFail();
        $this->assertSame('submitted', $expense->status);
        $owner = PlatformAdmin::create(['name' => 'O', 'email' => 'o2@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($owner, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp])->post(route('platform.finance.expenses.approve', $expense))->assertRedirect();
        $this->assertSame(1, Entry::count());
        $this->assertSame($owner->id, Entry::first()->created_by);
    }

    public function test_25_merchant_pos_data_never_enters_the_platform_ledger(): void
    {
        $this->paid(SubscriptionPlan::create(['name' => 'Pro', 'slug' => 'pro', 'price' => 99000, 'max_outlets' => 1, 'max_users' => 3]), 99000, '2026-11-04', '2026-12-04');
        // Toko klien punya biaya dan penjualan sendiri; buku besar Yoka tetap kosong sampai ada transaksi Yoka.
        $this->assertSame(0.0, app(Ledger::class)->incomeStatement(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'))['net_revenue']);
        $this->assertStringContainsString('terpisah dari data toko klien', app(AgentFinanceTools::class)->ledger(['report' => 'trial_balance'])['sumber_data']);
        $this->assertArrayHasKey('peringatan', app(AgentFinanceTools::class)->ledger(['report' => 'income_statement']), 'Buku besar kosong = agen diperingatkan agar tidak menyimpulkan.');
    }

    public function test_26_nanda_and_luna_read_the_same_numbers(): void
    {
        app(Transactions::class)->expense('2026-11-03', '6000', 500000, '1100', 'Meta');
        $tools = app(AgentFinanceTools::class);
        $nanda = $tools->ledger(['report' => 'income_statement', 'period' => '2026-11']);
        $luna = $tools->metrics(['metric' => 'snapshot', 'period' => '2026-11'])['income_statement'];
        $this->assertSame($nanda['net_income'], $luna['net_income']);
        $this->assertSame(500000.0, $luna['marketing']);
    }

    public function test_27_hypothetical_inputs_are_labelled_as_assumptions(): void
    {
        $r = app(AgentFinanceTools::class)->metrics(['metric' => 'break_even', 'fixed_costs' => 2400000, 'price' => 99000, 'variable_cost' => 19000]);
        $this->assertStringContainsString('asumsi', $r['angka_input_dari_pengguna']);
        $this->assertNull(app(AgentFinanceTools::class)->metrics(['metric' => 'saas'])['angka_input_dari_pengguna']);
    }

    public function test_knowledge_retrieval_cites_book_pages_per_agent(): void
    {
        $owner = Knowledge::lookup('modal pemilik dan prive itu pendapatan bukan?', 'nanda');
        $this->assertStringContainsString('FA', $owner[0]['sumber']);
        $be = Knowledge::lookup('berapa titik impas break even pelanggan', 'luna');
        $this->assertSame('Titik impas (CVP)', $be[0]['konsep']);
        $this->assertStringContainsString('MA 3.2', $be[0]['sumber']);
        $this->assertStringContainsString('BEP', $be[0]['rumus']);
    }

    // ── Midtrans webhook → buku besar ────────────────────────────────────────

    public function test_midtrans_webhook_posts_revenue_once_and_never_breaks_activation(): void
    {
        $plan = SubscriptionPlan::create(['name' => 'Pro', 'slug' => 'pro', 'price' => 99000, 'max_outlets' => 1, 'max_users' => 3]);
        $b = Business::factory()->create();
        $orderId = 'SUB-'.$b->id.'-20261128100000-ABCDE';
        $sub = BusinessSubscription::create(['business_id' => $b->id, 'subscription_plan_id' => $plan->id, 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'status' => 'pending', 'amount' => 99000, 'payment_token' => $orderId]);
        config(['midtrans.signature_secret' => 'sk-test']);
        $payload = ['order_id' => $orderId, 'status_code' => '200', 'gross_amount' => '99000.00', 'transaction_status' => 'settlement'];
        $payload['signature_key'] = hash('sha512', $orderId.'200'.'99000.00'.'sk-test');

        $this->postJson(route('midtrans.callback'), $payload)->assertOk();
        $this->postJson(route('midtrans.callback'), $payload)->assertOk();
        $this->assertSame('active', $sub->fresh()->status);
        $this->assertSame(1, Entry::where('idempotency_key', 'midtrans:subscription:'.$sub->id)->count());
        $this->assertSame(99000.0, app(Ledger::class)->balances()['4000']['balance']);
    }

    // ── Skenario lengkap V2 §19 ───────────────────────────────────────────────

    public function test_acceptance_full_month_owner_capital_ads_hosting_midtrans_payout(): void
    {
        $ledger = app(Ledger::class);
        $tx = app(Transactions::class);

        $tx->ownerCapital('2026-11-01', 2000000, '1100', 'Setoran modal pemilik');      // 1
        $tx->expense('2026-11-02', '6000', 300000, '1100', 'Meta Ads', null, 'INV-M1'); // 2
        $tx->expense('2026-11-02', '5000', 150000, '1100', 'Hosting', null, 'INV-H1');  // 3
        $ledger->post('2026-11-05', 'Langganan Pro 1 bulan', [['1200', 90000, 0], ['4000', 0, 90000]], 'midtrans', 'midtrans:subscription:500'); // 4-5
        $tx->midtransPayout('2026-11-07', 90000, 630, '1100', 'STL-500');               // 6-7

        $is = $ledger->incomeStatement(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));
        $bs = $ledger->balanceSheet();
        $cf = $ledger->cashFlow(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));

        $this->assertSame(90000.0, $is['net_revenue'], 'Modal dan pencairan bukan pendapatan.');
        $this->assertSame(300000.0, $is['marketing']);
        $this->assertSame(150630.0, $is['cost_of_revenue'], 'Hosting + fee Midtrans.');
        $this->assertSame(-360630.0, $is['net_income']);
        $this->assertTrue($bs['balanced']);
        $this->assertSame(1639370.0, $bs['total_assets']);
        $this->assertSame(1639370.0, $cf['closing_cash']);
        $this->assertSame(2000000.0, $cf['financing']);
        $this->assertTrue($ledger->trialBalance()['balanced']);                          // 8
        $this->assertSame([], array_values(array_filter(app(\App\Services\PlatformFinance\Review::class)->run()['findings'], fn ($f) => $f['severity'] === 'high')));
        \App\Models\PlatformFinance\Budget::create(['period' => '2026-12', 'platform_fin_account_id' => \App\Models\PlatformFinance\Account::where('code', '6000')->value('id'), 'amount' => 500000]); // 10
        $snapshot = app(AgentFinanceTools::class)->metrics(['metric' => 'snapshot', 'period' => '2026-11']); // 9, 12
        $this->assertSame(-360630.0, $snapshot['income_statement']['net_income']);
        $this->assertSame(1639370.0, $snapshot['cash_and_bank']);
        $this->assertSame(0.0, (float) $snapshot['midtrans_unsettled']);
    }

    private function paid(SubscriptionPlan $plan, float $amount, string $paidAt, string $endsAt): BusinessSubscription
    {
        $b = Business::factory()->create();

        return BusinessSubscription::create(['business_id' => $b->id, 'subscription_plan_id' => $plan->id, 'starts_at' => $paidAt, 'ends_at' => $endsAt,
            'status' => 'active', 'amount' => $amount, 'paid_at' => $paidAt, 'payment_token' => 'tok-'.$b->id]);
    }
}
