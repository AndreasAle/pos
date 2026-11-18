<?php

namespace Tests\Feature\Platform;

use App\Jobs\ProcessPlatformTelegramUpdate;
use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiConversationState;
use App\Models\PlatformAiDiscussionContribution;
use App\Models\PlatformAiRun;
use App\Models\PlatformAiTask;
use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramGroup;
use App\Models\PlatformAiTelegramIdentity;
use App\Models\PlatformAiTelegramMessage;
use App\Models\PlatformAiTelegramUpdate;
use App\Services\PlatformAiContext;
use App\Services\PlatformTelegramBotRegistry;
use App\Services\PlatformTelegramConversation;
use App\Services\PlatformTelegramGateway;
use App\Services\PlatformTelegramNotifications;
use App\Services\PlatformTelegramRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformTelegramOfficeTest extends TestCase
{
    use RefreshDatabase;

    private array $aiResponses = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.yoka_ai.key' => null, 'queue.default' => 'sync', 'services.telegram_office.chime_in_chance' => 0]);
        foreach (['chief' => 'ramli', 'marketing' => 'yusuf', 'ads' => 'yono', 'finance' => 'luna', 'accounting' => 'nanda', 'cs' => 'adit', 'sales' => 'yosef'] as $key => $name) {
            config(["services.telegram_office.bots.{$key}" => ['token' => "{$name}:test-token", 'username' => "{$name}_yoka_bot", 'webhook_secret' => "secret-{$name}"]]);
        }
        app(PlatformTelegramBotRegistry::class)->sync();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'ai.test')) {
                return Http::response(array_shift($this->aiResponses) ?? ['choices' => [['message' => ['content' => 'Respons uji.']]]]);
            }

            return config('telegram_test_fail')
                ? Http::response(['ok' => false], 500)
                : Http::response(['ok' => true, 'result' => ['message_id' => 9001]]);
        });
    }

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => 'Owner', 'email' => 'owner@yoka.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
    }

    private function linkedOffice(string $mode = 'smart_discussion'): array
    {
        $admin = $this->admin();
        $identity = PlatformAiTelegramIdentity::create(['platform_admin_id' => $admin->id, 'telegram_user_id' => 771122, 'status' => 'verified', 'verified_at' => now()]);
        $group = PlatformAiTelegramGroup::create(['platform_admin_id' => $admin->id, 'telegram_chat_id' => -100123456, 'title' => 'Yoka Office', 'enabled' => true, 'mode' => $mode, 'notification_settings' => []]);

        return [$admin, $identity, $group];
    }

    private function update(PlatformAiTelegramBot $bot, string $text, int $messageId = 10, int $userId = 771122, ?PlatformAiTelegramGroup $group = null, bool $isBot = false): PlatformAiTelegramUpdate
    {
        return PlatformAiTelegramUpdate::create([
            'platform_ai_telegram_bot_id' => $bot->id, 'platform_ai_telegram_group_id' => $group?->id,
            'telegram_update_id' => 1000 + $messageId, 'telegram_chat_id' => $group?->telegram_chat_id ?? $userId,
            'telegram_message_id' => $messageId, 'interaction_id' => (string) Str::uuid(),
            'event_fingerprint' => hash('sha256', "test|{$bot->id}|{$messageId}|{$text}"), 'status' => 'received',
            'payload' => ['message' => ['message_id' => $messageId, 'text' => $text, 'chat' => ['id' => $group?->telegram_chat_id ?? $userId, 'type' => $group ? 'supergroup' : 'private', 'title' => $group?->title], 'from' => ['id' => $userId, 'username' => 'owner', 'is_bot' => $isBot]]],
        ]);
    }

    public function test_webhook_requires_secret_and_deduplicates_overlapping_bot_updates(): void
    {
        Queue::fake();
        $ramli = PlatformAiTelegramBot::where('credential_key', 'chief')->firstOrFail();
        $yusuf = PlatformAiTelegramBot::where('credential_key', 'marketing')->firstOrFail();
        $payload = ['update_id' => 99, 'message' => ['message_id' => 55, 'text' => 'halo', 'chat' => ['id' => -1001, 'type' => 'supergroup'], 'from' => ['id' => 7, 'is_bot' => false]]];

        $this->postJson(route('platform.telegram.webhook', $ramli->public_key), $payload)->assertForbidden();
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-ramli')->postJson(route('platform.telegram.webhook', $ramli->public_key), $payload)->assertAccepted();
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'secret-yusuf')->postJson(route('platform.telegram.webhook', $yusuf->public_key), $payload + ['update_id' => 100])->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame(1, PlatformAiTelegramUpdate::count());
        $this->assertSame('supergroup', data_get(PlatformAiTelegramUpdate::first()->payload, 'message.chat.type'));
        Queue::assertPushed(ProcessPlatformTelegramUpdate::class);
    }

    public function test_one_time_link_code_links_numeric_identity_and_cannot_be_reused(): void
    {
        $admin = $this->admin();
        PlatformAiTelegramIdentity::create(['platform_admin_id' => $admin->id, 'status' => 'pending', 'link_code_hash' => Hash::make('ABCD-EFGH'), 'link_expires_at' => now()->addMinutes(10)]);
        $bot = PlatformAiTelegramBot::where('credential_key', 'chief')->firstOrFail();
        app(PlatformTelegramConversation::class)->process($this->update($bot, '/link ABCD-EFGH', 1, 771122));

        $this->assertDatabaseHas('platform_ai_telegram_identities', ['platform_admin_id' => $admin->id, 'telegram_user_id' => 771122, 'status' => 'verified']);
        $other = PlatformAiTelegramIdentity::create(['platform_admin_id' => $admin->id, 'status' => 'pending', 'link_code_hash' => Hash::make('ABCD-EFGH'), 'link_expires_at' => now()->subMinute()]);
        app(PlatformTelegramConversation::class)->process($this->update($bot, '/link ABCD-EFGH', 2, 991122));
        $this->assertSame('pending', $other->fresh()->status);
    }

    public function test_unauthorized_telegram_user_cannot_invoke_agents_or_tools(): void
    {
        [, , $group] = $this->linkedOffice();
        $bot = PlatformAiTelegramBot::where('credential_key', 'chief')->firstOrFail();
        $update = $this->update($bot, '/ask ramli tampilkan data keuangan', 12, 991122, $group);

        app(PlatformTelegramConversation::class)->process($update);

        $this->assertSame('unauthorized', $update->fresh()->status);
        $this->assertSame(0, PlatformAiRun::count());
        $this->assertSame(0, PlatformAiTask::count());
    }

    public function test_direct_agent_name_routes_to_yusuf_and_keeps_other_agents_silent(): void
    {
        [, , $group] = $this->linkedOffice();
        $bot = PlatformAiTelegramBot::where('credential_key', 'chief')->firstOrFail();
        app(PlatformTelegramConversation::class)->process($this->update($bot, 'Yusuf, ide konten hari ini?', 20, 771122, $group));

        $outbound = PlatformAiTelegramMessage::where('direction', 'outbound')->get();
        $this->assertCount(1, $outbound);
        $this->assertSame('marketing', $outbound->first()->agent?->key ?? PlatformAiTelegramBot::find($outbound->first()->platform_ai_telegram_bot_id)->agent->key);
    }

    public function test_smart_discussion_is_bounded_and_bot_messages_do_not_loop(): void
    {
        [, , $group] = $this->linkedOffice('smart_discussion');
        $bot = PlatformAiTelegramBot::where('credential_key', 'chief')->firstOrFail();
        app(PlatformTelegramConversation::class)->process($this->update($bot, 'Menurut kalian budget iklan dan kondisi keuangan aman?', 30, 771122, $group));
        $this->assertLessThanOrEqual(3, PlatformAiTelegramMessage::where('direction', 'outbound')->count());
        $before = PlatformAiTelegramMessage::count();
        app(PlatformTelegramConversation::class)->process($this->update($bot, 'pesan dari bot', 31, 771122, $group, true));
        $this->assertSame($before, PlatformAiTelegramMessage::count());
    }

    public function test_team_meeting_persists_real_attributed_contributions(): void
    {
        [, , $group] = $this->linkedOffice();
        $bot = PlatformAiTelegramBot::where('credential_key', 'chief')->firstOrFail();
        app(PlatformTelegramConversation::class)->process($this->update($bot, '/meeting Evaluasi marketing, sales, dan biaya akuisisi', 40, 771122, $group));

        $this->assertDatabaseHas('platform_ai_discussions', ['status' => 'completed']);
        $this->assertDatabaseCount('platform_ai_telegram_discussion_bindings', 1);
        $this->assertGreaterThanOrEqual(2, PlatformAiDiscussionContribution::count());
        $this->assertGreaterThanOrEqual(3, PlatformAiTelegramMessage::where('direction', 'outbound')->count());
    }

    public function test_telegram_conversation_state_is_isolated_from_dashboard_state(): void
    {
        [$admin, , $group] = $this->linkedOffice();
        $agent = PlatformAiAgent::where('key', 'chief')->firstOrFail();
        app(PlatformAiContext::class)->reviseConversationState($agent, $admin, 'Topik dashboard');
        app(PlatformAiContext::class)->reviseConversationState($agent, $admin, 'Topik Telegram', [], null, 'telegram_group', (string) $group->telegram_chat_id);

        $this->assertSame(2, PlatformAiConversationState::count());
        $this->assertDatabaseHas('platform_ai_conversation_states', ['channel' => 'platform_dashboard', 'context_key' => 'default']);
        $this->assertDatabaseHas('platform_ai_conversation_states', ['channel' => 'telegram_group', 'context_key' => (string) $group->telegram_chat_id]);
    }

    public function test_failed_delivery_is_not_recorded_as_successful(): void
    {
        [, , $group] = $this->linkedOffice();
        $bot = PlatformAiTelegramBot::where('credential_key', 'chief')->firstOrFail();
        config(['telegram_test_fail' => true]);
        $message = app(PlatformTelegramGateway::class)->send($bot, $group, 'Tes gagal');

        $this->assertSame('failed', $message->delivery_status);
        $this->assertNull($message->delivered_at);
    }

    public function test_pending_alert_is_deduplicated_and_respects_notification_preferences(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setTime(12, 0));
        [, , $group] = $this->linkedOffice();
        $group->update(['notification_settings' => ['alerts' => true, 'quiet_start' => '22:00', 'quiet_end' => '07:00', 'max_alerts_per_day' => 5]]);
        $task = PlatformAiTask::create([
            'platform_ai_agent_id' => PlatformAiAgent::where('key', 'chief')->value('id'),
            'type' => 'recommendation',
            'title' => 'Keputusan lama',
            'summary' => 'Keputusan ini perlu ditinjau.',
            'status' => 'proposed',
            'requires_approval' => true,
        ]);
        PlatformAiTask::whereKey($task->id)->update(['created_at' => now()->subDays(2)]);
        $notifications = app(PlatformTelegramNotifications::class);

        $this->assertSame(1, $notifications->pendingAlerts());
        $this->assertSame(0, $notifications->pendingAlerts());
        $this->assertSame(1, PlatformAiTelegramMessage::where('direction', 'outbound')->count());

        $group->update(['notification_settings' => ['alerts' => false, 'quiet_start' => '22:00', 'quiet_end' => '07:00', 'max_alerts_per_day' => 5]]);
        PlatformAiTask::query()->update(['created_at' => now()->subDays(3)]);
        $this->assertSame(0, $notifications->pendingAlerts());
    }

    public function test_natural_conversation_routes_without_mentions_and_bounds_participants(): void
    {
        [, , $group] = $this->linkedOffice();
        $router = app(PlatformTelegramRouter::class);
        $casual = $router->route('gimana prioritas Yoka hari ini?', $group);
        $this->assertSame('message', $casual['kind']);
        $this->assertSame(['chief'], $casual['agents']);
        $route = $router->route('Bahas konten, iklan, biaya, sales, customer, dan akuntansi', $group);
        $this->assertCount(3, $route['agents']);
        $this->assertSame(['marketing'], $router->route('Yusuf menurutmu gimana?', $group)['agents']);
    }

    public function test_telegram_created_task_appears_in_dashboard_and_remains_pending_approval(): void
    {
        [, , $group] = $this->linkedOffice();
        config(['services.yoka_ai.key' => 'test-key', 'services.yoka_ai.url' => 'https://ai.test/v1']);
        $toolResponse = [
            'choices' => [[
                'message' => [
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'task-1',
                        'type' => 'function',
                        'function' => [
                            'name' => 'create_task',
                            'arguments' => json_encode([
                                'title' => 'Strategi konten tujuh hari',
                                'summary' => 'Susun kalender konten tujuh hari.',
                                'assign_to' => 'marketing',
                            ]),
                        ],
                    ]],
                ],
            ]],
        ];
        $finalResponse = [
            'choices' => [[
                'message' => [
                    'content' => 'Tugasnya sudah masuk Operations Center dan menunggu approval.',
                ],
            ]],
        ];
        $this->aiResponses = [$toolResponse, $finalResponse];
        $bot = PlatformAiTelegramBot::where('credential_key', 'chief')->firstOrFail();
        app(PlatformTelegramConversation::class)->process($this->update($bot, '/ask ramli buatkan Yusuf strategi konten tujuh hari', 50, 771122, $group));

        $this->assertDatabaseHas('platform_ai_tasks', ['title' => 'Strategi konten tujuh hari', 'status' => 'proposed', 'requires_approval' => true]);
        $this->assertDatabaseMissing('platform_ai_tasks', ['title' => 'Strategi konten tujuh hari', 'status' => 'completed']);
    }

    public function test_agent_reply_is_cleaned_of_speaker_labels_markdown_and_split_into_bubbles(): void
    {
        [, , $group] = $this->linkedOffice();
        config(['services.yoka_ai.key' => 'test-key', 'services.yoka_ai.url' => 'https://ai.test/v1']);
        $this->aiResponses = [['choices' => [['message' => ['content' => "[Yusuf]: **oke bos** ini idenya\n\nyang pertama demo kasir 30 detik"]]]]];
        $bot = PlatformAiTelegramBot::where('credential_key', 'marketing')->firstOrFail();
        app(PlatformTelegramConversation::class)->process($this->update($bot, 'yusuf kasih ide konten', 60, 771122, $group));

        $sent = PlatformAiTelegramMessage::where('direction', 'outbound')->orderBy('id')->pluck('content')->all();
        $this->assertSame(['oke bos ini idenya', 'yang pertama demo kasir 30 detik'], $sent);
    }

    public function test_history_never_labels_own_messages_so_model_does_not_copy_label_format(): void
    {
        [$admin, , $group] = $this->linkedOffice();
        $yusuf = PlatformAiAgent::where('key', 'marketing')->firstOrFail();
        $luna = PlatformAiAgent::where('key', 'finance')->firstOrFail();
        PlatformAiTelegramMessage::create(['platform_ai_telegram_group_id' => $group->id, 'platform_admin_id' => $admin->id, 'direction' => 'inbound', 'channel' => 'telegram_group', 'content' => 'gimana konten', 'delivery_status' => 'received']);
        PlatformAiTelegramMessage::create(['platform_ai_telegram_group_id' => $group->id, 'platform_ai_agent_id' => $yusuf->id, 'direction' => 'outbound', 'channel' => 'telegram_group', 'content' => 'aman bos', 'delivery_status' => 'delivered']);
        PlatformAiTelegramMessage::create(['platform_ai_telegram_group_id' => $group->id, 'platform_ai_agent_id' => $luna->id, 'direction' => 'outbound', 'channel' => 'telegram_group', 'content' => 'budget aman', 'delivery_status' => 'delivered']);

        $history = app(\App\Services\PlatformTelegramOffice::class)->history($group, $yusuf, null);

        $this->assertSame([
            ['role' => 'user', 'content' => 'Bos: gimana konten'],
            ['role' => 'assistant', 'content' => 'aman bos'],
            ['role' => 'user', 'content' => 'Luna: budget aman'],
        ], $history);
    }

    public function test_office_chatter_respects_daily_limit_idle_time_and_cannot_create_tasks(): void
    {
        $this->travelTo(now('Asia/Jakarta')->setTime(14, 0));
        [, , $group] = $this->linkedOffice();
        $group->update(['notification_settings' => ['timezone' => 'Asia/Jakarta', 'quiet_start' => '22:00', 'quiet_end' => '07:00']]);
        config(['services.yoka_ai.key' => 'test-key', 'services.yoka_ai.url' => 'https://ai.test/v1']);
        $office = app(\App\Services\PlatformTelegramOffice::class);

        $this->aiResponses = [['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 't1', 'type' => 'function', 'function' => ['name' => 'create_task', 'arguments' => json_encode(['title' => 'Tugas spontan', 'summary' => 'x'])]]]]]]]];
        $this->assertTrue($office->runChatter($group, 1));
        $this->assertDatabaseMissing('platform_ai_tasks', ['title' => 'Tugas spontan']);
        $this->assertGreaterThan(0, PlatformAiTelegramMessage::where('direction', 'outbound')->count());

        // Grup baru saja ramai: tidak boleh menyela.
        $this->assertFalse($office->runChatter($group, 1));

        // Peluang gagal: tidak mulai.
        $this->travel(2)->hours();
        $this->assertFalse($office->runChatter($group, 99));

        // Jam tenang: diam.
        $this->travelTo(now('Asia/Jakarta')->setTime(23, 30));
        $this->assertFalse($office->runChatter($group, 1));
    }

    public function test_owner_can_ask_team_to_chat_among_themselves(): void
    {
        [, , $group] = $this->linkedOffice();
        $router = app(PlatformTelegramRouter::class);
        $this->assertSame('chatter', $router->route('/ngobrol fitur trial', $group)['kind']);
        $this->assertSame('fitur trial', $router->route('/ngobrol fitur trial', $group)['topic']);
        $this->assertSame('chatter', $router->route('kalian ngobrol dong soal pengembangan sistem', $group)['kind']);
        $this->assertSame('message', $router->route('yusuf gimana kontennya', $group)['kind']);
    }

    public function test_short_call_names_route_to_the_right_agent(): void
    {
        [, , $group] = $this->linkedOffice();
        $router = app(PlatformTelegramRouter::class);
        $this->assertSame(['marketing'], $router->route('coba suf jawab', $group)['agents']);
        $this->assertSame(['cs'], $router->route('dit ada komplain?', $group)['agents']);
        $this->assertNotSame(['accounting'], $router->route('nanti aja', $group)['agents']);
    }
}
