<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\PlatformAdmin;
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
        config(['services.yoka_ai.key' => null, 'services.yoka_fonnte.webhook_secret' => 'rahasia-uji', 'services.yoka_fonnte.token' => 'tok']);
        app(PlatformAiOperations::class)->ensureAgents();
    }

    private function inbound(array $data, string $secret = 'rahasia-uji')
    {
        return $this->post('/platform-ai/whatsapp/webhook/'.$secret, $data + ['device' => '6285891111795']);
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

    public function test_inbound_message_creates_one_draft_and_sends_nothing(): void
    {
        Http::fake();
        $b = Business::factory()->create(['name' => 'Kopi Senja', 'phone' => '081234567890']);

        $this->inbound(['sender' => '6281234567890', 'name' => 'Rina', 'message' => 'kak cara tambah menu gimana?', 'inboxid' => 'A1'])->assertOk();
        $this->inbound(['sender' => '6281234567890', 'name' => 'Rina', 'message' => 'kak cara tambah menu gimana?', 'inboxid' => 'A1'])->assertOk();
        $this->inbound(['sender' => '6281234567890', 'name' => 'Rina', 'message' => 'sama printer juga', 'inboxid' => 'A2'])->assertOk();

        $this->assertSame(2, PlatformAiWhatsAppMessage::where('direction', 'inbound')->count());
        $tasks = PlatformAiTask::where('type', 'whatsapp_draft')->get();
        $this->assertCount(1, $tasks, 'Pesan susulan memperbarui draf yang sama.');
        $task = $tasks->first();
        $this->assertSame('proposed', $task->status);
        $this->assertSame('reply', $task->payload['kind']);
        $this->assertSame($b->id, $task->payload['matched_business_id']);
        $this->assertSame('cs', $task->agent->key);
        $this->assertStringContainsString('sama printer juga', $task->recommendation);
        Http::assertNothingSent();
    }

    public function test_group_and_own_device_messages_are_ignored(): void
    {
        $this->inbound(['sender' => '1203630@g.us', 'member' => '6281', 'message' => 'halo grup']);
        $this->inbound(['sender' => '6285891111795', 'message' => 'pesan sendiri']);
        $this->assertSame(0, PlatformAiWhatsAppMessage::count());
    }

    public function test_sensitive_message_is_flagged_high_risk(): void
    {
        $this->inbound(['sender' => '6281111111111', 'message' => 'saya mau refund, kepotong dua kali', 'inboxid' => 'S1']);
        $task = PlatformAiTask::where('type', 'whatsapp_draft')->firstOrFail();
        $this->assertSame('high', $task->risk);
        $this->assertTrue($task->payload['sensitive']);
        $this->assertStringContainsString('TOPIK SENSITIF', $task->recommendation);
    }

    public function test_approved_reply_is_sent_once_and_logged_to_thread(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true])]);
        $this->inbound(['sender' => '6281234567890', 'message' => 'halo', 'inboxid' => 'B1']);
        $task = PlatformAiTask::where('type', 'whatsapp_draft')->firstOrFail();
        $admin = PlatformAdmin::create(['name' => 'A', 'email' => 'a@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($admin, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp])
            ->patch(route('platform.ai-team.decision', $task), ['decision' => 'approve', 'message' => 'Halo Kak, ada yang bisa dibantu?'])->assertRedirect();

        Http::assertSentCount(1);
        $this->assertDatabaseHas('platform_ai_whatsapp_messages', ['direction' => 'outbound', 'phone' => '6281234567890', 'platform_ai_task_id' => $task->id]);
    }
}
