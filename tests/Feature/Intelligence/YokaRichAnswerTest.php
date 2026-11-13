<?php

namespace Tests\Feature\Intelligence;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Intelligence\VisualBlocks;
use App\Services\Intelligence\WebResearch;
use App\Services\YokaAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YokaRichAnswerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        return User::factory()->create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'role' => 'owner']);
    }

    public function test_visual_blocks_are_extracted_validated_and_replaced_with_markers(): void
    {
        $text = "Intro\n\n```yoka\n{\"type\":\"chart\",\"kind\":\"hbar\",\"title\":\"<script>x</script>Top\",\"items\":[{\"label\":\"A\",\"value\":10},{\"label\":\"B\",\"value\":5},{\"label\":\"bad\",\"value\":\"abc\"}]}\n```\n\nOutro\n\n```yoka\n{\"type\":\"evil\",\"x\":1}\n```";
        [$clean, $visuals] = (new VisualBlocks)->extract($text);

        $this->assertStringContainsString('[[visual:0]]', $clean);
        $this->assertStringNotContainsString('```', $clean);
        $this->assertCount(1, $visuals);
        $this->assertSame('xTop', $visuals[0]['title']);
        $this->assertCount(2, $visuals[0]['items']);
    }

    public function test_unclosed_visual_fence_never_leaks_raw_json(): void
    {
        [$clean, $visuals] = (new VisualBlocks)->extract("Halo\n```yoka\n{\"type\":\"chart\",\"items\":[");

        $this->assertSame('Halo', $clean);
        $this->assertSame([], $visuals);
    }

    public function test_json_fenced_card_is_rendered_but_plain_code_blocks_are_left_alone(): void
    {
        $text = "Intro\n```json\n{\"type\":\"roadmap\",\"title\":\"Rencana\",\"items\":[{\"title\":\"A\"},{\"title\":\"B\"}]}\n```\n```json\n{\"nama\":\"x\"}\n```";
        [$clean, $visuals] = (new VisualBlocks)->extract($text);

        $this->assertCount(1, $visuals);
        $this->assertSame('roadmap', $visuals[0]['type']);
        $this->assertStringContainsString('[[visual:0]]', $clean);
        $this->assertStringContainsString('"nama"', $clean);
    }

    public function test_bare_fenced_card_json_is_rendered_not_leaked(): void
    {
        $text = "Intro\n\n```\n{\"type\":\"chart\",\"kind\":\"line\",\"title\":\"Tren\",\"items\":[{\"label\":\"1 Nov\",\"value\":10},{\"label\":\"2 Nov\",\"value\":20}]}\n```\n\nOutro";
        [$clean, $visuals] = (new VisualBlocks)->extract($text);

        $this->assertCount(1, $visuals);
        $this->assertStringNotContainsString('"type"', $clean);
        $this->assertStringContainsString('[[visual:0]]', $clean);
    }

    public function test_web_research_strips_personal_data_and_returns_sources(): void
    {
        config(['services.yoka_ai.key' => 'k']);
        Http::fake(['*/responses' => Http::response(['output' => [['type' => 'web_search_call'], ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Pasar kopi tumbuh 8% (2025).', 'annotations' => [['type' => 'url_citation', 'url' => 'https://www.example.com/a', 'title' => 'Laporan Kopi'], ['type' => 'url_citation', 'url' => 'javascript:alert(1)', 'title' => 'bad']]]]]]])]);
        $r = (new WebResearch)->search('tren kopi budi@mail.com 081234567890 2025');

        Http::assertSent(fn ($req) => ! str_contains(json_encode($req->data()), 'budi@mail.com') && ! str_contains(json_encode($req->data()), '081234567890'));
        $this->assertSame('example.com', $r['sources'][0]['host']);
        $this->assertCount(1, $r['sources']);
    }

    public function test_agent_answer_carries_visuals_sources_and_thinking_trace(): void
    {
        config(['services.yoka_ai.key' => 'k']);
        Http::fake([
            '*/responses' => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Pasar tumbuh.', 'annotations' => [['type' => 'url_citation', 'url' => 'https://riset.id/x', 'title' => 'Riset']]]]]]]),
            '*/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'w1', 'type' => 'function', 'function' => ['name' => 'web_search', 'arguments' => json_encode(['query' => 'tren pasar kopi Indonesia'])]]]]]]])
                ->push(['choices' => [['message' => ['content' => "**1. Tren**\n\n```yoka\n{\"type\":\"kpi\",\"title\":\"Pasar\",\"metrics\":[{\"label\":\"Tumbuh\",\"value\":\"8%\"}]}\n```\n\nSARAN: Apa aksinya?"]]]]),
        ]);
        $reply = app(YokaAssistantService::class)->ask($this->owner(), 'Bagaimana tren pasar kopi dan kompetitor?');

        $this->assertSame('kpi', $reply['response']['visuals'][0]['type']);
        $this->assertStringContainsString('[[visual:0]]', $reply['answer']);
        $this->assertSame('riset.id', $reply['response']['sources'][0]['host']);
        $this->assertContains('Mencari data terbaru di internet…', $reply['response']['thinking']['steps']);
    }
}
