<?php

namespace App\Services\PlatformAgents\Sales;

use App\Models\PlatformAiProspect;
use App\Models\PlatformAiWhatsAppMessage;
use Illuminate\Support\Str;

/**
 * Pesan pembuka dan follow-up prospek: pendek, memperkenalkan diri sebagai Adit dari Yoka POS, satu topik, satu pertanyaan, TANPA link trial.
 * Nama bisnis tidak pernah dipakai sebagai nama orang.
 */
final class OpeningStrategy
{
    /** Nama kontak yang aman dipakai menyapa, atau null bila tidak terverifikasi. */
    public static function personName(PlatformAiProspect $p): ?string
    {
        $name = trim((string) $p->contact_name);
        if ($name === '' || mb_strlen($name) > 30 || preg_match('/[\d@&\/]/u', $name)) {
            return null;
        }
        $norm = fn ($s) => preg_replace('/[^a-z]/', '', mb_strtolower((string) $s));
        $n = $norm($name);
        $b = $norm($p->business_name);
        // "Andopos" sebagai kontak untuk bisnis "andopos": itu nama usaha, bukan nama orang.
        // Tetapi "Budi" untuk "Pecel Lele Cak Budi" adalah nama orang (jauh lebih pendek dari nama usaha).
        if ($n === '' || ($b !== '' && ($n === $b || str_contains($n, $b) || (str_contains($b, $n) && strlen($n) >= 0.7 * strlen($b))))) {
            return null;
        }
        if (preg_match('/\b(toko|warung|kedai|cafe|kafe|resto|pos|store|shop|official|group|cv|pt|ud|coffee|kopi|bakso|pecel)\b/iu', $name) || str_word_count($name) > 3) {
            return null;
        }

        return Str::title($name);
    }

    /** Sapaan: honorifik dari nama usaha ("Cak Budi", "Bu Siti") bila cocok dengan nama kontak; lainnya "Kak Nama"; tanpa nama "Pak/Bu". */
    public static function salutation(PlatformAiProspect $p): string
    {
        $who = self::personName($p);
        if (! $who) {
            return 'Pak/Bu';
        }
        if (preg_match('/\b(cak|mas|mbak|mba|bu|ibu|pak|bapak|bang|uda|uni|teh|kang|koh|cik|ci|om|tante)\s+'.preg_quote($who, '/').'\b/iu', (string) $p->business_name, $m)) {
            return Str::ucfirst(mb_strtolower($m[1])).' '.$who;
        }

        return 'Kak '.$who;
    }

    /** Variasi kalimat pembuka supaya pesan ke warung-warung berbeda tidak identik (bukan broadcast). */
    private const OPENERS = [
        "{hi}, saya Adit dari Yoka POS.\n\n{value}\n\nKira-kira ini relevan untuk {biz}?",
        "{hi}, izin kenalan ya, saya Adit dari Yoka POS.\n\n{value}\n\nKira-kira cocok nggak untuk {biz}?",
        "{hi}, saya Adit dari Yoka POS.\n\n{value}\n\nBoleh tahu, ini relevan untuk {biz}?",
    ];

    public static function greeting(): string
    {
        $h = (int) now('Asia/Jakarta')->format('G');

        return match (true) {
            $h < 11 => 'Selamat pagi',
            $h < 15 => 'Selamat siang',
            $h < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    /** @return array{category:string, message:string} */
    public function opening(PlatformAiProspect $p): array
    {
        $hi = self::greeting().' '.self::salutation($p);
        $tpl = self::OPENERS[abs(crc32((string) ($p->phone_normalized ?: $p->business_name))) % count(self::OPENERS)];
        // Riset outreach (Gong, Backlinko, BuzzStream): pendek, konteks bidang usaha, ajakan berbasis minat (relevan atau tidak).
        $fill = fn (string $value, string $object) => strtr($tpl, ['{hi}' => $hi, '{value}' => $value, '{biz}' => trim((string) $p->business_name) !== '' ? trim((string) $p->business_name) : $object]);
        $engaged = $p->phone_normalized && PlatformAiWhatsAppMessage::where('phone', $p->phone_normalized)->where('direction', 'inbound')->exists();
        $segment = IndustryPlaybook::for((string) $p->business_type, (string) $p->business_name, (string) $p->notes);

        if ($engaged) {
            return ['category' => 'PREVIOUSLY_ENGAGED', 'message' => "{$hi}. Ini Adit dari Yoka POS, lanjut dari obrolan kita sebelumnya. "
                .'Kalau masih ada yang ingin ditanyakan soal aplikasi kasirnya, boleh langsung balas di sini ya.'];
        }
        if ($segment) {
            return ['category' => $segment['key'] === 'pecel_lele' ? 'VERIFIED_PECEL_LELE' : 'VERIFIED_'.strtoupper($segment['key']),
                'message' => $fill($segment['value'], $segment['object'])];
        }

        return ['category' => 'UNKNOWN_BUSINESS', 'message' => $fill('Kami bantu usaha kuliner dan toko supaya penjualan harian langsung tercatat, tanpa hitung dari nota.', 'usahanya')];
    }

    public function followup(PlatformAiProspect $p): string
    {
        return self::greeting().' '.self::salutation($p).'. Adit dari Yoka POS lagi, cuma mau memastikan pesan kemarin sampai. '
            .'Kalau sekarang belum perlu aplikasi kasir, tidak apa-apa, saya tidak akan menghubungi lagi. Kalau ingin lihat dulu, bisa coba gratis di yokapos.com/register.';
    }
}
