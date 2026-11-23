<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramGroup;
use App\Models\PlatformAiTelegramIdentity;
use App\Models\PlatformAiTelegramUpdate;
use App\Services\PlatformTelegramBotRegistry;
use App\Services\PlatformTelegramConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Semua agen bisa melihat gambar: lampiran dashboard dan foto di grup Telegram ikut terkirim ke model. */
class PlatformAiVisionTest extends TestCase
{
    use RefreshDatabase;

    private int $messageId = 100;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.yoka_ai.key' => 'test-key', 'services.yoka_ai.url' => 'https://ai.test/v1', 'queue.default' => 'sync', 'services.telegram_office.chime_in_chance' => 0]);
        foreach (['chief' => 'ramli', 'marketing' => 'yusuf', 'ads' => 'yono', 'finance' => 'luna', 'accounting' => 'nanda', 'cs' => 'adit', 'sales' => 'yosef'] as $key => $name) {
            config(["services.telegram_office.bots.{$key}" => ['token' => "{$name}:test-token", 'username' => "{$name}_yoka_bot", 'webhook_secret' => "secret-{$name}"]]);
        }
        app(PlatformTelegramBotRegistry::class)->sync();
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, 'ai.test')) {
                return Http::response(['choices' => [['message' => ['content' => 'desainnya rapi bos, logonya kebaca jelas']]]]);
            }
            if (str_contains($url, '/getFile')) {
                return Http::response(['ok' => true, 'result' => ['file_path' => 'photos/file_1.jpg']]);
            }
            if (str_contains($url, '/file/bot')) {
                return Http::response('JPEGDATA-SERAGAM');
            }

            return Http::response(['ok' => true, 'result' => ['message_id' => ++$this->messageId]]);
        });
    }

    private function office(): PlatformAiTelegramGroup
    {
        $admin = PlatformAdmin::create(['name' => 'Owner', 'email' => 'owner@yoka.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        PlatformAiTelegramIdentity::create(['platform_admin_id' => $admin->id, 'telegram_user_id' => 771122, 'status' => 'verified', 'verified_at' => now()]);

        return PlatformAiTelegramGroup::create(['platform_admin_id' => $admin->id, 'telegram_chat_id' => -100123456, 'title' => 'Yoka Office', 'enabled' => true, 'mode' => 'smart_discussion', 'notification_settings' => []]);
    }

    private function send(PlatformAiTelegramGroup $group, array $content): void
    {
        $id = ++$this->messageId;
        $message = $content + ['message_id' => $id, 'date' => 1_790_000_000 + $id, 'chat' => ['id' => $group->telegram_chat_id, 'type' => 'supergroup', 'title' => $group->title], 'from' => ['id' => 771122, 'username' => 'owner', 'is_bot' => false]];
        $update = PlatformAiTelegramUpdate::create([
            'platform_ai_telegram_bot_id' => PlatformAiTelegramBot::where('credential_key', 'chief')->value('id'), 'platform_ai_telegram_group_id' => $group->id,
            'telegram_update_id' => 5000 + $id, 'telegram_chat_id' => $group->telegram_chat_id, 'telegram_message_id' => $id,
            'interaction_id' => (string) Str::uuid(), 'event_fingerprint' => hash('sha256', "vision|{$id}"), 'status' => 'received', 'payload' => ['message' => $message],
        ]);
        app(PlatformTelegramConversation::class)->process($update);
    }

    private function imageSentToModel(): bool
    {
        return collect(Http::recorded())->contains(fn ($p) => str_contains($p[0]->url(), 'ai.test')
            && str_contains(json_encode($p[0]->data()), base64_encode('JPEGDATA-SERAGAM')));
    }

    public function test_telegram_photo_with_caption_is_seen_by_the_agent(): void
    {
        $group = $this->office();
        $this->send($group, ['caption' => 'Ramli, bagus gak desain seragam ini?', 'photo' => [['file_id' => 'kecil'], ['file_id' => 'besar']]]);

        $this->assertTrue($this->imageSentToModel(), 'Foto ikut dikirim ke model sebagai gambar.');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/getFile') && $r['file_id'] === 'besar');
    }

    public function test_photo_without_caption_is_read_with_the_next_text(): void
    {
        $group = $this->office();
        $this->send($group, ['photo' => [['file_id' => 'foto1']]]);
        $this->assertFalse(collect(Http::recorded())->contains(fn ($p) => str_contains($p[0]->url(), 'ai.test')), 'Foto saja belum dijawab.');

        $this->send($group, ['text' => 'bagus gak ramli']);
        $this->assertTrue($this->imageSentToModel(), 'Foto sebelumnya ikut terbaca di pesan berikutnya.');
    }

    public function test_dashboard_image_attachment_reaches_the_model_and_agents_know_they_can_see(): void
    {
        $admin = PlatformAdmin::create(['name' => 'Owner', 'email' => 'o2@yoka.test', 'password' => 'password-kuat', 'role' => 'super_admin', 'is_active' => true]);
        app(\App\Services\PlatformAiOperations::class)->ensureAgents();
        $yusuf = PlatformAiAgent::where('key', 'marketing')->firstOrFail();
        $png = UploadedFile::fake()->image('tiktok-analytics.png', 40, 40);

        $this->actingAs($admin, 'platform')->withSession(['platform_2fa_passed_at' => now()->timestamp])
            ->post(route('platform.ai-team.chat', $yusuf), ['message' => 'suf baca grafik ini', 'files' => [$png]], ['Accept' => 'application/json'])->assertOk();

        $reqs = collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'ai.test'))->map(fn ($p) => json_encode($p[0]->data()));
        $withImage = $reqs->first(fn ($j) => str_contains($j, 'data:image\/png;base64,') || str_contains($j, 'data:image/png;base64,'));
        $this->assertNotNull($withImage, 'Gambar dashboard ikut dikirim ke model.');
        $this->assertStringContainsString('KAMU BISA MELIHAT GAMBAR', $withImage);
    }
}
