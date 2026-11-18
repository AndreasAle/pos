<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiDiscussion;
use App\Models\PlatformAiRun;
use App\Models\PlatformAiTask;
use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramGroup;
use App\Models\PlatformAiTelegramIdentity;
use App\Models\PlatformAiTelegramMessage;
use App\Models\PlatformAiTelegramUpdate;
use App\Services\PlatformAgents\AgentIntent;
use App\Services\PlatformAgents\ResponseGuard;
use App\Services\PlatformAgents\WorkingMemory;
use App\Services\PlatformAiOperations;
use App\Services\PlatformTelegramBotRegistry;
use App\Services\PlatformTelegramConversation;
use App\Services\PlatformTelegramNotifications;
use App\Services\PlatformTelegramOffice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kebijakan aksi & kualitas percakapan Telegram Office (Yoka AI Workforce 2.1).
 * Semua panggilan model dan Telegram di sini DI-MOCK: tes ini membuktikan logika routing,
 * kebijakan tool, validasi klaim, dedup, dan pengiriman hasil, BUKAN kualitas bahasa model asli.
 */
class PlatformAiActionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private array $aiResponses = [];

    private int $messageId = 100;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.yoka_ai.key' => 'test-key', 'services.yoka_ai.url' => 'https://ai.test/v1', 'queue.default' => 'sync', 'services.telegram_office.chime_in_chance' => 0]);
        foreach (['chief' => 'ramli', 'marketing' => 'yusuf', 'ads' => 'yono', 'finance' => 'luna', 'accounting' => 'nanda', 'cs' => 'adit', 'sales' => 'yosef'] as $key => $name) {
            config(["services.telegram_office.bots.{$key}" => ['token' => "{$name}:test-token", 'username' => "{$name}_yoka_bot", 'webhook_secret' => "secret-{$name}"]]);
        }
        app(PlatformTelegramBotRegistry::class)->sync();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'ai.test')) {
                return Http::response(array_shift($this->aiResponses) ?? $this->say('respons uji'));
            }

            return Http::response(['ok' => true, 'result' => ['message_id' => ++$this->messageId]]);
        });
    }

    private function say(string $content): array
    {
        return ['choices' => [['message' => ['content' => $content]]]];
    }

    private function toolCall(string $name, array $args): array
    {
        return ['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'c'.Str::random(4), 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($args)]]]]]]];
    }

    private function office(): array
    {
        $admin = PlatformAdmin::create(['name' => 'Owner', 'email' => 'owner@yoka.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $identity = PlatformAiTelegramIdentity::create(['platform_admin_id' => $admin->id, 'telegram_user_id' => 771122, 'status' => 'verified', 'verified_at' => now()]);
        $group = PlatformAiTelegramGroup::create(['platform_admin_id' => $admin->id, 'telegram_chat_id' => -100123456, 'title' => 'Yoka Office', 'enabled' => true, 'mode' => 'smart_discussion', 'notification_settings' => []]);

        return [$admin, $identity, $group];
    }

    private function send(PlatformAiTelegramGroup $group, string $text, ?int $replyTo = null, int $userId = 771122): void
    {
        $id = ++$this->messageId;
        $message = ['message_id' => $id, 'text' => $text, 'chat' => ['id' => $group->telegram_chat_id, 'type' => 'supergroup', 'title' => $group->title], 'from' => ['id' => $userId, 'username' => 'owner', 'is_bot' => false]];
        if ($replyTo) {
            $message['reply_to_message'] = ['message_id' => $replyTo, 'from' => ['is_bot' => true]];
        }
        $update = PlatformAiTelegramUpdate::create([
            'platform_ai_telegram_bot_id' => PlatformAiTelegramBot::where('credential_key', 'chief')->value('id'), 'platform_ai_telegram_group_id' => $group->id,
            'telegram_update_id' => 5000 + $id, 'telegram_chat_id' => $group->telegram_chat_id, 'telegram_message_id' => $id,
            'interaction_id' => (string) Str::uuid(), 'event_fingerprint' => hash('sha256', "policy|{$id}"), 'status' => 'received', 'payload' => ['message' => $message],
        ]);
        app(PlatformTelegramConversation::class)->process($update);
    }

    private function agent(string $key): PlatformAiAgent
    {
        return PlatformAiAgent::where('key', $key)->firstOrFail();
    }

    private function outbound(?string $agentKey = null)
    {
        return PlatformAiTelegramMessage::where('direction', 'outbound')
            ->when($agentKey, fn ($q) => $q->where('platform_ai_agent_id', $this->agent($agentKey)->id))->orderBy('id')->get();
    }

    /** @return array<int,array<string,mixed>> payload request ke model */
    private function aiRequests(): array
    {
        return collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'ai.test'))->map(fn ($pair) => $pair[0]->data())->values()->all();
    }

    private function toolNames(array $payload): array
    {
        return collect($payload['tools'] ?? [])->pluck('function.name')->all();
    }

    private function telegramTask(PlatformAiTelegramGroup $group, string $key, array $attrs = []): PlatformAiTask
    {
        return $this->agent($key)->tasks()->create(array_merge([
            'type' => 'chat_task', 'title' => 'Simulasi harga paket', 'summary' => 'Hitung simulasi paket.', 'risk' => 'low', 'status' => 'proposed', 'requires_approval' => true,
            'payload' => ['source' => 'telegram', 'created_by_agent' => $key, 'telegram_origin' => ['group_id' => $group->id, 'admin_id' => $group->platform_admin_id, 'chat_id' => $group->telegram_chat_id, 'thread_id' => null]],
        ], $attrs));
    }

    // 1
    public function test_direct_analysis_request_is_answered_inline_without_creating_a_task(): void
    {
        [, , $group] = $this->office();
        // Model "bandel" tetap mencoba create_task: harus ditolak di lapisan tool.
        $this->aiResponses = [
            $this->toolCall('create_task', ['title' => 'Analisis paket', 'summary' => 'x']),
            $this->say("asumsi: 20 toko bayar Rp99.000\nhasil: MRR Rp1.980.000"),
        ];
        $this->send($group, 'Luna, hitung simulasi harga paket 99 ribu');

        $this->assertDatabaseCount('platform_ai_tasks', 0);
        $this->assertNotContains('create_task', $this->toolNames($this->aiRequests()[0]));
        $this->assertStringContainsString('MRR Rp1.980.000', $this->outbound('finance')->pluck('content')->join("\n"));
        $this->assertDatabaseHas('platform_ai_runs', ['operation' => 'quality.direct_answer']);
        $this->assertDatabaseHas('platform_ai_runs', ['operation' => 'quality.task_blocked_by_policy']);
    }

    // 2
    public function test_explicit_task_request_creates_a_proposal_with_telegram_origin(): void
    {
        [, , $group] = $this->office();
        $this->aiResponses = [
            $this->toolCall('create_task', ['title' => 'Kalender konten tujuh hari', 'summary' => 'Susun kalender konten.']),
            $this->say('oke, kucatat'),
        ];
        $this->send($group, 'Ramli, catat ini sebagai tugas: kalender konten tujuh hari');

        $this->assertContains('create_task', $this->toolNames($this->aiRequests()[0]));
        $task = PlatformAiTask::where('title', 'Kalender konten tujuh hari')->firstOrFail();
        $this->assertSame('proposed', $task->status);
        $this->assertSame($group->id, data_get($task->payload, 'telegram_origin.group_id'));
        $this->assertStringContainsString('masuk papan buat di-approve', $this->outbound('chief')->pluck('content')->join("\n"));
    }

    // 3
    public function test_sensitive_external_action_still_requires_approval_and_sends_nothing_outside(): void
    {
        [, , $group] = $this->office();
        $this->aiResponses = [
            $this->toolCall('create_task', ['title' => 'Follow-up WA trial', 'summary' => 'Draf pesan WA ke toko trial.', 'risk' => 'medium']),
            $this->say('drafnya kusiapkan, kirimnya nunggu approve kamu ya bos'),
        ];
        $this->send($group, 'Adit, kirim WA ke pelanggan trial yang mau habis');

        $this->assertDatabaseHas('platform_ai_tasks', ['title' => 'Follow-up WA trial', 'status' => 'proposed', 'requires_approval' => true]);
        $this->assertSame(AgentIntent::EXTERNAL_ACTION, AgentIntent::classify('kirim WA ke pelanggan trial')['intent']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'graph.facebook.com') || str_contains($r->url(), 'fonnte'));
    }

    // 4
    public function test_reference_sends_the_actual_completed_deliverable(): void
    {
        [, , $group] = $this->office();
        $this->telegramTask($group, 'finance', ['status' => 'completed', 'executed_at' => now(), 'result' => ['report' => 'Paket Basic Rp99.000, Pro Rp199.000. Payback 3 bulan.']]);
        $this->send($group, 'Luna, coba kirim di sini dulu');

        $this->assertStringContainsString('Paket Basic Rp99.000', $this->outbound('finance')->pluck('content')->join("\n"));
        $this->assertSame([], $this->aiRequests(), 'Hasil yang sudah ada dikirim apa adanya, tanpa memanggil model.');
    }

    // 4b: tanpa nama, lewat reply ke pesan agen
    public function test_reply_to_an_agent_message_routes_back_to_that_agent(): void
    {
        [, , $group] = $this->office();
        $luna = $this->agent('finance');
        $msg = PlatformAiTelegramMessage::create(['platform_ai_telegram_group_id' => $group->id, 'platform_ai_agent_id' => $luna->id, 'direction' => 'outbound', 'channel' => 'telegram_group', 'telegram_message_id' => 4242, 'content' => 'simulasinya: basic 99rb', 'delivery_status' => 'delivered']);
        $this->aiResponses = [$this->say('lengkapnya: basic 99rb, pro 199rb')];
        $this->send($group, 'yang ini dong lengkapnya', 4242);

        $this->assertSame(1, $this->outbound('finance')->where('id', '>', $msg->id)->count());
    }

    // 5
    public function test_missing_deliverable_is_not_presented_as_completed(): void
    {
        [, , $group] = $this->office();
        $this->telegramTask($group, 'finance');
        $this->aiResponses = [$this->say('hasilnya belum ada bos, ini langsung kuhitung: basic 99rb x 20 toko = 1,98jt/bulan')];
        $this->send($group, 'Luna, coba kirim di sini dulu');

        $system = collect($this->aiRequests()[0]['messages'])->where('role', 'system')->pluck('content')->join("\n");
        $this->assertStringContainsString('BELUM ADA', $system);
        $this->assertStringNotContainsString('ini hasil "Simulasi harga paket"', $this->outbound('finance')->pluck('content')->join("\n"));
        $this->assertDatabaseHas('platform_ai_runs', ['operation' => 'quality.deliverable_missing']);
    }

    // 6
    public function test_agent_cannot_claim_unrecorded_task_or_future_job(): void
    {
        [, , $group] = $this->office();
        $this->aiResponses = [$this->say('Udah aku buat tugasnya di Operations Center. Nanti aku cek data trial ya bos. Trial aktif ada 3 toko.')];
        $this->send($group, 'Ramli, gimana trial?');

        $sent = $this->outbound('chief')->pluck('content')->join("\n");
        $this->assertStringContainsString('Trial aktif ada 3 toko.', $sent);
        $this->assertStringNotContainsString('Operations Center', $sent);
        $this->assertStringNotContainsString('Nanti aku cek', $sent);
        $this->assertDatabaseHas('platform_ai_runs', ['operation' => 'quality.unsupported_claim_removed']);
    }

    public function test_guard_uses_evidence_not_only_phrases(): void
    {
        $claim = 'Udah aku buat tugasnya, tinggal di-approve.';
        $this->assertSame('', ResponseGuard::validate($claim, ['created_tasks' => []])['text']);
        $this->assertSame($claim, ResponseGuard::validate($claim, ['created_tasks' => [7]])['text']);
        $this->assertSame('', ResponseGuard::validate('Tadi aku udah diskusi sama luna.', ['spoke_agents' => []])['text']);
        $this->assertNotSame('', ResponseGuard::validate('Tadi aku udah diskusi sama luna.', ['spoke_agents' => ['Luna']])['text']);
        $this->assertSame('', ResponseGuard::validate('Aku sudah kirim WA ke pelanggan.')['text']);
        $this->assertSame('', ResponseGuard::validate('Ada yang mau dicek atau dikerjain?')['text']);
    }

    // 7 & 8
    public function test_low_value_interjection_is_suppressed_but_risk_warning_is_kept(): void
    {
        $this->assertNotNull(ResponseGuard::lowValue('siap, setuju sama luna'));
        $this->assertNotNull(ResponseGuard::lowValue('nanti aku cek ya'));
        $this->assertNotNull(ResponseGuard::lowValue('trial 7 hari cukup', ['trial 7 hari cukup']));
        $this->assertNull(ResponseGuard::lowValue('hati-hati, budget segitu bikin rugi'));
        $this->assertNull(ResponseGuard::lowValue('setuju, tapi CAC kita Rp180rb jadi payback 2 bulan'));

        [, , $group] = $this->office();
        config(['services.telegram_office.chime_in_chance' => 100]);
        $before = PlatformAiTelegramMessage::count();
        $this->aiResponses = [$this->say('siap, setuju sama yusuf')];
        $this->assertFalse(app(PlatformTelegramOffice::class)->maybeChimeIn($group, 'konten', ['marketing'], (string) Str::uuid(), null, 1));
        $this->assertSame($before, PlatformAiTelegramMessage::count(), 'Pesan yang ditahan tidak tersimpan dan tidak memicu bot lain.');
        $this->assertDatabaseHas('platform_ai_runs', ['operation' => 'quality.low_value_suppressed']);
    }

    // 9
    public function test_identical_approval_notifications_are_deduplicated(): void
    {
        [, , $group] = $this->office();
        $a = $this->telegramTask($group, 'chief', ['title' => 'Tugas A']);
        $b = $this->telegramTask($group, 'chief', ['title' => 'Tugas B']);
        $notifications = app(PlatformTelegramNotifications::class);

        $first = $notifications->proposalNotice($group, null, [$a->id]);
        $this->assertStringContainsString('/platform/ai-team', $first);
        $this->assertNull($notifications->proposalNotice($group, null, [$a->id]), 'Tugas yang sama tidak diumumkan dua kali.');
        $second = $notifications->proposalNotice($group, null, [$b->id]);
        $this->assertStringContainsString('Tugas B', $second);
        $this->assertStringNotContainsString('/platform/ai-team', $second, 'Link dashboard tidak diulang dalam jendela cooldown.');
        // Versi approval baru = keadaan baru = boleh diumumkan lagi.
        $a->update(['approval_version' => 2]);
        $this->assertNotNull($notifications->proposalNotice($group, null, [$a->id]));
    }

    // 10 & 17
    public function test_completed_telegram_task_returns_result_to_origin_group_exactly_once(): void
    {
        [, , $group] = $this->office();
        $task = $this->telegramTask($group, 'marketing', ['status' => 'approved', 'approved_at' => now(), 'idempotency_key' => (string) Str::uuid()]);
        $this->aiResponses = [$this->say("Kalender 7 hari:\n1. Demo kasir\n2. Stok bahan")];
        app(PlatformAiOperations::class)->execute($task->id, $task->idempotency_key);

        $sent = $this->outbound('marketing');
        $this->assertStringContainsString('udah selesai', $sent->pluck('content')->join("\n"));
        $this->assertStringContainsString('Demo kasir', $sent->pluck('content')->join("\n"));
        $count = $sent->count();

        // Worker lain / retry memproses event yang sama: tidak ada pesan ganda.
        $this->assertFalse(app(PlatformTelegramNotifications::class)->taskOutcome($task->fresh('agent'), true));
        app(PlatformAiOperations::class)->execute($task->id, $task->idempotency_key);
        $this->assertSame($count, $this->outbound('marketing')->count());
        $this->assertDatabaseCount('platform_ai_telegram_notifications', 1);
    }

    // 11
    public function test_failed_task_reports_failure_and_never_success(): void
    {
        [, , $group] = $this->office();
        $task = $this->telegramTask($group, 'marketing', ['status' => 'approved', 'approved_at' => now(), 'idempotency_key' => (string) Str::uuid()]);
        config(['services.yoka_ai.key' => null]);
        app(PlatformAiOperations::class)->execute($task->id, $task->idempotency_key);

        $sent = $this->outbound('marketing')->pluck('content')->join("\n");
        $this->assertStringContainsString('gagal', $sent);
        $this->assertStringNotContainsString('udah selesai', $sent);
        $this->assertFalse(app(PlatformTelegramNotifications::class)->taskOutcome($task->fresh('agent'), true), 'Tugas yang tidak completed tidak boleh diumumkan selesai.');
    }

    // 12
    public function test_agent_recalls_its_own_previous_discussion_contribution(): void
    {
        [$admin, , $group] = $this->office();
        $discussion = PlatformAiDiscussion::create(['created_by' => $admin->id, 'objective' => 'fitur trial', 'status' => 'completed', 'limits' => []]);
        $discussion->contributions()->create(['platform_ai_agent_id' => $this->agent('marketing')->id, 'round' => 1, 'content' => 'Trial 7 hari plus email onboarding hari ke-2.']);
        $this->aiResponses = [$this->say('tadi aku usul trial 7 hari plus onboarding hari ke-2')];
        $this->send($group, 'Yusuf, tadi bagaimana hasil diskusi trial?');

        $system = collect($this->aiRequests()[0]['messages'])->where('role', 'system')->pluck('content')->join("\n");
        $this->assertStringContainsString('email onboarding hari ke-2', $system);
    }

    // 13
    public function test_working_memory_is_scoped_per_agent_and_per_group(): void
    {
        [$admin, , $group] = $this->office();
        $other = PlatformAiTelegramGroup::create(['platform_admin_id' => $admin->id, 'telegram_chat_id' => -100999, 'title' => 'Lain', 'enabled' => true, 'mode' => 'smart_discussion', 'notification_settings' => []]);
        $yusuf = $this->agent('marketing');
        $luna = $this->agent('finance');
        PlatformAiTelegramMessage::create(['platform_ai_telegram_group_id' => $other->id, 'platform_ai_agent_id' => $yusuf->id, 'direction' => 'outbound', 'channel' => 'telegram_group', 'content' => 'rahasia grup lain', 'delivery_status' => 'delivered']);
        PlatformAiTelegramMessage::create(['platform_ai_telegram_group_id' => $group->id, 'platform_ai_agent_id' => $yusuf->id, 'direction' => 'outbound', 'channel' => 'telegram_group', 'content' => 'hook demo kasir', 'delivery_status' => 'delivered']);

        $this->assertStringNotContainsString('rahasia grup lain', json_encode(WorkingMemory::for($yusuf, $group)));
        $this->assertStringContainsString('hook demo kasir', json_encode(WorkingMemory::for($yusuf, $group)));
        $this->assertStringNotContainsString('hook demo kasir', json_encode(WorkingMemory::for($luna, $group)));
    }

    // 14
    public function test_telegram_office_notifications_only_use_office_bots(): void
    {
        [, , $group] = $this->office();
        config(['services.telegram.bot_token' => 'merchant:token']);
        $task = $this->telegramTask($group, 'marketing', ['status' => 'completed', 'executed_at' => now(), 'result' => ['report' => 'hasil']]);
        app(PlatformTelegramNotifications::class)->taskOutcome($task->fresh('agent'), true);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'merchant:token'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'yusuf:test-token'));
    }

    // 15
    public function test_unauthorized_user_cannot_reach_the_model_or_tools(): void
    {
        [, , $group] = $this->office();
        $this->send($group, 'Luna, hitung margin', null, 999999);

        $this->assertSame([], $this->aiRequests());
        $this->assertDatabaseCount('platform_ai_tasks', 0);
    }

    // 9 (lintas worker) + 16
    public function test_bot_authored_updates_and_repeated_pending_alerts_do_not_loop(): void
    {
        [, , $group] = $this->office();
        $this->travelTo(now('Asia/Jakarta')->setTime(10, 0));
        $task = $this->telegramTask($group, 'chief');
        $task->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();
        $notifications = app(PlatformTelegramNotifications::class);
        $this->assertSame(1, $notifications->pendingAlerts());
        $this->assertSame(0, $notifications->pendingAlerts());
    }

    // 9 (kolaborasi nyata)
    public function test_consultation_runs_the_other_agent_and_persists_its_contribution(): void
    {
        [, , $group] = $this->office();
        $this->aiResponses = [
            $this->say('budget 2 juta masih aman, runway tetap 6 bulan'),
            $this->say('oke luna bilang aman, gas campaign 2 juta minggu ini'),
        ];
        $this->send($group, 'Yusuf, tanya Luna apakah campaign 2 juta masuk budget');

        $this->assertStringContainsString('runway tetap 6 bulan', $this->outbound('finance')->pluck('content')->join("\n"));
        $this->assertStringContainsString('gas campaign', $this->outbound('marketing')->pluck('content')->join("\n"));
        $this->assertDatabaseHas('platform_ai_discussion_contributions', ['platform_ai_agent_id' => $this->agent('finance')->id]);
        $yusufSystem = collect($this->aiRequests()[1]['messages'])->where('role', 'system')->pluck('content')->join("\n");
        $this->assertStringContainsString('runway tetap 6 bulan', $yusufSystem);
        $this->assertNotContains('create_task', $this->toolNames($this->aiRequests()[0]));
    }

    public function test_intent_classifier_covers_core_intents(): void
    {
        $cases = [
            'Yusuf, buat tiga hook video' => AgentIntent::BRAINSTORM,
            'Luna, hitung simulasi harga paket' => AgentIntent::CALCULATION,
            'Yosef, buat draf pendekatan pelanggan' => AgentIntent::DRAFT,
            'Ramli, rangkum kondisi trial' => AgentIntent::ANALYSIS,
            'Catat ini sebagai tugas' => AgentIntent::TASK_CREATION,
            'Jadikan tugas untuk Yusuf' => AgentIntent::TASK_CREATION,
            'buatkan task di dashboard' => AgentIntent::TASK_CREATION,
            'coba kirim di sini dulu' => AgentIntent::REFERENCE,
            'Yusuf, tanya Luna soal budget' => AgentIntent::DISCUSSION,
            'tugasnya udah sampai mana?' => AgentIntent::TASK_STATUS,
            'pasang iklan 500rb hari ini' => AgentIntent::EXTERNAL_ACTION,
        ];
        foreach ($cases as $text => $expected) {
            $this->assertSame($expected, AgentIntent::classify($text)['intent'], $text);
        }
        foreach (['buat tiga hook video', 'analisis trial', 'coba hitung margin'] as $text) {
            $this->assertFalse(AgentIntent::classify($text)['allows_task'], $text);
        }
    }

    public function test_quality_summary_reports_metrics_without_conversation_content(): void
    {
        [, , $group] = $this->office();
        $this->aiResponses = [$this->say('asumsi 20 toko, MRR 1,98jt')];
        $this->send($group, 'Luna, hitung MRR kalau 20 toko bayar 99rb');

        $summary = app(\App\Services\PlatformAiTelemetry::class)->qualitySummary();
        $this->assertSame(1, $summary['metrics']['direct_answer']);
        $this->assertStringNotContainsString('MRR', json_encode(PlatformAiRun::where('operation', 'like', 'quality.%')->pluck('metadata')));
    }
}
