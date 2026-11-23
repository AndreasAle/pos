<?php

namespace Tests\Feature\PlatformFinance;

use App\Models\AiCreditEntry;
use App\Models\AiCreditTopup;
use App\Models\AiCreditWallet;
use App\Models\Business;
use App\Services\PlatformAgents\AgentBrain;
use App\Services\PlatformFinance\AiCreditEconomics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Luna membaca ekonomi Kredit Yoka AI: top-up bukan pendapatan penuh, kredit gratis = biaya, margin per paket. */
class AiCreditEconomicsTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_separates_cash_liability_and_recognized_revenue(): void
    {
        Carbon::setTestNow('2026-11-24 10:00:00');
        $biz = Business::factory()->create();
        AiCreditTopup::create(['business_id' => $biz->id, 'pack' => 'ai-300', 'credits' => 300, 'amount' => 39000, 'payment_token' => 't1', 'status' => 'paid', 'paid_at' => now()]);
        AiCreditTopup::create(['business_id' => $biz->id, 'pack' => 'ai-100', 'credits' => 100, 'amount' => 15000, 'payment_token' => 't2', 'status' => 'pending']);
        AiCreditEntry::create(['business_id' => $biz->id, 'kind' => 'grant_monthly', 'free_change' => 30, 'paid_change' => 0, 'free_after' => 30, 'paid_after' => 0]);
        AiCreditEntry::create(['business_id' => $biz->id, 'kind' => 'usage', 'free_change' => -30, 'paid_change' => -20, 'free_after' => 0, 'paid_after' => 280]);
        AiCreditWallet::create(['business_id' => $biz->id, 'free_balance' => 0, 'free_period' => '2026-11', 'paid_balance' => 280]);

        $s = app(AiCreditEconomics::class)->summary();
        $this->assertSame(39000.0, $s['kas_masuk_topup']);
        $this->assertSame(300, $s['kredit_terjual']);
        $this->assertSame(['berbayar' => 20, 'gratis' => 30, 'total' => 50, 'toko_aktif' => 1], $s['kredit_terpakai']);
        $this->assertEquals(2600, $s['pendapatan_diakui_estimasi'], '20 kredit x Rp130, bukan Rp39.000');
        $this->assertSame(280, $s['kewajiban_kredit_belum_terpakai']['kredit']);
        $this->assertEquals(36400, $s['kewajiban_kredit_belum_terpakai']['rupiah_estimasi']);
        $this->assertStringContainsString('ASUMSI', $s['biaya_ai']['sumber']);

        // 12.000 token x $0.40/1M x Rp16.500 = Rp79,2 per kredit (batas atas)
        $this->assertEqualsWithDelta(79.2, $s['biaya_ai']['per_kredit_rp'], 0.1);
        $hemat = collect($s['margin_per_paket'])->firstWhere('pack', 'Hemat');
        $this->assertEqualsWithDelta(19.8, $hemat['margin_per_kredit'], 0.1);
        $this->assertGreaterThan($hemat['margin_pct'], collect($s['margin_per_paket'])->firstWhere('pack', 'Starter')['margin_pct']);
    }

    public function test_luna_has_the_tool_and_instruction(): void
    {
        $this->assertStringContainsString('kewajiban', AgentBrain::JUDGMENT['finance']);
        $this->assertStringContainsString('Dr 1200 Saldo Midtrans / Cr 4200', AgentBrain::JUDGMENT['accounting']);
        $this->assertStringContainsString('jangan menjurnal sendiri', AgentBrain::JUDGMENT['accounting']);
        $this->assertStringContainsString('ai_credit_economics', AgentBrain::JUDGMENT['finance']);
        $this->assertStringContainsString("'ai_credit_economics'", file_get_contents(app_path('Services/PlatformAgents/AgentBrain.php')));
    }
}
