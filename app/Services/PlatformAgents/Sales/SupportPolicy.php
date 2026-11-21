<?php

namespace App\Services\PlatformAgents\Sales;

/** Fakta layanan bantuan dari config/yoka_support.php. Yang belum ditetapkan pemilik disebut jujur sebagai belum ada. */
final class SupportPolicy
{
    public static function hours(): ?string
    {
        return config('yoka_support.human_hours');
    }

    public static function promptLine(): string
    {
        $hours = self::hours();
        $target = config('yoka_support.response_target');
        $install = config('yoka_support.installation_help');

        return 'Layanan bantuan RESMI: '.implode('; ', (array) config('yoka_support.channels', [])).'. '
            .'Adit adalah asisten AI (otomatis), BUKAN tim support manusia. '
            .($hours ? "Jam layanan tim manusia: {$hours}. " : 'Jam layanan tim manusia BELUM ditetapkan: jangan sebut jam; bila ditanya, jawab jujur belum ada jadwal resmi dan tawarkan meneruskan ke tim. ')
            .($target ? "Target respon resmi: {$target}. " : 'Tidak ada target waktu respon resmi: jangan janjikan respon cepat/24 jam. ')
            .($install ? "Bantuan instalasi: {$install}. " : 'Bantuan instalasi/setup belum ditetapkan: jangan janjikan datang atau setup gratis. ')
            .'Jangan menjamin masalah teknis pasti selesai.';
    }

    /** Kalimat yang menyebut jam layanan resmi tidak dianggap klaim palsu. */
    public static function mentionsOfficialHours(string $sentence): bool
    {
        $h = self::hours();

        return $h !== null && $h !== '' && str_contains(mb_strtolower($sentence), mb_strtolower($h));
    }
}
