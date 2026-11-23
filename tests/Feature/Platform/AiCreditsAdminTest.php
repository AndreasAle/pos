<?php

namespace Tests\Feature\Platform;

use App\Models\AiCreditEntry;
use App\Models\AiCreditTopup;
use App\Models\BusinessSubscription;
use App\Models\PlatformActivityLog;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPlan;
use App\Services\AiCredits;
use App\Services\AiCreditsReport;
use App\Services\PlatformAgents\AgentBrain;
use App\Services\PlatformAgents\CustomerService\ProductKnowledge;
use App\Services\PlatformAgents\Sales\CommercialPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

/** Kredit Yoka AI di panel admin dan di pengetahuan tim AI. */
class AiCreditsAdminTest extends TestCase
{
    use BuildsPosScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['yoka_ai_credits.enabled' => true]);
        $this->setUpPos();
        $basic = SubscriptionPlan::create(['name' => 'YokaBasic', 'slug' => 'starter', 'price' => 149000, 'max_outlets' => 2, 'max_users' => 10, 'features' => [], 'is_active' => true]);
        BusinessSubscription::where('business_id', $this->business->id)->update(['subscription_plan_id' => $basic->id, 'status' => 'active', 'ends_at' => now()->addMonth()]);
    }

    private function admin(string $role = 'super_admin'): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => 'Tim Yoka', 'email' => $role.'@yokapos.test', 'password' => 'password-platform-kuat', 'role' => $role, 'is_active' => true]);
    }

    private function as(PlatformAdmin $admin): static
    {
        return $this->actingAs($admin, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp]);
    }

    public function test_super_admin_sees_credit_stats_per_store(): void
    {
        app(AiCredits::class)->charge($this->business, ['prompt_tokens' => 8000, 'completion_tokens' => 500], 'msg:1');
        AiCreditTopup::create(['business_id' => $this->business->id, 'pack' => 'ai-300', 'credits' => 300, 'amount' => 39000, 'status' => 'paid', 'paid_at' => now(), 'payment_token' => 'AIC-T1']);

        $this->as($this->admin())->get(route('platform.ai-credits'))->assertOk()
            ->assertSee('Kredit AI')->assertSee($this->business->name)->assertSee('Rp 39.000')->assertSee('Sesuaikan kredit');
    }

    public function test_finance_can_view_but_not_adjust_and_support_cannot_open(): void
    {
        $finance = $this->admin('finance');
        $this->as($finance)->get(route('platform.ai-credits'))->assertOk()->assertDontSee('Sesuaikan kredit');
        $this->as($finance)->post(route('platform.ai-credits.adjust'), ['business_id' => $this->business->id, 'credits' => 50, 'reason' => 'coba coba'])->assertForbidden();
        $this->as($this->admin('support'))->get(route('platform.ai-credits'))->assertForbidden();
    }

    public function test_adjustment_is_logged_and_never_goes_negative(): void
    {
        $admin = $this->admin();
        $this->as($admin)->post(route('platform.ai-credits.adjust'), ['business_id' => $this->business->id, 'credits' => 50, 'reason' => 'Kompensasi gangguan'])->assertRedirect();

        $balance = app(AiCredits::class)->balance($this->business);
        $this->assertSame(50, $balance['paid']);
        $this->assertNotNull($balance['paid_expires_at']);
        $this->assertSame('Kompensasi gangguan', AiCreditEntry::where('kind', 'adjust')->sole()->meta['reason']);
        $this->assertTrue(PlatformActivityLog::where('action', 'ai_credits.adjusted')->exists());

        $this->as($admin)->post(route('platform.ai-credits.adjust'), ['business_id' => $this->business->id, 'credits' => -500, 'reason' => 'Koreksi salah input'])->assertRedirect();
        $this->assertSame(0, app(AiCredits::class)->balance($this->business)['total']);
    }

    public function test_dashboard_shows_ai_credits_and_real_subscription_amount(): void
    {
        BusinessSubscription::where('business_id', $this->business->id)->update(['paid_at' => now(), 'amount' => 1399000]);

        $this->as($this->admin())->get(route('platform.dashboard'))->assertOk()
            ->assertSee('Kredit AI bulan ini')->assertSee('Rp 1.399.000');
    }

    public function test_adit_knows_credits_and_may_quote_pack_prices(): void
    {
        $this->assertSame('kredit-yoka-ai', ProductKnowledge::match('kak kredit tanya yoka saya habis, top up gimana?')[0]['slug'] ?? null);
        $this->assertStringContainsString('Penggunaan', ProductKnowledge::render('kredit ai habis'));

        [$clean, $removed] = app(CommercialPolicy::class)->scrubReply('Paket 300 kredit Rp39.000, jadi Rp130 per kredit.');
        $this->assertSame([], $removed);
        $this->assertStringContainsString('Rp39.000', $clean);

        $line = app(AiCreditsReport::class)->promptLine($this->business);
        $this->assertStringContainsString('sisa 30 kredit', $line);
        $this->assertStringContainsString('jangan dijanjikan', $line);
    }

    public function test_every_agent_has_the_official_credit_facts(): void
    {
        $facts = AgentBrain::productFacts()['kredit_yoka_ai'];
        $this->assertSame(30, $facts['jatah_gratis_per_bulan']['YokaBasic']);
        $this->assertContains('300 kredit Rp39.000 (Rp130 per kredit)', $facts['paket_beli']);
    }

    public function test_credit_guide_is_hidden_while_the_feature_is_off(): void
    {
        config(['yoka_ai_credits.enabled' => false]);
        $this->assertArrayNotHasKey('kredit-yoka-ai', \App\Http\Controllers\GuideController::guides());
    }
}
