<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPlatformTelegramUpdate;
use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramGroup;
use App\Models\PlatformAiTelegramUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlatformTelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $botKey)
    {
        $bot = PlatformAiTelegramBot::where('public_key', $botKey)->where('status', 'active')->firstOrFail();
        $secret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        abort_unless($secret !== '' && hash_equals($bot->webhook_secret_hash, hash('sha256', $secret)), 403);
        $data = $request->validate([
            'update_id' => 'required|integer',
            'message' => 'required|array',
            'message.message_id' => 'required|integer',
            'message.message_thread_id' => 'nullable|integer',
            'message.date' => 'nullable|integer',
            'message.text' => 'nullable|string|max:4096',
            'message.chat.id' => 'required|integer',
            'message.chat.type' => 'required|in:private,group,supergroup,channel',
            'message.chat.title' => 'nullable|string|max:255',
            'message.from.id' => 'required|integer',
            'message.from.username' => 'nullable|string|max:255',
            'message.from.is_bot' => 'nullable|boolean',
            'message.reply_to_message' => 'nullable|array',
            'message.reply_to_message.message_id' => 'nullable|integer',
            'message.reply_to_message.from.is_bot' => 'nullable|boolean',
        ]);
        $chatId = (int) data_get($data, 'message.chat.id');
        $messageId = (int) data_get($data, 'message.message_id');
        $fingerprint = hash('sha256', "message|{$chatId}|{$messageId}");
        if (PlatformAiTelegramUpdate::where('event_fingerprint', $fingerprint)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }
        $safe = ['message' => ['message_id' => $messageId, 'message_thread_id' => data_get($data, 'message.message_thread_id'), 'text' => data_get($data, 'message.text'), 'date' => data_get($data, 'message.date'), 'chat' => ['id' => $chatId, 'type' => data_get($data, 'message.chat.type'), 'title' => data_get($data, 'message.chat.title')], 'from' => ['id' => data_get($data, 'message.from.id'), 'username' => data_get($data, 'message.from.username'), 'is_bot' => (bool) data_get($data, 'message.from.is_bot', false)]]];
        // Hanya ID pesan yang di-reply yang disimpan (bukan isinya): cukup untuk mencocokkan
        // dengan pesan agen yang sudah tercatat di platform_ai_telegram_messages.
        if ($replyId = data_get($data, 'message.reply_to_message.message_id')) {
            $safe['message']['reply_to_message'] = ['message_id' => (int) $replyId, 'from' => ['is_bot' => (bool) data_get($data, 'message.reply_to_message.from.is_bot', false)]];
        }
        $update = PlatformAiTelegramUpdate::create(['platform_ai_telegram_bot_id' => $bot->id, 'platform_ai_telegram_group_id' => PlatformAiTelegramGroup::where('telegram_chat_id', $chatId)->value('id'), 'telegram_update_id' => $data['update_id'], 'telegram_chat_id' => $chatId, 'telegram_message_id' => $messageId, 'interaction_id' => (string) Str::uuid(), 'event_fingerprint' => $fingerprint, 'status' => 'received', 'payload' => $safe]);
        ProcessPlatformTelegramUpdate::dispatchAfterResponse($update->id);

        return response()->json(['ok' => true], 202);
    }
}
