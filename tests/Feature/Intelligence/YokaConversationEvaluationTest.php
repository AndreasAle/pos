<?php

namespace Tests\Feature\Intelligence;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\User;
use App\Services\YokaAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YokaConversationEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fifty_multi_turn_conversation_cases_preserve_intent_and_format_safely(): void
    {
        config(['services.yoka_ai.key' => null]);
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $owner = User::factory()->create(['business_id' => $business->id, 'outlet_id' => $outlet->id, 'role' => 'owner']);
        $assistant = app(YokaAssistantService::class);
        $cases = $this->cases();
        $this->assertCount(50, $cases);

        foreach ($cases as $index => $case) {
            $first = $assistant->ask($owner, $case['first']);
            $reply = $assistant->ask($owner, $case['follow_up'], $first['conversation_id']);
            $this->assertSame($case['tool'], $reply['tool'], "Case {$index}: {$case['follow_up']}");
            if (isset($case['period'])) {
                $this->assertSame($case['period'], data_get($reply, 'data.period.label'), "Case {$index} period");
            }
            if (isset($case['block'])) {
                $this->assertSame($case['block'], data_get($reply, 'response.blocks.0.type'), "Case {$index} block");
            }
            if (($case['no_blocks'] ?? false) === true) {
                $this->assertSame([], $reply['response']['blocks'], "Case {$index} must not render blocks");
            }
        }
    }

    private function cases(): array
    {
        $cases = [];
        foreach (['Oke makasih bro', 'Terima kasih', 'Thanks', 'Sip bro', 'Mantap bro'] as $followUp) {
            $cases[] = ['first' => 'Berapa omzet hari ini?', 'follow_up' => $followUp, 'tool' => null, 'no_blocks' => true];
        }
        foreach (['Kalau dari tanggal 1?', 'Kalau sejak tanggal 1 bro?', 'Dari tanggal 1 ya', 'Sejak tanggal 1', 'Kalau dari tanggal 1 bulan ini?'] as $followUp) {
            $cases[] = ['first' => 'Pembayaran cash dan QRIS hari ini berapa?', 'follow_up' => $followUp, 'tool' => 'payment_breakdown', 'no_blocks' => true];
        }
        foreach (['Ada saran?', 'Ada rekomendasi?', 'Menurut kamu gimana?', 'Apa artinya?', 'Bisa analisis?'] as $followUp) {
            $cases[] = ['first' => 'Pembayaran bulan ini', 'follow_up' => $followUp, 'tool' => 'payment_breakdown', 'no_blocks' => true];
        }
        foreach (['Buat grafiknya', 'Tampilkan chart', 'Grafik dong', 'Buat perbandingannya', 'Tampilkan komposisinya'] as $followUp) {
            $cases[] = ['first' => 'Pembayaran bulan ini', 'follow_up' => $followUp, 'tool' => 'payment_breakdown', 'no_blocks' => true];
        }
        foreach (['Buat tabelnya', 'Tampilkan daftar', 'Kasih rincian', 'Detailnya dong', 'Tabel pembayaran'] as $followUp) {
            $cases[] = ['first' => 'Pembayaran bulan ini', 'follow_up' => $followUp, 'tool' => 'payment_breakdown', 'no_blocks' => true];
        }
        foreach (['Sekarang cek stok susu', 'Nah kalau stok kopi?', 'Cek bahan gula', 'Stok apa yang habis?', 'Bagaimana stock susu?'] as $followUp) {
            $cases[] = ['first' => 'Omzet bulan ini', 'follow_up' => $followUp, 'tool' => 'stock_status', 'no_blocks' => true];
        }
        foreach (['Produk paling laris?', 'Menu terlaris bulan ini?', 'Bagaimana margin produk?', 'Cek produk kopi', 'Produk mana yang laris?'] as $followUp) {
            $cases[] = ['first' => 'Cek stok', 'follow_up' => $followUp, 'tool' => 'product_performance', 'no_blocks' => true];
        }
        foreach (['Jangan grafik, jelaskan singkat', 'Tanpa grafik ya', 'Jangan tabel', 'Jelaskan singkat', 'Tidak usah grafik'] as $followUp) {
            $cases[] = ['first' => 'Pembayaran bulan ini', 'follow_up' => $followUp, 'tool' => 'payment_breakdown', 'no_blocks' => true];
        }
        foreach (['Cek shift hari ini', 'Siapa kasir yang jaga?', 'Bagaimana shift kasir?', 'Ringkasan shift', 'Cek kasir hari ini'] as $followUp) {
            $cases[] = ['first' => 'Omzet hari ini', 'follow_up' => $followUp, 'tool' => 'shift_summary', 'no_blocks' => true];
        }
        foreach (['Biaya resep kopi?', 'Cek resep kopi', 'Biaya produk kopi', 'Bagaimana resep menu?', 'Cek biaya menu'] as $followUp) {
            $cases[] = ['first' => 'Stok kopi', 'follow_up' => $followUp, 'tool' => 'recipe_cost', 'no_blocks' => true];
        }

        return $cases;
    }
}
