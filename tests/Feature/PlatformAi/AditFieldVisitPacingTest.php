<?php

namespace Tests\Feature\PlatformAi;

use App\Jobs\DraftWhatsAppReply;
use App\Models\PlatformAdmin;
use App\Models\PlatformAiProspect;
use App\Models\PlatformAiTask;
use App\Models\PlatformAiTelegramGroup;
use App\Services\PlatformAgents\ProspectDesk;
use App\Services\PlatformAgents\Sales\SalesOrchestrator;
use App\Services\PlatformAiOperations;
use App\Services\PlatformTelegramBotRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Jeda balas prospek lewat queue, tawaran tim mampir ke warung, dan kabar Ramli di Telegram. */
class AditFieldVisitPacingTest extends TestCase
{
    use RefreshDatabase;

    private PlatformAiProspect $p;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-24 03:00:00');
        $admin = PlatformAdmin::create(['name' => 'Owner', 'email' => 'o@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);
        app(PlatformAiOperations::class)->ensureAgents();
        $this->p = app(ProspectDesk::class)->check(new PlatformAiProspect([
            'business_name' => 'Pecel Lele Cak Budi', 'contact_name' => 'Budi', 'phone' => '081200000001', 'business_type' => 'Pecel Lele', 'city' => 'Palembang', 'source' => 'kenalan', 'status' => 'baru',
        ]));
        $this->p->update(['status' => 'dihubungi', 'contact_count' => 1, 'sales_stage' => 'outreach_sent']);
        config(['services.telegram_office.bots.chief' => ['token' => 'ramli:test-token', 'username' => 'ramli_yoka_bot', 'webhook_secret' => 'secret-ramli']]);
        app(PlatformTelegramBotRegistry::class)->sync();
        PlatformAiTelegramGroup::create(['platform_admin_id' => $admin->id, 'telegram_chat_id' => -100123456, 'title' => 'Yoka Office', 'enabled' => true, 'mode' => 'smart_discussion', 'notification_settings' => []]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]])]);
    }

    private function turn(string $text, int $id): array
    {
        app(ProspectDesk::class)->inbound($this->p->phone_normalized, $text);

        return app(SalesOrchestrator::class)->onInbound($this->p->fresh(), $text, $id);
    }

    public function test_prospect_replies_are_delayed_one_to_three_minutes_but_stop_is_immediate(): void
    {
        config(['services.yoka_fonnte.webhook_secret' => 'rahasia-uji']);
        Bus::fake();
        $this->post('/platform-ai/whatsapp/webhook/rahasia-uji', ['sender' => '6281200000001', 'message' => 'boleh tau skemanya gimana?', 'inboxid' => 'V1'])->assertOk();
        Bus::assertDispatched(DraftWhatsAppReply::class, function ($job) {
            $seconds = now()->diffInSeconds($job->delay, true);

            return $seconds >= 60 && $seconds <= 150;
        });
        $this->post('/platform-ai/whatsapp/webhook/rahasia-uji', ['sender' => '6281200000001', 'message' => 'stop jangan hubungi lagi', 'inboxid' => 'V2'])->assertOk();
        Bus::assertDispatchedAfterResponse(DraftWhatsAppReply::class);
        // Bukan prospek (nomor tak dikenal / pelanggan CS) tetap dibalas cepat.
        $this->post('/platform-ai/whatsapp/webhook/rahasia-uji', ['sender' => '6289999999999', 'message' => 'halo', 'inboxid' => 'V3'])->assertOk();
        Bus::assertDispatchedAfterResponseTimes(DraftWhatsAppReply::class, 2);
    }

    public function test_visit_is_offered_once_then_consent_creates_brief_and_ramli_tells_telegram(): void
    {
        $r = $this->turn('kerenn, nanti gimana cara masangnya mas?', 1);
        $this->assertTrue($r['strategy']['offer_visit']);
        $this->assertStringContainsString('mampir ke warungnya', $r['context']);
        $this->assertStringContainsString('Jangan sebut jam, tanggal', $r['context']);
        $this->assertNotNull($this->p->fresh()->intelligence['visit_offered_at']);

        $again = $this->turn('harganya berapa?', 2);
        $this->assertFalse($again['strategy']['offer_visit'], 'Tawaran kunjungan hanya sekali.');

        $yes = $this->turn('boleh mas, kapan aja', 3);
        $this->assertSame('ARRANGE_FIELD_VISIT', $yes['strategy']['objective']);
        $this->assertSame('ready_for_field_visit', $this->p->fresh()->sales_stage);
        $task = PlatformAiTask::where('type', 'field_visit_brief')->sole();

        $tg = collect(Http::recorded())->map(fn ($x) => $x[0])->filter(fn ($r) => str_contains($r->url(), 'api.telegram.org') && str_contains($r->url(), 'sendMessage'))->values();
        $this->assertCount(1, $tg);
        $this->assertStringContainsString('jadwal kunjungan', $tg[0]['text']);
        $this->assertStringContainsString('Pecel Lele Cak Budi di Palembang', $tg[0]['text']);
        $this->assertStringContainsString("tugas #{$task->id}", $tg[0]['text']);

        $this->turn('mau lihat printernya langsung juga', 4);
        $this->assertCount(1, collect(Http::recorded())->filter(fn ($x) => str_contains($x[0]->url(), 'sendMessage')), 'Ramli mengabari sekali per brief.');
    }

    public function test_declining_the_offer_does_not_create_a_visit(): void
    {
        $this->turn('gimana cara pasangnya?', 10);
        $this->turn('belum dulu mas, nanti aja', 11);
        $this->assertSame(0, PlatformAiTask::where('type', 'field_visit_brief')->count());
    }
}
