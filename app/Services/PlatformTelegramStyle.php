<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Membuat keluaran agen terasa seperti chat orang, bukan template:
 * buang label "[Nama]:" yang ikut tertiru dari riwayat, buang Markdown yang tidak dirender
 * Telegram (**tebal**, # judul), lalu pecah jadi beberapa bubble pendek seperti orang mengetik.
 */
class PlatformTelegramStyle
{
    public const MAX_BUBBLES = 4;

    public static function clean(string $text): string
    {
        $text = str_replace("\r\n", "\n", trim($text));
        // Label pembicara di awal pesan atau di awal baris: "[Ramli]: ", "Ramli:" (hanya nama tim).
        $names = 'ramli|yusuf|yono(?:\s+bakri)?|luna|nanda|adit|yosef|rekan tim';
        $text = preg_replace('/^[ \t]*\[(?:'.$names.')\][ \t]*:?[ \t]*/imu', '', $text);
        $text = preg_replace('/^[ \t]*(?:'.$names.')[ \t]*:[ \t]+/imu', '', $text);
        // Markdown yang tampil mentah di Telegram.
        $text = preg_replace('/\*\*(.+?)\*\*/su', '$1', $text);
        $text = preg_replace('/__(.+?)__/su', '$1', $text);
        $text = preg_replace('/^#{1,6}\s+/mu', '', $text);
        $text = preg_replace('/^\s*[\*\-]\s+/mu', '- ', $text);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** @return array<int,string> */
    public static function bubbles(string $text): array
    {
        $text = self::clean($text);
        if ($text === '') {
            return [];
        }
        $parts = array_values(array_filter(array_map('trim', preg_split("/\n{2,}/", $text)), fn ($p) => $p !== ''));
        // Daftar bernomor atau poin tetap satu bubble: memecahnya justru terasa seperti spam.
        $merged = [];
        foreach ($parts as $part) {
            $isList = (bool) preg_match('/^(\d+[\.\)]|- )/u', $part);
            $last = count($merged) - 1;
            if ($last >= 0 && $isList && preg_match('/(^|\n)(\d+[\.\)]|- )/u', $merged[$last])) {
                $merged[$last] .= "\n".$part;
            } elseif ($last >= 0 && $isList && Str::endsWith($merged[$last], ':')) {
                $merged[$last] .= "\n".$part;
            } else {
                $merged[] = $part;
            }
        }
        if (count($merged) > self::MAX_BUBBLES) {
            $head = array_slice($merged, 0, self::MAX_BUBBLES - 1);
            $head[] = implode("\n\n", array_slice($merged, self::MAX_BUBBLES - 1));
            $merged = $head;
        }

        return array_map(fn ($p) => Str::limit($p, 3500, '…'), $merged);
    }
}
