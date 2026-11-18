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
        // Markdown yang tampil mentah di Telegram.
        $text = preg_replace('/\*\*(.+?)\*\*/su', '$1', $text);
        $text = preg_replace('/__(.+?)__/su', '$1', $text);
        $text = preg_replace('/^#{1,6}\s+/mu', '', $text);
        $text = preg_replace('/^\s*[\*\-]\s+/mu', '- ', $text);

        // Label pembicara di awal pesan atau di awal baris: "[Ramli]: ", "Ramli:" (hanya nama tim).
        $names = 'ramli|yusuf|yono(?:\s+bakri)?|luna|nanda|adit|yosef|rekan tim';
        $text = preg_replace('/^[ \t]*\[(?:'.$names.')\][ \t]*:?[ \t]*/imu', '', $text);
        $text = preg_replace('/^[ \t]*(?:'.$names.')[ \t]*:[ \t]+/imu', '', $text);
        // Penanda internal "SKIP" tidak boleh ikut terkirim walau model menempelkannya di ujung kalimat.
        $text = preg_replace('/\s*\bSKIP\b[\s.!]*$/u', '', $text);

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
        // Model kadang mengulang isi yang sama dengan kata lain di bubble berikutnya: buang.
        $merged = self::withoutRepeats($merged);
        if (count($merged) > self::MAX_BUBBLES) {
            $head = array_slice($merged, 0, self::MAX_BUBBLES - 1);
            $head[] = implode("\n\n", array_slice($merged, self::MAX_BUBBLES - 1));
            $merged = $head;
        }

        return array_map(fn ($p) => Str::limit($p, 3500, '…'), $merged);
    }

    /**
     * Batas keras untuk obrolan (bukan hasil kerja): maksimal $maxBubbles bubble dan $maxChars
     * karakter total, dipotong di batas kalimat. Ditegakkan di kode karena prompt saja terbukti
     * tidak cukup membuat balasan pendek.
     */
    public static function chatLimit(string $text, int $maxBubbles = 2, int $maxChars = 320): string
    {
        $out = [];
        $used = 0;
        foreach (array_slice(self::bubbles($text), 0, $maxBubbles) as $bubble) {
            $room = $maxChars - $used;
            if ($room < 40) {
                break;
            }
            if (mb_strlen($bubble) > $room) {
                $sentences = preg_split('/(?<=[.!?])\s+/u', $bubble);
                $cut = '';
                foreach ($sentences as $sentence) {
                    if (mb_strlen($cut.' '.$sentence) > $room) {
                        break;
                    }
                    $cut = trim($cut.' '.$sentence);
                }
                $bubble = $cut !== '' ? $cut : Str::limit($bubble, $room - 1, '…');
            }
            $out[] = $bubble;
            $used += mb_strlen($bubble);
        }

        return implode("\n\n", $out);
    }

    /** @param array<int,string> $parts */
    private static function withoutRepeats(array $parts): array
    {
        $kept = [];
        foreach ($parts as $part) {
            $a = self::signature($part);
            $repeat = false;
            foreach ($kept as $previous) {
                $b = self::signature($previous);
                $shared = count(array_intersect($a, $b));
                $base = max(1, min(count($a), count($b)));
                if ($shared / $base >= 0.6) {
                    $repeat = true;
                    break;
                }
            }
            if (! $repeat) {
                $kept[] = $part;
            }
        }

        return $kept;
    }

    /** Kata bermakna (≥4 huruf, tanpa kata umum) untuk membandingkan isi dua bubble. */
    private static function signature(string $text): array
    {
        $stop = ['yang', 'buat', 'bisa', 'juga', 'jadi', 'kita', 'kamu', 'sama', 'udah', 'sudah', 'belum', 'masih', 'aja', 'nih', 'sih', 'dari', 'untuk', 'dengan', 'kalau', 'tapi', 'atau', 'hari', 'bos,'];
        preg_match_all('/[\p{L}\p{N}]{4,}/u', mb_strtolower($text), $m);

        return array_values(array_unique(array_diff($m[0], $stop)));
    }
}
