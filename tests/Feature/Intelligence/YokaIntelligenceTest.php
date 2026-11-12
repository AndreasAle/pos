<?php

namespace Tests\Feature\Intelligence;

use App\Models\AssistantConversation;
use App\Models\Business;
use App\Models\BusinessInsight;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use App\Services\InsightService;
use App\Services\YokaAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YokaIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pos.features.subscription' => false, 'services.yoka_ai.key' => null]);
    }

    private function owner(?Business $business = null): User
    {
        $business ??= Business::factory()->create();

        return User::factory()->create([
            'business_id' => $business->id,
            'outlet_id' => Outlet::factory()->create(['business_id' => $business->id])->id,
            'role' => 'owner', 'is_active' => true, 'email_verified_at' => now(),
        ]);
    }

    public function test_low_stock_produces_a_persisted_proactive_insight(): void
    {
        $business = Business::factory()->create();
        Ingredient::factory()->create(['business_id' => $business->id, 'current_stock' => 2, 'minimum_stock' => 5, 'name' => 'Susu']);

        app(InsightService::class)->refresh($business);

        $this->assertDatabaseHas('business_insights', [
            'business_id' => $business->id, 'type' => 'stock_forecast', 'severity' => 'critical',
        ]);
        $insight = BusinessInsight::where('business_id', $business->id)->where('type', 'stock_forecast')->firstOrFail();
        $this->assertNotEmpty($insight->evidence);
        $this->assertNotNull($insight->confidence);
        $this->assertNotNull($insight->first_detected_at);
    }

    public function test_insight_refresh_deduplicates_and_tracks_occurrences(): void
    {
        $business = Business::factory()->create();
        Ingredient::factory()->create(['business_id' => $business->id, 'current_stock' => 1, 'minimum_stock' => 5]);
        $service = app(InsightService::class);

        $service->refresh($business);
        $service->refresh($business);

        $this->assertSame(1, BusinessInsight::where('business_id', $business->id)->where('type', 'stock_forecast')->count());
        $this->assertSame(2, BusinessInsight::where('business_id', $business->id)->where('type', 'stock_forecast')->value('occurrence_count'));
    }

    public function test_reading_visible_insights_does_not_run_detectors_on_page_load(): void
    {
        $business = Business::factory()->create();
        Ingredient::factory()->create(['business_id' => $business->id, 'current_stock' => 1, 'minimum_stock' => 5]);

        $this->assertCount(0, app(InsightService::class)->visible($business));
        $this->assertDatabaseCount('business_insights', 0);
    }

    public function test_sales_trend_uses_previous_revenue_value_without_type_error(): void
    {
        $owner = $this->owner();
        $yesterday = Order::create([
            'business_id' => $owner->business_id, 'outlet_id' => $owner->outlet_id, 'user_id' => $owner->id,
            'order_number' => 'TREND-YESTERDAY', 'status' => 'paid', 'payment_status' => 'paid',
            'subtotal' => 100000, 'grand_total' => 100000, 'created_at' => now()->subDay(),
        ]);
        $yesterday->timestamps = false;
        $yesterday->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();
        Order::create([
            'business_id' => $owner->business_id, 'outlet_id' => $owner->outlet_id, 'user_id' => $owner->id,
            'order_number' => 'TREND-TODAY', 'status' => 'paid', 'payment_status' => 'paid',
            'subtotal' => 150000, 'grand_total' => 150000, 'created_at' => now(),
        ]);

        app(InsightService::class)->refresh($owner->business);

        $this->assertDatabaseHas('business_insights', [
            'business_id' => $owner->business_id, 'type' => 'sales_trend', 'severity' => 'positive',
        ]);
    }

    public function test_assistant_works_without_an_external_ai_key_and_saves_history(): void
    {
        config(['services.yoka_ai.key' => null]);
        $owner = $this->owner();

        $answer = app(YokaAssistantService::class)->ask($owner, 'Berapa omzet hari ini?');

        $this->assertSame('sales_summary', $answer['tool']);
        $this->assertStringContainsString('Belum ada transaksi', $answer['answer']);
        $this->assertSame('kpi', data_get($answer, 'response.blocks.0.type'));
        $this->assertSame('Ringkasan hari ini', data_get($answer, 'response.blocks.0.title'));
        $this->assertDatabaseCount('assistant_messages', 2);
    }

    public function test_assistant_returns_verified_business_analysis_instead_of_only_repeating_totals(): void
    {
        config(['services.yoka_ai.key' => null]);
        $owner = $this->owner();
        foreach ([100000, 300000] as $index => $amount) {
            $date = now()->startOfMonth()->addDays($index);
            $order = Order::create([
                'business_id' => $owner->business_id, 'outlet_id' => $owner->outlet_id, 'user_id' => $owner->id,
                'order_number' => 'ANALYSIS-'.$index, 'status' => 'paid', 'payment_status' => 'paid',
                'payment_method' => 'cash', 'subtotal' => $amount, 'grand_total' => $amount,
                'discount_amount' => 0, 'is_split_payment' => false,
            ]);
            $order->timestamps = false;
            $order->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
        }

        $answer = app(YokaAssistantService::class)->ask($owner, 'Buat grafik tren penjualan bulan ini');
        $findings = data_get($answer, 'response.analysis.findings');

        $this->assertSame('sales_series', $answer['tool']);
        $this->assertCount(3, $findings);
        $this->assertStringContainsString('2 hari aktif', $findings[0]);
        $this->assertStringContainsString('Puncak terjadi', $findings[1]);
        $this->assertStringContainsString('naik 200.0%', $findings[2]);
        $this->assertStringContainsString('Langkah yang paling masuk akal', $answer['answer']);
        $this->assertNotEmpty(data_get($answer, 'response.analysis.caveats'));
    }

    public function test_follow_up_keeps_previous_subject_and_changes_the_date_range(): void
    {
        $owner = $this->owner();
        $first = app(YokaAssistantService::class)->ask($owner, 'Berapa pembayaran cash dan QRIS hari ini?');
        $followUp = app(YokaAssistantService::class)->ask($owner, 'Kalau dari tanggal 1 bro?', $first['conversation_id']);

        $this->assertSame('payment_breakdown', $followUp['tool']);
        $message = AssistantConversation::find($first['conversation_id'])->messages()->findOrFail($followUp['message_id']);
        $this->assertSame('custom', data_get($message->meta, 'arguments.period'));
        $this->assertSame(now()->startOfMonth()->toDateString(), data_get($message->meta, 'arguments.date_from'));
    }

    public function test_from_first_transaction_uses_all_time_and_returns_real_visual_data(): void
    {
        $owner = $this->owner();
        $answer = app(YokaAssistantService::class)->ask($owner, 'Berapa profit dari awal transaksi?');

        $message = AssistantConversation::find($answer['conversation_id'])->messages()->findOrFail($answer['message_id']);
        $this->assertSame('all_time', data_get($message->meta, 'arguments.period'));
        $this->assertCount(4, data_get($answer, 'response.blocks.0.metrics'));
        $this->assertSame('kpi', data_get($answer, 'response.blocks.0.type'));
        $this->assertSame('Ringkasan sejak transaksi pertama', data_get($answer, 'response.blocks.0.title'));
    }

    public function test_acknowledgement_does_not_repeat_the_previous_data_or_visual(): void
    {
        $owner = $this->owner();
        $first = app(YokaAssistantService::class)->ask($owner, 'Tampilkan grafik pembayaran bulan ini');
        $reply = app(YokaAssistantService::class)->ask($owner, 'Oke makasih bro', $first['conversation_id']);

        $this->assertNull($reply['tool']);
        $this->assertNull($reply['data']);
        $this->assertSame([], $reply['response']['blocks']);
        $this->assertSame('Sama-sama, bro 🙌', $reply['answer']);
    }

    public function test_advice_follow_up_reuses_data_range_without_repeating_a_visual(): void
    {
        $owner = $this->owner();
        $first = app(YokaAssistantService::class)->ask($owner, 'Tampilkan grafik pembayaran dari tanggal 1');
        $reply = app(YokaAssistantService::class)->ask($owner, 'Ada saran gak bro?', $first['conversation_id']);
        $message = AssistantConversation::find($first['conversation_id'])->messages()->findOrFail($reply['message_id']);

        $this->assertSame('payment_breakdown', $reply['tool']);
        $this->assertSame('analysis', data_get($message->meta, 'intent'));
        $this->assertSame('custom', data_get($message->meta, 'arguments.period'));
        $this->assertSame([], $reply['response']['blocks']);
    }

    public function test_sales_trend_follow_up_reuses_the_previous_date_range(): void
    {
        $owner = $this->owner();
        Order::create([
            'business_id' => $owner->business_id, 'outlet_id' => $owner->outlet_id, 'user_id' => $owner->id,
            'order_number' => 'TREND-RANGE', 'status' => 'paid', 'payment_status' => 'paid',
            'payment_method' => 'cash', 'subtotal' => 100000, 'grand_total' => 100000, 'is_split_payment' => false,
            'created_at' => now()->startOfMonth()->addDay(), 'updated_at' => now()->startOfMonth()->addDay(),
        ]);

        $first = app(YokaAssistantService::class)->ask($owner, 'Berapa omzet dari tanggal 1?');
        $reply = app(YokaAssistantService::class)->ask($owner, 'Buat grafik trennya', $first['conversation_id']);
        $message = AssistantConversation::find($first['conversation_id'])->messages()->findOrFail($reply['message_id']);

        $this->assertSame('sales_series', $reply['tool']);
        $this->assertSame('custom', data_get($message->meta, 'arguments.period'));
        $this->assertSame(now()->startOfMonth()->toDateString(), data_get($message->meta, 'arguments.date_from'));
        $this->assertSame(now()->toDateString(), data_get($message->meta, 'arguments.date_to'));
        $this->assertSame('line_chart', data_get($reply, 'response.blocks.0.type'));
        $expectedRange = now()->startOfMonth()->format('j M Y').'–'.now()->format('j M Y');
        $this->assertSame('Tren penjualan '.$expectedRange, data_get($reply, 'response.blocks.0.title'));
    }

    public function test_visual_type_follows_the_users_request(): void
    {
        $owner = $this->owner();
        Order::create([
            'business_id' => $owner->business_id, 'outlet_id' => $owner->outlet_id, 'user_id' => $owner->id,
            'order_number' => 'VISUAL-PAYMENT', 'status' => 'paid', 'payment_status' => 'paid',
            'payment_method' => 'cash', 'subtotal' => 100000, 'grand_total' => 100000, 'is_split_payment' => false,
        ]);

        $chart = app(YokaAssistantService::class)->ask($owner, 'Buatkan grafik pembayaran bulan ini');
        $this->assertNotEmpty(data_get($chart, 'data.data.payments'), json_encode($chart));
        $this->assertSame('bar_chart', data_get($chart, 'response.blocks.0.type'));
        $this->assertCount(1, $chart['response']['blocks']);

        $table = app(YokaAssistantService::class)->ask($owner, 'Buatkan tabel pembayaran bulan ini');
        $this->assertSame('table', data_get($table, 'response.blocks.0.type'));
        $this->assertCount(1, $table['response']['blocks']);
    }

    public function test_latest_explicit_period_overrides_ai_context_and_markdown_table_is_not_duplicated(): void
    {
        $owner = $this->owner();
        foreach ([[now()->startOfMonth(), 200000], [now(), 100000]] as $index => [$date, $amount]) {
            $order = Order::create([
                'business_id' => $owner->business_id, 'outlet_id' => $owner->outlet_id, 'user_id' => $owner->id,
                'order_number' => 'PERIOD-'.$index, 'status' => 'paid', 'payment_status' => 'paid',
                'payment_method' => 'cash', 'subtotal' => $amount, 'grand_total' => $amount,
                'discount_amount' => 0, 'is_split_payment' => false,
            ]);
            $order->timestamps = false;
            $order->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
        }

        $first = app(YokaAssistantService::class)->ask($owner, 'Buatkan tabel pembayaran hari ini');
        config(['services.yoka_ai.key' => 'test-key', 'services.yoka_ai.agent' => false]);
        Http::fakeSequence()
            ->push(['choices' => [['message' => ['content' => json_encode([
                'intent' => 'lookup', 'tool' => 'payment_breakdown',
                'arguments' => ['period' => 'today', 'date_from' => null, 'date_to' => null, 'outlet_ids' => null, 'query' => null, 'limit' => null],
                'visual' => 'table',
            ])]]], 'usage' => []], 200)
            ->push(['choices' => [['message' => ['content' => "| Metode | Total |\n|---|---|\n| Cash | Rp300.000 |\n\nCash menjadi metode utama bulan ini."]]], 'usage' => []], 200);

        $reply = app(YokaAssistantService::class)->ask($owner, 'Nah sekarang buatkan tabelnya untuk keseluruhan transaksi bulan ini', $first['conversation_id']);
        $message = AssistantConversation::find($first['conversation_id'])->messages()->findOrFail($reply['message_id']);

        $this->assertSame('this_month', data_get($message->meta, 'arguments.period'));
        $this->assertSame(300000.0, data_get($reply, 'data.data.total'));
        $this->assertSame('Metode pembayaran bulan ini', data_get($reply, 'response.blocks.0.title'));
        $this->assertSame('table', data_get($reply, 'response.blocks.0.type'));
        $this->assertStringNotContainsString('| Metode |', $reply['answer']);
        $this->assertSame('Cash menjadi metode utama bulan ini.', $reply['answer']);
    }

    public function test_user_cannot_continue_another_tenants_conversation(): void
    {
        $first = $this->owner();
        $second = $this->owner();
        $conversation = AssistantConversation::create(['business_id' => $first->business_id, 'user_id' => $first->id]);

        $this->actingAs($second)->postJson(route('intelligence.ask'), [
            'question' => 'Omzet hari ini?', 'conversation_id' => $conversation->id,
        ])->assertNotFound();
    }

    public function test_insight_from_another_tenant_cannot_be_dismissed(): void
    {
        $first = $this->owner();
        $second = $this->owner();
        $insight = BusinessInsight::create([
            'business_id' => $first->business_id, 'fingerprint' => 'test', 'type' => 'sales_trend',
            'severity' => 'info', 'title' => 'Test', 'summary' => 'Test', 'detected_at' => now(),
        ]);

        $this->actingAs($second)->postJson(route('intelligence.insights.dismiss', $insight))->assertForbidden();
        $this->assertNull($insight->fresh()->dismissed_at);
    }

    public function test_cashier_cannot_access_owner_intelligence_endpoint(): void
    {
        $business = Business::factory()->create();
        $cashier = User::factory()->create(['business_id' => $business->id, 'outlet_id' => Outlet::factory()->create(['business_id' => $business->id])->id, 'role' => 'cashier']);

        $this->actingAs($cashier)->postJson(route('intelligence.ask'), ['question' => 'Omzet?'])->assertForbidden();
    }

    public function test_owner_can_open_the_dedicated_chat_page(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('intelligence.index'))
            ->assertOk()
            ->assertSee('Tanya Yoka')
            ->assertSee('Apa yang ingin kamu ketahui?')
            ->assertSee('images/brand/yoka-icon.png')
            ->assertDontSee('Data bisnis terhubung')
            ->assertDontSee('Asisten data '.$owner->business->name);
    }

    public function test_cashier_cannot_open_the_dedicated_chat_page(): void
    {
        $business = Business::factory()->create();
        $cashier = User::factory()->create(['business_id' => $business->id, 'outlet_id' => Outlet::factory()->create(['business_id' => $business->id])->id, 'role' => 'cashier']);

        $this->actingAs($cashier)->get(route('intelligence.index'))->assertForbidden();
    }

    public function test_ai_planner_uses_closed_schema_and_persists_safe_trace_metadata(): void
    {
        config(['services.yoka_ai.key' => 'test-key', 'services.yoka_ai.agent' => false]);
        Http::fakeSequence()
            ->push(['choices' => [['message' => ['content' => json_encode(['intent' => 'lookup', 'tool' => 'sales_summary', 'arguments' => ['period' => 'today', 'date_from' => null, 'date_to' => null, 'outlet_ids' => null, 'query' => null, 'limit' => null], 'visual' => 'metrics'])]]], 'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 5, 'total_tokens' => 25]], 200, ['x-request-id' => 'req-plan'])
            ->push(['choices' => [['message' => ['content' => 'Belum ada transaksi hari ini, bro.']]], 'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 7, 'total_tokens' => 37]], 200, ['x-request-id' => 'req-answer']);

        $answer = app(YokaAssistantService::class)->ask($this->owner(), 'Omzet hari ini berapa bro?');
        $message = AssistantConversation::find($answer['conversation_id'])->messages()->findOrFail($answer['message_id']);

        $this->assertSame(62, data_get($message->token_usage, 'total_tokens'));
        $this->assertSame('req-plan,req-answer', $message->request_id);
        Http::assertSent(fn ($request) => data_get($request->data(), 'response_format.type') === 'json_schema');
    }
}
