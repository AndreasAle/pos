<?php

namespace App\Services\PlatformAgents\Sales;

use App\Models\PlatformAiProspect;
use App\Models\PlatformAiWhatsAppMessage;
use Illuminate\Support\Str;

/**
 * Pesan pembuka dan follow-up prospek: pendek, jujur sebagai asisten AI, satu topik, satu pertanyaan, TANPA link trial.
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
        if ($n === '' || ($b !== '' && (str_contains($b, $n) || str_contains($n, $b)))) {
            return null;
        }
        if (preg_match('/\b(toko|warung|kedai|cafe|kafe|resto|pos|store|shop|official|group|cv|pt|ud|coffee|kopi|bakso|pecel)\b/iu', $name) || str_word_count($name) > 3) {
            return null;
        }

        return Str::title($name);
    }

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
        $who = self::personName($p);
        $hi = self::greeting().($who ? " Pak/Bu {$who}" : ' Pak/Bu');
        $engaged = $p->phone_normalized && PlatformAiWhatsAppMessage::where('phone', $p->phone_normalized)->where('direction', 'inbound')->exists();
        $segment = IndustryPlaybook::for((string) $p->business_type, (string) $p->business_name, (string) $p->notes);

        if ($engaged) {
            return ['category' => 'PREVIOUSLY_ENGAGED', 'message' => "{$hi}. Ini Adit, asisten AI dari Yoka POS, lanjut dari obrolan kita sebelumnya. "
                .'Kalau masih ada yang ingin ditanyakan soal aplikasi kasirnya, boleh langsung balas di sini ya.'];
        }
        if ($segment) {
            return ['category' => $segment['key'] === 'pecel_lele' ? 'VERIFIED_PECEL_LELE' : 'VERIFIED_'.strtoupper($segment['key']),
                'message' => "{$hi}, izin kenalan ya. Saya Adit, asisten AI dari Yoka POS.\n\n"
                    ."Kami punya aplikasi kasir untuk {$segment['audience']}. Mau tanya sedikit, {$segment['opening_question']}"];
        }

        return ['category' => 'UNKNOWN_BUSINESS', 'message' => "{$hi}, izin kenalan ya. Saya Adit, asisten AI dari Yoka POS.\n\n"
            .'Kami punya aplikasi kasir untuk usaha kuliner dan toko. Mau tanya sedikit, penjualan sehari-hari biasanya masih dicatat manual atau sudah pakai aplikasi?'];
    }

    public function followup(PlatformAiProspect $p): string
    {
        $who = self::personName($p);

        return self::greeting().($who ? " Pak/Bu {$who}" : ' Pak/Bu').'. Adit dari Yoka POS lagi, cuma mau memastikan pesan kemarin sampai. '
            .'Kalau sekarang belum perlu aplikasi kasir, tidak apa-apa, saya tidak akan menghubungi lagi. Kalau ingin lihat dulu, bisa coba gratis di yokapos.com/register.';
    }
}
