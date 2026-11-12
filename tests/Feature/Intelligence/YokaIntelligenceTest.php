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
    }

    public function test_sales_trend_uses_previous_revenue_value_without_type_error(): void
    {
        $owner = $this->owner();
        $yesterday = Order::create([
            'business_id' => $owner->business_id, 'outlet_id' => $owner->outlet_id, 'user_id' => $owner->id,
            'order_number' => 'TREND-YESTERDAY', 'status' => 'paid', 'payment_status' => 'paid',
            'grand_total' => 100000, 'created_at' => now()->subDay(),
        ]);
        $yesterday->timestamps = false;
        $yesterday->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();
        Order::create([
            'business_id' => $owner->business_id, 'outlet_id' => $owner->outlet_id, 'user_id' => $owner->id,
            'order_number' => 'TREND-TODAY', 'status' => 'paid', 'payment_status' => 'paid',
            'grand_total' => 150000, 'created_at' => now(),
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
        $this->assertArrayHasKey('presentation', $answer);
        $this->assertSame('Ringkasan hari ini', $answer['presentation']['title']);
        $this->assertDatabaseCount('assistant_messages', 2);
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
        $this->assertCount(4, $answer['presentation']['metrics']);
        $this->assertArrayHasKey('chart', $answer['presentation']);
        $this->assertSame('Ringkasan sejak transaksi pertama', $answer['presentation']['title']);
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
        $first = $this->owner(); $second = $this->owner();
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
            ->assertSee('Apa yang ingin kamu ketahui?');
    }

    public function test_cashier_cannot_open_the_dedicated_chat_page(): void
    {
        $business = Business::factory()->create();
        $cashier = User::factory()->create(['business_id' => $business->id, 'outlet_id' => Outlet::factory()->create(['business_id' => $business->id])->id, 'role' => 'cashier']);

        $this->actingAs($cashier)->get(route('intelligence.index'))->assertForbidden();
    }
}
