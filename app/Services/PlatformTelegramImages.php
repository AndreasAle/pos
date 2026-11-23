<?php

namespace App\Services;

use App\Models\PlatformAiAgent;
use App\Models\PlatformAiAttachment;
use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramGroup;
use App\Models\PlatformAiTelegramIdentity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Foto dari grup Telegram Office -> lampiran gambar yang ikut dikirim ke model (agen bisa melihat).
 * Foto tanpa caption disimpan 10 menit dan ikut terbaca di pesan teks berikutnya dari pengirim yang sama.
 * $current berisi gambar untuk pesan yang sedang diproses (dipakai PlatformAiOperations::reply).
 */
class PlatformTelegramImages
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** @var array<int, PlatformAiAttachment> */
    public static array $current = [];

    public function __construct(private PlatformTelegramBotRegistry $registry) {}

    public static function hasImage(array $message): bool
    {
        return ! empty($message['photo']) || str_starts_with((string) data_get($message, 'document.mime_type'), 'image/');
    }

    /** @return array<int, PlatformAiAttachment> gambar yang harus ikut dibaca untuk pesan ini (kosong bila foto disimpan dulu). */
    public function collect(PlatformAiTelegramBot $bot, PlatformAiTelegramGroup $group, PlatformAiTelegramIdentity $identity, array $message, int $fromId, string $text): array
    {
        $key = "tg-photo:{$group->id}:{$fromId}";
        $new = self::hasImage($message) ? $this->download($bot, $identity, $message) : null;
        if ($text === '') {
            if ($new) {
                Cache::put($key, array_slice([...(array) Cache::get($key, []), $new->id], -3), now()->addMinutes(10));
            }

            return [];
        }
        $ids = (array) Cache::pull($key, []);
        $images = PlatformAiAttachment::whereIn('id', $ids)->get()->all();
        if ($new) {
            $images[] = $new;
        }

        return array_slice($images, -3);
    }

    private function download(PlatformAiTelegramBot $bot, PlatformAiTelegramIdentity $identity, array $message): ?PlatformAiAttachment
    {
        $token = $this->registry->token($bot);
        $fileId = ! empty($message['photo']) ? data_get(end($message['photo']), 'file_id') : data_get($message, 'document.file_id');
        if (! $token || ! $fileId) {
            return null;
        }
        try {
            $path = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getFile", ['file_id' => $fileId])->json('result.file_path');
            if (! $path) {
                return null;
            }
            $body = Http::timeout(20)->get("https://api.telegram.org/file/bot{$token}/{$path}")->throw()->body();
            if ($body === '' || strlen($body) > self::MAX_BYTES) {
                return null;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'jpg';
            $ext = in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true) ? $ext : 'jpg';
            $agentId = $bot->platform_ai_agent_id ?? PlatformAiAgent::where('key', 'chief')->value('id');
            $stored = 'platform-ai/'.$agentId.'/tg-'.Str::uuid().'.'.$ext;
            Storage::disk('local')->put($stored, $body);

            return PlatformAiAttachment::create([
                'platform_ai_agent_id' => $agentId, 'platform_admin_id' => $identity->platform_admin_id,
                'original_name' => 'telegram-foto.'.$ext, 'mime' => $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg'),
                'kind' => 'image', 'size' => strlen($body), 'path' => $stored, 'extracted_text' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram photo download failed', ['error' => class_basename($e)]);

            return null;
        }
    }
}
