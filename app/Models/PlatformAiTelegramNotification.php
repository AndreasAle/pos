<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class PlatformAiTelegramNotification extends Model
{
    protected $fillable = ['dedupe_key', 'type', 'platform_ai_telegram_group_id', 'platform_ai_task_id', 'task_version', 'telegram_chat_id', 'thread_id', 'event_fingerprint', 'status', 'platform_ai_telegram_message_id'];

    /**
     * Klaim atomik. Mengembalikan null bila notifikasi yang sama sudah pernah diklaim
     * (oleh proses ini atau worker lain), sehingga pemanggil tidak boleh mengirim.
     */
    public static function claim(string $type, PlatformAiTelegramGroup $group, ?int $threadId, string $subject, array $attributes = []): ?self
    {
        $key = implode(':', [$type, $group->id, $threadId ?? 0, $subject]);
        try {
            return self::create(array_merge([
                'dedupe_key' => mb_substr($key, 0, 191), 'type' => $type,
                'platform_ai_telegram_group_id' => $group->id, 'telegram_chat_id' => $group->telegram_chat_id,
                'thread_id' => $threadId, 'event_fingerprint' => hash('sha256', $key), 'status' => 'claimed',
            ], $attributes));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }
}
