<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pembungkus tipis untuk Telegram Bot API. Satu bot dipakai semua bisnis;
 * tiap bisnis menautkan chat Telegram-nya sendiri (lihat Business::telegram()).
 */
class TelegramService
{
    public static function enabled(): bool
    {
        return filled(config('services.telegram.token'));
    }

    public static function username(): ?string
    {
        return config('services.telegram.username') ?: null;
    }

    /** Tautan t.me yang langsung membuka bot dan mengirim /start KODE. */
    public static function deepLink(string $code): ?string
    {
        $u = self::username();
        return $u ? 'https://t.me/' . ltrim($u, '@') . '?start=' . $code : null;
    }

    public static function esc(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function rp(float|int $v): string
    {
        return 'Rp ' . number_format($v, 0, ',', '.');
    }

    /** Kirim pesan HTML. Mengembalikan true bila Telegram menerimanya. */
    public function send(string|int $chatId, string $html): bool
    {
        if (!self::enabled()) {
            return false;
        }

        try {
            $res = Http::timeout(6)->asForm()->post($this->url('sendMessage'), [
                'chat_id'                  => $chatId,
                'text'                     => mb_substr($html, 0, 4000),
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (!$res->successful()) {
                Log::warning('Telegram send failed', ['status' => $res->status(), 'body' => $res->body()]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('Telegram send error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function setWebhook(string $url, string $secret): array
    {
        return Http::timeout(10)->asForm()->post($this->url('setWebhook'), [
            'url'             => $url,
            'secret_token'    => $secret,
            'allowed_updates' => json_encode(['message']),
        ])->json() ?? [];
    }

    public function deleteWebhook(): array
    {
        return Http::timeout(10)->post($this->url('deleteWebhook'))->json() ?? [];
    }

    public function me(): array
    {
        return Http::timeout(10)->get($this->url('getMe'))->json() ?? [];
    }

    private function url(string $method): string
    {
        return 'https://api.telegram.org/bot' . config('services.telegram.token') . '/' . $method;
    }
}
