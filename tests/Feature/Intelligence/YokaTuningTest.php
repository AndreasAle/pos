<?php

namespace Tests\Feature\Intelligence;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\User;
use App\Services\Intelligence\YokaAgent;
use App\Services\YokaAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Tuning Tanya Yoka: sapaan tidak ditolak, tidak mengulang angka, solusi di menu nyata, tanpa basa-basi. */
class YokaTuningTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        return User::factory()->create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'role' => 'owner']);
    }

    public function test_greetings_are_social_and_in_scope(): void
    {
        foreach (['bro', 'Bro', 'p', 'halo min', 'woi'] as $i => $q) {
            if ($q === 'woi') {
                $this->assertFalse(YokaAgent::isSocial($q));

                continue;
            }
            $this->assertTrue(YokaAgent::isSocial($q), $q);
        }
        $this->assertTrue(YokaAgent::isSocial('makasih banyak bro'));
        $this->assertFalse(YokaAgent::isSocial('bro omzet hari ini berapa?'));
    }

    public function test_bro_gets_a_friendly_reply_without_scope_check_or_forced_tools(): void
    {
        config(['services.yoka_ai.key' => 'test-key']);
        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => "Tentu! halo bro! mau cek apa hari ini, omzet, menu laris, atau stok?\n\nSemoga membantu ya.\n\nSARAN: Omzet hari ini? | Menu terlaris? | Stok menipis?"]]]])]);
        $reply = app(YokaAssistantService::class)->ask($this->owner(), 'bro');

        $this->assertStringContainsString('halo bro', $reply['answer']);
        $this->assertStringNotContainsString('di luar bisnismu', $reply['answer']);
        $this->assertStringNotContainsString('Semoga membantu', $reply['answer']);
        $this->assertStringNotContainsString('Tentu!', $reply['answer']);
        Http::assertSentCount(1); // tidak ada panggilan penjaga lingkup
        Http::assertSent(fn ($r) => ($r->data()['tool_choice'] ?? null) === 'auto');
    }

    public function test_system_prompt_carries_new_rules_and_real_menu_map(): void
    {
        config(['services.yoka_ai.key' => 'test-key']);
        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Omzet hari ini Rp1,2 jt.']]]])]);
        app(YokaAssistantService::class)->ask($this->owner(), 'gimana omzet hari ini');
        $system = collect(Http::recorded())->map(fn ($x) => $x[0]->data()['messages'][0]['content'] ?? '')->first(fn ($c) => str_contains($c, 'Kamu Yoka'));

        $this->assertNotNull($system);
        foreach (['JANGAN MENGULANG ANGKA', 'ANGKA BERKONTEKS', 'SOLUSI YANG BISA DIKERJAKAN', 'PETA MENU APLIKASI', 'SAPAAN', 'Promo & Diskon'] as $rule) {
            $this->assertStringContainsString($rule, $system);
        }
    }

    public function test_polish_strips_filler(): void
    {
        $this->assertSame('Omzet naik **12%**.', YokaAgent::polish("Pertanyaan bagus! Omzet naik **12%**.\n\nJangan ragu bertanya lagi ya."));
    }
}
