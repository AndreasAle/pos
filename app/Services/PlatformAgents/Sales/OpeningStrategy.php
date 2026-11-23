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
        "{hi}, izin kenalan ya. Saya Adit dari Yoka POS.\n\nKami punya aplikasi kasir untuk {aud}. Mau tanya sedikit, {q}",
        "{hi}. Saya Adit dari Yoka POS, kami bikin aplikasi kasir untuk {aud}.\n\nBoleh tanya sebentar, {q}",
        "{hi}, maaf ganggu waktunya sebentar. Saya Adit dari Yoka POS, aplikasi kasir untuk {aud}.\n\nIzin tanya, {q}",
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
        $fill = fn (string $aud, string $q) => strtr($tpl, ['{hi}' => $hi, '{aud}' => $aud, '{q}' => $q]);
        $engaged = $p->phone_normalized && PlatformAiWhatsAppMessage::where('phone', $p->phone_normalized)->where('direction', 'inbound')->exists();
        $segment = IndustryPlaybook::for((string) $p->business_type, (string) $p->business_name, (string) $p->notes);

        if ($engaged) {
            return ['category' => 'PREVIOUSLY_ENGAGED', 'message' => "{$hi}. Ini Adit dari Yoka POS, lanjut dari obrolan kita sebelumnya. "
                .'Kalau masih ada yang ingin ditanyakan soal aplikasi kasirnya, boleh langsung balas di sini ya.'];
        }
        if ($segment) {
            return ['category' => $segment['key'] === 'pecel_lele' ? 'VERIFIED_PECEL_LELE' : 'VERIFIED_'.strtoupper($segment['key']),
                'message' => $fill($segment['audience'], $segment['opening_question'])];
        }

        return ['category' => 'UNKNOWN_BUSINESS', 'message' => $fill('usaha kuliner dan toko', 'penjualan sehari-hari biasanya masih dicatat manual atau sudah pakai aplikasi?')];
    }

    public function followup(PlatformAiProspect $p): string
    {
        return self::greeting().' '.self::salutation($p).'. Adit dari Yoka POS lagi, cuma mau memastikan pesan kemarin sampai. '
            .'Kalau sekarang belum perlu aplikasi kasir, tidak apa-apa, saya tidak akan menghubungi lagi. Kalau ingin lihat dulu, bisa coba gratis di yokapos.com/register.';
    }
}
