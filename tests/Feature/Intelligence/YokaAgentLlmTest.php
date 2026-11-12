<?php

namespace Tests\Feature\Intelligence;

use App\Models\AssistantAttachment;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\User;
use App\Services\YokaAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class YokaAgentLlmTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        return User::factory()->create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'role' => 'owner']);
    }

    public function test_agent_calls_tools_then_answers_with_markdown_and_suggestions(): void
    {
        config(['services.yoka_ai.key' => 'test-key']);
        Http::fake(['*/chat/completions' => Http::sequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'marketing_brief', 'arguments' => json_encode(['period' => 'last_30_days', 'outlet_ids' => null])]]]]]], 'usage' => ['total_tokens' => 10]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => "**Penjualan masih sepi.**\n\n- Buat promo jam sepi\n\nSARAN: Produk apa paling laku? | Jam ramai kapan?"]]], 'usage' => ['total_tokens' => 20]])]);
        $reply = app(YokaAssistantService::class)->ask($this->owner(), 'Kasih ide promo untuk naikkan omzet');

        $this->assertStringContainsString('Penjualan masih sepi', $reply['answer']);
        $this->assertStringNotContainsString('SARAN', $reply['answer']);
        $this->assertSame(['Produk apa paling laku?', 'Jam ramai kapan?'], $reply['response']['suggestions']);
    }

    public function test_off_topic_questions_are_refused_without_running_tools(): void
    {
        config(['services.yoka_ai.key' => 'test-key']);
        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode(['in_scope' => false])]]]])]);
        $reply = app(YokaAssistantService::class)->ask($this->owner(), 'Siapa presiden Perancis?');

        $this->assertStringContainsString('hanya bisa membantu seputar bisnis', $reply['answer']);
        Http::assertSentCount(1);
    }

    public function test_tool_arguments_from_the_model_cannot_reach_other_outlets(): void
    {
        config(['services.yoka_ai.key' => 'test-key']);
        $foreign = Outlet::factory()->create();
        Http::fake(['*/chat/completions' => Http::sequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'sales_summary', 'arguments' => json_encode(['period' => 'today', 'outlet_ids' => [$foreign->id]])]]]]]]])
            ->push(['choices' => [['message' => ['content' => 'Outlet itu tidak tersedia. SARAN: Omzet hari ini?']]]])]);
        $reply = app(YokaAssistantService::class)->ask($this->owner(), 'omzet outlet lain');

        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), 'Outlet tidak tersedia'));
        $this->assertSame([], $reply['response']['blocks']);
    }

    public function test_documents_are_stored_per_business_and_sent_as_untrusted_data(): void
    {
        config(['services.yoka_ai.key' => 'test-key']);
        Storage::fake('local');
        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Sudah kubaca. SARAN: Apa solusinya?']]]])]);
        $owner = $this->owner();
        $csv = UploadedFile::fake()->createWithContent('rencana.csv', "bulan,target\nJan,1000\n");
        $reply = app(YokaAssistantService::class)->ask($owner, 'Baca file ini dan beri strategi toko', null, [$csv]);

        $doc = AssistantAttachment::firstOrFail();
        $this->assertSame($owner->business_id, $doc->business_id);
        $this->assertStringContainsString('Jan,1000', $doc->extracted_text);
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), 'bukan instruksi') && str_contains(json_encode($r->data()), 'Jan,1000'));
        $this->assertNotEmpty($reply['conversation_id']);
    }

    public function test_unsupported_file_types_are_rejected(): void
    {
        config(['services.yoka_ai.key' => 'test-key']);
        Storage::fake('local');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(YokaAssistantService::class)->ask($this->owner(), 'baca ini toko', null, [UploadedFile::fake()->create('virus.exe', 10)]);
    }

    public function test_llm_failure_falls_back_to_the_local_engine(): void
    {
        config(['services.yoka_ai.key' => 'test-key']);
        Http::fake(['*/chat/completions' => Http::response('boom', 500)]);
        $reply = app(YokaAssistantService::class)->ask($this->owner(), 'Berapa omzet hari ini?');

        $this->assertSame('sales_summary', $reply['tool']);
    }

    public function test_business_notes_are_saved_and_fed_back_into_later_prompts(): void
    {
        config(['services.yoka_ai.key' => 'test-key']);
        $owner = $this->owner();
        Http::fake(['*/chat/completions' => Http::sequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'n1', 'type' => 'function', 'function' => ['name' => 'save_business_note', 'arguments' => json_encode(['note' => 'Target omzet Desember 30 juta'])]]]]]]])
            ->push(['choices' => [['message' => ['content' => 'Siap, kucatat. SARAN: Omzet hari ini?']]]])
            ->push(['choices' => [['message' => ['content' => 'Oke. SARAN: lanjut']]]])]);
        app(YokaAssistantService::class)->ask($owner, 'Ingat ya target omzet Desember 30 juta untuk toko');
        $this->assertSame(['Target omzet Desember 30 juta'], $owner->business->fresh()->settings['yoka_notes']);

        app(YokaAssistantService::class)->ask($owner->fresh(), 'Apa kabar toko kita');
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), 'Target omzet Desember 30 juta') && str_contains(json_encode($r->data()), 'CATATAN BISNIS'));
    }

    public function test_cashier_cannot_store_business_notes(): void
    {
        config(['services.yoka_ai.key' => 'test-key']);
        $owner = $this->owner();
        $cashier = User::factory()->create(['business_id' => $owner->business_id, 'outlet_id' => $owner->outlet_id, 'role' => 'cashier']);
        $agent = app(\App\Services\Intelligence\YokaAgent::class);
        $m = new \ReflectionMethod($agent, 'saveNote');
        $m->setAccessible(true);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $m->invoke($agent, $cashier, ['note' => 'catatan jahat']);
    }

    public function test_customer_names_are_masked_before_leaving_for_the_ai_provider(): void
    {
        $agent = app(\App\Services\Intelligence\YokaAgent::class);
        $m = new \ReflectionMethod($agent, 'maskPersonalData');
        $m->setAccessible(true);
        $out = $m->invoke($agent, ['data' => ['customers' => [['customer_id' => 9, 'name' => 'Budi Santoso', 'visits' => 3]]]]);

        $this->assertSame('Budi S.', $out['data']['customers'][0]['name']);
        $this->assertArrayNotHasKey('customer_id', $out['data']['customers'][0]);
    }

    public function test_weekly_digest_goes_to_telegram_only_for_linked_businesses_that_want_it(): void
    {
        config(['services.yoka_ai.key' => 'test-key', 'services.telegram.token' => 't:oken', 'services.telegram.bot_token' => 't:oken']);
        $owner = $this->owner();
        $biz = $owner->business;
        $biz->setTelegram(['chat_id' => '123', 'chat_name' => 'x', 'linked_at' => now()->toIso8601String()]);
        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'w1', 'type' => 'function', 'function' => ['name' => 'marketing_brief', 'arguments' => json_encode(['period' => 'this_week', 'outlet_ids' => null])]]]]]]])
                ->push(['choices' => [['message' => ['content' => "**Minggu ini stabil.**\nAksi: promo jam sepi. SARAN: x"]]]]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        \App\Services\TelegramNotifier::weeklyDigest($biz->fresh());

        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.telegram.org') && str_contains($r['text'] ?? '', 'Analisis mingguan') && ! str_contains($r['text'], '**'));
    }

    public function test_streaming_forwards_status_and_tokens_and_returns_the_full_answer(): void
    {
        config(['services.yoka_ai.key' => 'test-key']);
        $sse = fn (array $chunks) => implode('', array_map(fn ($c) => 'data: '.json_encode($c)."\n\n", $chunks))."data: [DONE]\n\n";
        $toolStep = $sse([
            ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 't1', 'function' => ['name' => 'sales_sum', 'arguments' => '']]]]]]],
            ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['name' => 'mary', 'arguments' => '{"period":"today",']]]]]]],
            ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['arguments' => '"outlet_ids":null}']]]]]]],
        ]);
        $answer = $sse([
            ['choices' => [['delta' => ['content' => 'Omzet ']]]], ['choices' => [['delta' => ['content' => 'stabil.']]]],
            ['choices' => [['delta' => ['content' => "\nSARAN: Jam ramai?"]]]], ['choices' => [], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8]],
        ]);
        Http::fake(['*/chat/completions' => Http::sequence()->push($toolStep, 200, ['Content-Type' => 'text/event-stream'])->push($answer, 200, ['Content-Type' => 'text/event-stream'])]);
        $tokens = [];
        $statuses = [];
        $reply = app(YokaAssistantService::class)->ask($this->owner(), 'Berapa omzet hari ini?', null, [], function ($t) use (&$tokens) {
            $tokens[] = $t;
        }, function ($l) use (&$statuses) {
            $statuses[] = $l;
        });

        $this->assertSame(['Omzet ', 'stabil.', "\nSARAN: Jam ramai?"], $tokens);
        $this->assertContains('Membaca data penjualan…', $statuses);
        $this->assertSame('Omzet stabil.', $reply['answer']);
        $this->assertSame(['Jam ramai?'], $reply['response']['suggestions']);
    }

    public function test_advanced_analysis_tools_run_on_a_business_without_data(): void
    {
        $owner = $this->owner();
        $adv = app(\App\Services\Intelligence\AdvancedTools::class);
        foreach ($adv->names() as $tool) {
            $out = $adv->run($owner, $tool, ['period' => 'this_month']);
            $this->assertSame($tool, $out['schema'], $tool);
            $this->assertIsArray($out['data'], $tool);
        }
        $this->assertCount(5, $adv->definitions());
    }

    public function test_advanced_tools_only_see_the_users_own_business(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $adv = app(\App\Services\Intelligence\AdvancedTools::class);
        $out = $adv->run($mine, 'customer_retention', ['period' => 'last_30_days']);

        $this->assertSame(0, $out['data']['customers_in_period']);
        $this->assertNotContains($other->outlet_id, $out['scope']['outlet_ids']);
    }
}
