<?php

namespace App\Services;

use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramGroup;
use Illuminate\Support\Str;

class PlatformTelegramRouter
{
    public function route(string $text, PlatformAiTelegramGroup $group, ?PlatformAiTelegramBot $receivingBot = null): array
    {
        $normalized = Str::lower(trim($text));
        if (preg_match('/^\/(help|tasks|status|team|briefing)(?:@\w+)?\b/u', $normalized, $m)) {
            return ['kind' => 'command', 'command' => $m[1], 'agents' => []];
        }
        if (preg_match('/^\/meeting(?:@\w+)?\s+(.+)/isu', trim($text), $m)) {
            return ['kind' => 'meeting', 'objective' => trim($m[1]), 'agents' => $this->relevant($m[1], 4)];
        }
        if (preg_match('/^\/ask(?:@\w+)?\s+(ramli|yusuf|yono(?:\s+bakri)?|luna|nanda|adit|yosef)\s+(.+)/isu', trim($text), $m)) {
            return ['kind' => 'message', 'question' => trim($m[2]), 'agents' => [$this->nameKey($m[1])]];
        }
        $bots = PlatformAiTelegramBot::whereNotNull('username')->get();
        foreach ($bots as $bot) {
            if (preg_match('/@'.preg_quote($bot->username, '/').'\b/iu', $text)) {
                return ['kind' => 'message', 'question' => trim(preg_replace('/@'.preg_quote($bot->username, '/').'\b/iu', '', $text)), 'agents' => [$bot->agent?->key ?? $bot->credential_key]];
            }
        }
        // Nama agen boleh dipanggil seperti percakapan biasa, tanpa /ask atau @username.
        if (preg_match('/\b(ramli|yusuf|yono(?:\s+bakri)?|luna|nanda|adit|yosef)\b/iu', $text, $m)) {
            return ['kind' => 'message', 'question' => $text, 'agents' => [$this->nameKey($m[1])]];
        }

        return ['kind' => 'message', 'question' => $text, 'agents' => $this->relevant($text, 3)];
    }

    public function relevant(string $text, int $limit = 3): array
    {
        $rules = [
            'finance' => ['biaya', 'budget', 'anggaran', 'uang', 'harga', 'margin', 'cac', 'ltv', 'runway', 'keuangan', 'cashflow', 'arus kas'],
            'ads' => ['iklan', 'ads', 'campaign', 'roas', 'cpm', 'budget iklan'],
            'marketing' => ['konten', 'marketing', 'promosi', 'video', 'instagram', 'tiktok', 'brand', 'branding'],
            'sales' => ['sales', 'jualan', 'prospek', 'lead', 'pipeline', 'closing', 'pelanggan berbayar'],
            'accounting' => ['akuntansi', 'rekonsiliasi', 'pembayaran', 'pencatatan', 'invoice'],
            'cs' => ['customer', 'pelanggan', 'user', 'komplain', 'keluhan', 'tiket', 'layanan', 'onboarding', 'trial habis'],
        ];
        $lower = Str::lower($text);
        $keys = [];
        foreach ($rules as $key => $terms) {
            if (collect($terms)->contains(fn ($term) => str_contains($lower, $term))) {
                $keys[] = $key;
            }
        }
        if ($keys === []) {
            $keys[] = 'chief';
        }
        if (count($keys) > 1 && ! in_array('chief', $keys, true)) {
            $keys[] = 'chief';
        }

        return array_slice(array_values(array_unique($keys)), 0, $limit);
    }

    private function nameKey(string $name): string
    {
        return match (true) {
            str_starts_with(Str::lower($name), 'yono') => 'ads', default => ['ramli' => 'chief', 'yusuf' => 'marketing', 'luna' => 'finance', 'nanda' => 'accounting', 'adit' => 'cs', 'yosef' => 'sales'][Str::lower($name)]
        };
    }
}
