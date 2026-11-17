<?php

namespace App\Services;

use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramGroup;
use App\Models\PlatformAiTelegramMessage;
use Illuminate\Support\Facades\Http;

class PlatformTelegramGateway
{
    public function __construct(private PlatformTelegramBotRegistry $registry) {}

    public function send(PlatformAiTelegramBot $bot, PlatformAiTelegramGroup $group, string $text, ?string $interactionId = null, ?int $threadId = null, array $links = []): PlatformAiTelegramMessage
    {
        $message = PlatformAiTelegramMessage::create([
            'platform_ai_telegram_group_id' => $group->id, 'platform_ai_telegram_bot_id' => $bot->id,
            'platform_ai_agent_id' => $bot->platform_ai_agent_id, 'interaction_id' => $interactionId,
            'platform_ai_task_id' => $links['task_id'] ?? null, 'platform_ai_discussion_id' => $links['discussion_id'] ?? null,
            'direction' => 'outbound', 'channel' => $threadId ? 'telegram_topic' : 'telegram_group',
            'thread_id' => $threadId, 'content' => mb_substr($text, 0, 4000), 'delivery_status' => 'pending',
        ]);
        $token = $this->registry->token($bot);
        if (! $token || ! $group->enabled) {
            $message->update(['delivery_status' => 'failed', 'metadata' => ['error' => 'Bot tidak aktif atau grup dijeda.']]);

            return $message;
        }
        try {
            $payload = ['chat_id' => $group->telegram_chat_id, 'text' => mb_substr($text, 0, 4000), 'disable_web_page_preview' => true];
            if ($threadId) {
                $payload['message_thread_id'] = $threadId;
            }
            $response = Http::timeout((int) config('services.telegram_office.timeout', 10))->retry([250, 750], throw: false)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
            $body = $response->json();
            if ($response->successful() && data_get($body, 'ok') === true) {
                $message->update(['delivery_status' => 'delivered', 'telegram_message_id' => data_get($body, 'result.message_id'), 'delivered_at' => now()]);
                $bot->update(['last_activity_at' => now(), 'last_error' => null]);
            } else {
                $message->update(['delivery_status' => 'failed', 'metadata' => ['http_status' => $response->status(), 'error' => 'Telegram menolak pengiriman.']]);
                $bot->update(['last_error' => 'Telegram send failed HTTP '.$response->status()]);
            }
        } catch (\Throwable $e) {
            $message->update(['delivery_status' => 'failed', 'metadata' => ['error' => class_basename($e)]]);
            $bot->update(['last_error' => class_basename($e)]);
        }

        return $message->fresh();
    }

    public function sendRaw(PlatformAiTelegramBot $bot, int|string $chatId, string $text): bool
    {
        $token = $this->registry->token($bot);
        if (! $token) {
            return false;
        }
        try {
            $response = Http::timeout((int) config('services.telegram_office.timeout', 10))->retry([250, 750], throw: false)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", ['chat_id' => $chatId, 'text' => mb_substr($text, 0, 4000)]);

            return $response->successful() && data_get($response->json(), 'ok') === true;
        } catch (\Throwable) {
            return false;
        }
    }
}
