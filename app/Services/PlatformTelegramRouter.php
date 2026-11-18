<?php

namespace App\Services;

use App\Models\PlatformAiTelegramBot;
use App\Models\PlatformAiTelegramGroup;
use Illuminate\Support\Str;

class PlatformTelegramRouter
{
    /** Nama lengkap dan panggilan pendek tiap agen, dicocokkan sebagai kata utuh. */
    public const CALL_NAMES = 'ramli|ram|yusuf|suf|ucup|yono(?:\s+bakri)?|yon|luna|lun|nanda|nan|adit|dit|yosef|sef|ocep';

    public function route(string $text, PlatformAiTelegramGroup $group, ?PlatformAiTelegramBot $receivingBot = null, ?string $replyToAgent = null): array
    {
        $normalized = Str::lower(trim($text));
        if (preg_match('/^\/(help|tasks|status|team|briefing)(?:@\w+)?\b/u', $normalized, $m)) {
            return ['kind' => 'command', 'command' => $m[1], 'agents' => []];
        }
        // "semuanya absen", "absen dulu", "/absen": tiap agen menjawab hadir + kerjaannya.
        if (preg_match('/^\/absen\b|^absen\b|\b(semua(nya)?|tim|guys|kalian|everyone)\b.{0,20}\babsen\b/u', $normalized)) {
            return ['kind' => 'roll_call', 'agents' => []];
        }
        // "hapus/batalin semua tugas": pembersihan papan, selalu lewat konfirmasi.
        if (preg_match('/\b(hapus|hapusin|bersihin|bersihkan|batalin|batalkan|kosongin|kosongkan|buang)\b.{0,25}\b(tugas|task|kerjaan)/u', $normalized)) {
            return ['kind' => 'task_cleanup', 'agents' => []];
        }
        // Bos minta tim ngobrol sendiri: "/ngobrol", "kalian ngobrol dong", "diskusiin X dong".
        if (preg_match('/^\/ngobrol(?:@\w+)?\s*(.*)$/isu', trim($text), $m)
            || preg_match('/\b(kalian|tim|guys|semua)\b.*\b(ngobrol|ngobrolin|diskusi|diskusiin)\b(.*)$/isu', trim($text), $m2)) {
            $hint = trim($m[1] ?? ($m2[3] ?? ''));

            return ['kind' => 'chatter', 'topic' => $hint !== '' ? $hint : trim($text), 'agents' => []];
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
        // Termasuk panggilan pendek seperti di kantor: "suf", "ucup", "ram", "lun", "dit", "sef".
        if (preg_match('/\b('.self::CALL_NAMES.')\b/iu', $text, $m)) {
            return ['kind' => 'message', 'question' => $text, 'agents' => [$this->nameKey($m[1])]];
        }
        // Balasan (reply) ke pesan seorang agen ditujukan ke agen itu.
        if ($replyToAgent) {
            return ['kind' => 'message', 'question' => $text, 'agents' => [$replyToAgent]];
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
        return self::agentKeyFor($name);
    }

    public static function agentKeyFor(string $name): string
    {
        $name = Str::lower(preg_replace('/\s+.*/u', '', trim($name)));

        return [
            'ramli' => 'chief', 'ram' => 'chief',
            'yusuf' => 'marketing', 'suf' => 'marketing', 'ucup' => 'marketing',
            'yono' => 'ads', 'yon' => 'ads',
            'luna' => 'finance', 'lun' => 'finance',
            'nanda' => 'accounting', 'nan' => 'accounting',
            'adit' => 'cs', 'dit' => 'cs',
            'yosef' => 'sales', 'sef' => 'sales', 'ocep' => 'sales',
        ][$name] ?? 'chief';
    }
}
