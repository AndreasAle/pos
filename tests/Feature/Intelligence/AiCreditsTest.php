<?php

namespace Tests\Feature\Intelligence;

use App\Models\AiCreditEntry;
use App\Models\AiCreditTopup;
use App\Models\AssistantConversation;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AiCredits;
use App\Services\MidtransService;
use App\Services\YokaAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosScenario;
use Tests\TestCase;

class AiCreditsTest extends TestCase
{
    use RefreshDatabase, BuildsPosScenario;

    protected function setUp(): void
    {
        parent::setUp();
        config(['yoka_ai_credits.enabled' => true, 'pos.features.intelligence' => true, 'midtrans.signature_secret' => 'secret-x']);
        $this->setUpPos();
        $basic = SubscriptionPlan::create(['name' => 'YokaBasic', 'slug' => 'starter', 'price' => 149000, 'max_outlets' => 2, 'max_users' => 10, 'features' => [], 'is_active' => true]);
        BusinessSubscription::where('business_id', $this->business->id)->update(['subscription_plan_id' => $basic->id, 'status' => 'active', 'ends_at' => now()->addMonth()]);
    }

    private function owner(): User
    {
        return User::factory()->create(['business_id' => $this->business->id, 'outlet_id' => $this->outlet->id, 'role' => 'owner', 'is_active' => true]);
    }

    private function credits(): AiCredits
    {
        return app(AiCredits::class);
    }

    /** Pura-pura assistant menjawab dengan pemakaian token tertentu. */
    private function fakeAnswer(User $owner, array $usage): void
    {
        $conversation = AssistantConversation::create(['business_id' => $owner->business_id, 'user_id' => $owner->id, 'title' => 't']);
        $message = $conversation->messages()->create(['role' => 'assistant', 'content' => 'ok', 'token_usage' => $usage]);
        $this->mock(YokaAssistantService::class, fn ($m) => $m->shouldReceive('ask')->andReturn([
            'conversation_id' => $conversation->id, 'message_id' => $message->id, 'answer' => 'ok', 'tool' => null, 'data' => null, 'response' => ['message' => 'ok'], 'presentation' => [],
        ]));
    }

    public function test_monthly_allowance_follows_the_plan_and_resets_each_month(): void
    {
        $this->assertSame(30, $this->credits()->balance($this->business)['free']);

        $this->credits()->charge($this->business, ['prompt_tokens' => 8000, 'completion_tokens' => 500], 'msg:1');
        $this->assertSame(29, $this->credits()->balance($this->business)['free']);

        $this->travel(1)->months();
        $balance = $this->credits()->balance($this->business);
        $this->assertSame(30, $balance['free'], 'Jatah bulan baru tidak menumpuk dengan sisa bulan lalu.');
        $this->assertTrue(AiCreditEntry::where('kind', 'expire_free')->exists());
    }

    public function test_credits_follow_real_token_usage(): void
    {
        $c = $this->credits();
        $this->assertSame(0, $c->creditsFor(['prompt_tokens' => 0, 'completion_tokens' => 0]));
        $this->assertSame(1, $c->creditsFor(['prompt_tokens' => 8000, 'completion_tokens' => 500]));
        $this->assertSame(3, $c->creditsFor(['prompt_tokens' => 30000, 'completion_tokens' => 1000]));
        $this->assertSame(10, $c->creditsFor(['prompt_tokens' => 900000, 'completion_tokens' => 0]), 'Dibatasi per jawaban.');
    }

    public function test_free_credits_are_used_before_paid_and_balance_never_goes_negative(): void
    {
        $w = $this->credits()->wallet($this->business);
        $w->update(['free_balance' => 1, 'paid_balance' => 2, 'paid_expires_at' => now()->addYear()]);

        $used = $this->credits()->charge($this->business, ['prompt_tokens' => 60000, 'completion_tokens' => 0], 'msg:9');

        $this->assertSame(3, $used, 'Hanya sisa saldo yang terpotong.');
        $this->assertSame(['free' => 0, 'paid' => 0], array_intersect_key($this->credits()->balance($this->business), ['free' => 1, 'paid' => 1]));
        $this->assertSame(0, $this->credits()->charge($this->business, ['prompt_tokens' => 60000, 'completion_tokens' => 0], 'msg:9'), 'Satu jawaban dipotong sekali.');
    }

    public function test_asking_charges_credits_and_returns_the_new_balance(): void
    {
        $owner = $this->owner();
        $this->fakeAnswer($owner, ['prompt_tokens' => 8000, 'completion_tokens' => 500, 'total_tokens' => 8500]);

        $this->actingAs($owner)->postJson(route('intelligence.ask'), ['question' => 'omzet hari ini?'])
            ->assertOk()->assertJsonPath('credits_used', 1)->assertJsonPath('credits.total', 29);
    }

    public function test_asking_without_credits_is_refused_before_the_model_is_called(): void
    {
        $owner = $this->owner();
        $this->credits()->wallet($this->business)->update(['free_balance' => 0]);
        $this->mock(YokaAssistantService::class, fn ($m) => $m->shouldNotReceive('ask'));

        $this->actingAs($owner)->postJson(route('intelligence.ask'), ['question' => 'omzet?'])
            ->assertStatus(402)->assertJsonPath('code', 'no_credits');
        $this->actingAs($owner)->post(route('intelligence.ask.stream'), ['question' => 'omzet?'], ['Accept' => 'application/json'])
            ->assertStatus(402);
    }

    public function test_feature_off_keeps_tanya_yoka_unlimited(): void
    {
        config(['yoka_ai_credits.enabled' => false]);
        $owner = $this->owner();
        $this->fakeAnswer($owner, ['prompt_tokens' => 8000, 'completion_tokens' => 500]);

        $this->actingAs($owner)->postJson(route('intelligence.ask'), ['question' => 'omzet?'])->assertOk()->assertJsonMissingPath('credits');
        $this->actingAs($owner)->get(route('ai-credits.index'))->assertNotFound();
    }

    public function test_topup_checkout_and_webhook_add_credits_once(): void
    {
        $this->mock(MidtransService::class, function ($m) {
            $m->shouldReceive('isConfigured')->andReturnTrue();
            $m->shouldReceive('createSnapToken')->once()->andReturnUsing(function ($orderId, $amount, $name, $email, $item, $finish) {
                $this->assertSame(39000, $amount);
                $this->assertSame(route('ai-credits.index'), $finish);

                return ['token' => 't', 'redirect_url' => 'https://app.midtrans.com/snap/v2/vtweb/t'];
            });
            $m->shouldReceive('verifySignature')->andReturnTrue();
        });
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('ai-credits.checkout'), ['pack' => 'ai-300'])->assertRedirect('https://app.midtrans.com/snap/v2/vtweb/t');
        $topup = AiCreditTopup::sole();
        $this->assertSame('pending', $topup->status);

        $notify = ['order_id' => $topup->payment_token, 'status_code' => '200', 'gross_amount' => '39000.00', 'signature_key' => 'x', 'transaction_status' => 'settlement'];
        $this->postJson('/midtrans/callback', $notify)->assertOk();
        $this->postJson('/midtrans/callback', $notify)->assertOk();

        $balance = $this->credits()->balance($this->business);
        $this->assertSame(300, $balance['paid']);
        $this->assertSame('paid', $topup->fresh()->status);
        $this->assertSame(1, AiCreditEntry::where('kind', 'topup')->count());
        $this->assertDatabaseHas('platform_fin_entries', ['idempotency_key' => 'midtrans:ai-credits:'.$topup->id]);
    }

    public function test_webhook_with_a_different_amount_adds_nothing(): void
    {
        $this->mock(MidtransService::class, fn ($m) => $m->shouldReceive('verifySignature')->andReturnTrue());
        $topup = AiCreditTopup::create(['business_id' => $this->business->id, 'pack' => 'ai-1000', 'credits' => 1000, 'amount' => 99000, 'payment_token' => 'AIC-1-X']);

        $this->postJson('/midtrans/callback', ['order_id' => 'AIC-1-X', 'status_code' => '200', 'gross_amount' => '1000.00', 'signature_key' => 'x', 'transaction_status' => 'settlement'])
            ->assertStatus(400);

        $this->assertSame('pending', $topup->fresh()->status);
        $this->assertSame(0, $this->credits()->balance($this->business)['paid']);
    }

    public function test_unknown_pack_is_rejected(): void
    {
        $this->actingAs($this->owner())->post(route('ai-credits.checkout'), ['pack' => 'ai-murah'])->assertSessionHasErrors('pack');
    }

    public function test_credits_page_shows_balance_and_packs(): void
    {
        $this->actingAs($this->owner())->get(route('ai-credits.index'))->assertOk()
            ->assertSee('Sisa kredit')->assertSee('Rp39.000')->assertSee('Jatah gratis bulanan');
        $this->actingAs($this->owner())->get(route('intelligence.index'))->assertOk()->assertSee(route('ai-credits.index'), false);
    }

    public function test_yoka_ai_sidebar_groups_chats_and_has_an_account_menu(): void
    {
        $owner = $this->owner();
        $today = AssistantConversation::create(['business_id' => $owner->business_id, 'user_id' => $owner->id, 'title' => 'Omzet hari ini']);
        $old = AssistantConversation::create(['business_id' => $owner->business_id, 'user_id' => $owner->id, 'title' => 'Stok bulan lalu']);
        $old->forceFill(['updated_at' => now()->subDays(40)])->saveQuietly();

        $this->actingAs($owner)->get(route('intelligence.index'))->assertOk()
            ->assertSeeInOrder(['Hari ini', 'Omzet hari ini', 'Lebih lama', 'Stok bulan lalu'])
            ->assertSee('Kredit AI')->assertSee('Insight bisnis')->assertSee('Kembali ke Dashboard')->assertSee('YokaBasic')
            ->assertDontSee('Insight aktif');

        $this->actingAs($owner)->get(route('ai-credits.index'))->assertOk()
            ->assertSee('Omzet hari ini')->assertSee('Kembali ke Tanya Yoka', false);
    }

    public function test_insight_link_prefills_the_question(): void
    {
        $this->actingAs($this->owner())->get(route('intelligence.index', ['ask' => 'Stok gula hampir habis']))->assertOk()
            ->assertSee('Stok gula hampir habis');
    }
}
