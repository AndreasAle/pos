<?php

namespace Tests\Feature\Platform;

use App\Jobs\DraftWhatsAppReply;
use App\Models\Business;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiTask;
use App\Models\PlatformAiWhatsAppMessage;
use App\Services\PlatformAiOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformWhatsAppInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.yoka_ai.key' => null, 'services.yoka_ai.url' => 'https://ai.test/v1', 'services.yoka_fonnte.webhook_secret' => 'rahasia-uji', 'services.yoka_fonnte.token' => 'tok']);
        app(PlatformAiOperations::class)->ensureAgents();
    }

    private function inbound(array $data, string $secret = 'rahasia-uji')
    {
        return $this->post('/platform-ai/whatsapp/webhook/'.$secret, $data + ['device' => '6285891111795']);
    }

    private function aiSays(array $decision): void
    {
        config(['services.yoka_ai.key' => 'k']);
        Http::fake([
            'https://ai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode($decision)]]]]),
            'api.fonnte.com/*' => Http::response(['status' => true]),
        ]);
    }

    private function sentToFonnte(): array
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn ($r) => str_contains($r->url(), 'fonnte'))->values()->all();
    }

    public function test_wrong_or_missing_secret_is_rejected(): void
    {
        $this->inbound(['sender' => '6281234567890', 'message' => 'halo'], 'salah')->assertNotFound();
        config(['services.yoka_fonnte.webhook_secret' => null]);
        $this->inbound(['sender' => '6281234567890', 'message' => 'halo'], 'x')->assertNotFound();
        $this->assertSame(0, PlatformAiWhatsAppMessage::count());
    }

    public function test_fonnte_url_check_with_get_succeeds(): void
    {
        $this->get('/platform-ai/whatsapp/webhook/rahasia-uji')->assertOk();
    }

    public function test_adit_replies_by_himself_in_bubbles_and_logs_a_kanban_card_without_approval(): void
    {
        $this->aiSays(['relevant' => true, 'reply' => "bisa kak, menu tinggal tambah di Produk.\n\nmau aku jelasin langkahnya?", 'closing' => false, 'summary' => 'Tanya cara tambah menu.']);
        $b = Business::factory()->create(['name' => 'Kopi Senja', 'phone' => '081234567890']);

        $this->inbound(['sender' => '6281234567890', 'name' => 'Rina', 'message' => 'kak cara tambah menu gimana?', 'inboxid' => 'A1'])->assertOk();
        $this->inbound(['sender' => '6281234567890', 'name' => 'Rina', 'message' => 'kak cara tambah menu gimana?', 'inboxid' => 'A1'])->assertOk();

        $sent = $this->sentToFonnte();
        $this->assertCount(2, $sent, 'Dua bubble, dan pesan dobel dari Fonnte tidak dibalas ulang.');
        $this->assertSame('bisa kak, menu tinggal tambah di Produk.', $sent[0]['message']);
        $this->assertSame(2, PlatformAiWhatsAppMessage::where('direction', 'outbound')->count());

        $task = PlatformAiTask::where('type', 'whatsapp_conversation')->sole();
        $this->assertSame('completed', $task->status);
        $this->assertFalse($task->requires_approval);
        $this->assertSame($b->id, $task->payload['matched_business_id']);
        $this->assertSame('cs', $task->agent->key);
        $this->assertSame(0, PlatformAiTask::where('type', 'whatsapp_draft')->count());
    }

    public function test_off_topic_message_is_only_read(): void
    {
        $this->aiSays(['relevant' => false, 'reply' => '', 'closing' => false, 'summary' => 'Promosi pinjol.']);
        $this->inbound(['sender' => '6289999999999', 'message' => 'pinjaman cepat cair tanpa agunan', 'inboxid' => 'O1']);

        $this->assertSame([], $this->sentToFonnte());
        $this->assertSame(0, PlatformAiTask::count());
        $this->assertSame(1, PlatformAiWhatsAppMessage::where('direction', 'inbound')->count());
    }

    public function test_closing_goes_to_in_progress_column(): void
    {
        $this->aiSays(['relevant' => true, 'reply' => 'siap kak, bisa langsung daftar trial di yokapos.com ya', 'closing' => true, 'summary' => 'Mau langganan untuk 2 outlet.']);
        $this->inbound(['sender' => '6282222222222', 'name' => 'Budi', 'message' => 'saya mau langganan buat 2 outlet', 'inboxid' => 'C1']);

        $task = PlatformAiTask::where('type', 'whatsapp_conversation')->sole();
        $this->assertSame('approved', $task->status);
        $this->assertStringStartsWith('Closing WA', $task->title);
        $this->assertNull($task->idempotency_key);
        $this->assertFalse(app(PlatformAiOperations::class)->canExecute($task), 'Kartu CS tidak pernah dieksekusi agen lain.');
    }

    public function test_sensitive_message_gets_one_safe_bubble_and_red_card(): void
    {
        $this->aiSays(['relevant' => true, 'reply' => "baik kak, sudah kami terima dan sedang dicek.\n\nnanti refundnya kami proses ya", 'closing' => false, 'summary' => 'Minta refund.']);
        $this->inbound(['sender' => '6281111111111', 'message' => 'saya mau refund, kepotong dua kali', 'inboxid' => 'S1']);

        $this->assertCount(1, $this->sentToFonnte());
        $task = PlatformAiTask::where('type', 'whatsapp_conversation')->sole();
        $this->assertSame('high', $task->risk);
        $this->assertSame('approved', $task->status);
        $this->assertTrue($task->payload['sensitive']);
    }

    public function test_forbidden_promises_are_stripped_and_bubbles_capped(): void
    {
        $b = DraftWhatsAppReply::bubbles("Bisa kak. Ada diskon 50% khusus kakak!\n\nsatu\n\ndua\n\ntiga\n\nempat");
        $this->assertSame(['Bisa kak.', 'satu', 'dua'], $b);

        // Penutup basa-basi dari tes live 2026-11-19 dibuang; urutan isi tetap.
        $live = DraftWhatsAppReply::bubbles("Kalau Kakak butuh bantuan lain seputar aplikasi kasir, jangan ragu tanya ya!\n\nUntuk pinjol, Yoka POS belum menyediakan fasilitas tersebut.");
        $this->assertSame(['Untuk pinjol, Yoka POS belum menyediakan fasilitas tersebut.'], $live);
        $this->assertSame(['Kakak bisa daftar di yokapos.com.'], DraftWhatsAppReply::bubbles('Kakak bisa daftar di yokapos.com. Kalau ada yang mau ditanyakan soal proses langganan, saya siap bantu ya.'));
        $this->assertSame(['halo kak, siap bantu!'], DraftWhatsAppReply::bubbles('halo kak, siap bantu!'), 'Kalau cuma itu isinya, tetap dikirim.');
    }

    public function test_burst_limit_stops_bot_loops(): void
    {
        $this->aiSays(['relevant' => true, 'reply' => 'oke kak', 'closing' => false, 'summary' => 'x']);
        for ($i = 0; $i < DraftWhatsAppReply::BURST_LIMIT + 3; $i++) {
            $this->inbound(['sender' => '6283333333333', 'message' => "pesan {$i}", 'inboxid' => "L{$i}"]);
        }
        $this->assertCount(DraftWhatsAppReply::BURST_LIMIT, $this->sentToFonnte());
    }

    public function test_normal_conversation_of_live_test_is_not_cut_off(): void
    {
        // Tes live 2026-11-19: 8 bubble dalam sejam membuat Adit berhenti menjawab.
        $this->aiSays(['relevant' => true, 'reply' => "bisa kak\n\nmau coba dulu?", 'closing' => false, 'summary' => 'x']);
        foreach (range(1, 6) as $i) {
            $this->travel(3)->minutes();
            $this->inbound(['sender' => '6285555555555', 'message' => "tanya {$i}", 'inboxid' => "N{$i}"]);
        }
        $this->assertCount(12, $this->sentToFonnte());
    }

    public function test_burst_of_customer_messages_gets_one_answer_for_the_latest(): void
    {
        $this->aiSays(['relevant' => true, 'reply' => 'bisa kak', 'closing' => false, 'summary' => 'x']);
        // Dua pesan sudah tersimpan sebelum job pertama sempat jalan.
        $first = PlatformAiWhatsAppMessage::create(['direction' => 'inbound', 'phone' => '6286666666666', 'body' => 'kak']);
        $second = PlatformAiWhatsAppMessage::create(['direction' => 'inbound', 'phone' => '6286666666666', 'body' => 'yokapos bisa buat cafe ga?']);
        (new DraftWhatsAppReply($first->id))->handle();
        (new DraftWhatsAppReply($second->id))->handle();

        $this->assertCount(1, $this->sentToFonnte());
    }

    public function test_without_ai_nothing_is_sent_and_card_asks_for_manual_reply(): void
    {
        Http::fake();
        $this->inbound(['sender' => '6284444444444', 'message' => 'halo kak', 'inboxid' => 'N1']);
        Http::assertNothingSent();
        $task = PlatformAiTask::where('type', 'whatsapp_conversation')->sole();
        $this->assertSame('approved', $task->status);
        $this->assertStringContainsString('manual', $task->recommendation);
    }

    public function test_dashboard_adit_is_synced_with_real_whatsapp_activity(): void
    {
        $adit = PlatformAiAgent::where('key', 'cs')->firstOrFail();
        // Draf versi lama untuk nomor yang sama (sebelum balasan otomatis).
        $old = $adit->tasks()->create(['type' => 'whatsapp_draft', 'title' => 'Balas WA · lama', 'summary' => 'x', 'status' => 'proposed', 'requires_approval' => true, 'risk' => 'medium', 'payload' => ['kind' => 'reply', 'phone' => '6281234567890']]);
        $this->aiSays(['relevant' => true, 'reply' => 'bisa kak', 'closing' => false, 'summary' => 'Tanya kafe.']);
        $this->inbound(['sender' => '6281234567890', 'message' => 'bisa buat cafe?', 'inboxid' => 'Y1']);

        $this->assertSame('rejected', $old->fresh()->status);
        $waPrompt = collect(Http::recorded())->map(fn ($p) => $p[0])->first(fn ($r) => str_contains($r->url(), 'ai.test'));
        $this->assertStringContainsString('trial gratis 7 hari', json_encode($waPrompt->data(), JSON_UNESCAPED_UNICODE));

        Http::fake(['https://ai.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Udah aku balas 1 chat hari ini.']]]])]);
        app(PlatformAiOperations::class)->reply($adit, 'kau sudah jawab semua belum');
        Http::assertSent(function ($r) {
            $body = json_encode($r->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($body, 'RINGKASAN WHATSAPP HARI INI') && str_contains($body, '1 pesan masuk') && str_contains($body, 'PENGECUALIAN UNTUK KAMU');
        });
        // Ringkasan ada tepat sebelum pertanyaan Bos, setelah riwayat yang mungkin berisi jawaban lama keliru.
        Http::assertSent(function ($r) {
            $messages = $r->data()['messages'] ?? [];
            $beforeUser = $messages[count($messages) - 2] ?? [];

            return ($beforeUser['role'] ?? null) === 'system' && str_contains((string) $beforeUser['content'], 'KOREKSI');
        });
    }

    public function test_group_and_own_device_messages_are_ignored(): void
    {
        $this->inbound(['sender' => '1203630@g.us', 'member' => '6281', 'message' => 'halo grup']);
        $this->inbound(['sender' => '6285891111795', 'message' => 'pesan sendiri']);
        $this->assertSame(0, PlatformAiWhatsAppMessage::count());
    }

    public function test_adit_chat_can_read_the_inbox_and_channel_shows_connected(): void
    {
        $this->inbound(['sender' => '6281234567890', 'name' => 'Rina', 'message' => 'Halo', 'inboxid' => 'C1']);
        config(['services.yoka_ai.key' => 'k']);
        Http::fakeSequence('https://ai.test/v1/chat/completions')
            ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 't1', 'type' => 'function', 'function' => ['name' => 'whatsapp_inbox', 'arguments' => '{}']]]]]]])
            ->push(['choices' => [['message' => ['content' => 'Ada 1 pesan masuk dari Rina.']]]]);
        $adit = PlatformAiAgent::where('key', 'cs')->firstOrFail();

        $result = app(PlatformAiOperations::class)->reply($adit, 'adit ada pesan masuk gak');

        $this->assertContains('whatsapp_inbox', $result['tools_used']);
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), 'Rina'));
        $this->assertTrue(app(PlatformAiOperations::class)->externalConnectionReady('cs'));
    }
}
