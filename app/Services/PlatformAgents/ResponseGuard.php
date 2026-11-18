<?php

namespace App\Services\PlatformAgents;

use Illuminate\Support\Str;

/**
 * Validasi keluaran agen sebelum dipublikasikan ke Telegram.
 *
 * Bukan sekadar daftar kata terlarang: setiap kalimat yang berisi klaim aksi dicek terhadap bukti
 * eksekusi giliran itu (tool yang benar-benar dipanggil, tugas yang benar-benar tersimpan,
 * rekan yang benar-benar bicara). Klaim tanpa bukti dibuang.
 *
 * Evidence:
 *  - tools_used: nama tool yang sukses dipanggil di giliran ini
 *  - created_tasks: id tugas yang tersimpan di giliran ini
 *  - spoke_agents: nama rekan yang benar-benar punya pesan/kontribusi tersimpan di konteks ini
 *  - scheduled: true bila ada job/tugas terjadwal yang benar-benar tersimpan untuk agen ini
 */
class ResponseGuard
{
    private const FILLER = [
        '/\bada (yang|yg) (mau|perlu|bisa|ingin)\s+(aku\s+|ku)?(bantu|dibantu|cek|dicek|kerjain|dikerjain|dikerjakan|bahas|dibahas)\b/iu',
        '/\b(siap|senang) (bantu|membantu)\b/iu',
        '/\bada (yang|yg) bisa (aku|saya|kubantu|ku) ?bantu\b/iu',
        '/\bmasih (standby|stand by|siap)\b/iu',
        '/\b(kalau|kalo|jika|bila) (kamu |bos |lo |lu )?(mau|perlu|butuh),? (aku|saya|ku) (bisa|siap|bantu)\b/iu',
        '/\b(bilang|kabari) aja\b/iu',
        '/\btinggal (bilang|kabari)\b/iu',
    ];

    /** Janji tindakan di masa depan oleh agen sendiri. */
    private const FUTURE = '/\b(nanti|ntar|besok|lusa|minggu depan|segera)\b.{0,30}\b(aku|ku|saya)\b|\b(aku|saya|ku)\b.{0,12}\b(akan|bakal|mau)\b.{0,15}\b(cek|pantau|pantauin|monitor|kabari|kabarin|update|follow.?up|kirim|hubungi|siapkan|buat(kan)?|kerjakan|kerjain|lapor)\b|\b(aku|ku)\s*(pantau|pantauin|monitor)\b|\b(aku|ku|saya)\b.{0,10}\b(cek|lihat|liat)\b.{0,6}\b(dulu|nanti|lagi)\b/iu';

    /** Klaim aksi eksternal yang memang tidak bisa dilakukan agen. */
    private const EXTERNAL_DONE = '/\b(sudah|udah|udh|telah|barusan)\b.{0,12}\b(aku |ku|saya )?(kirim|kontak|hubungi|chat|wa|telepon|telpon|jalankan|pasang|aktifkan|nyalakan|transfer|bayar|refund|posting|publish|blast|broadcast|tayangkan)/iu';

    /** Klaim sudah membuat tugas. */
    private const TASK_DONE = '/\b(sudah|udah|udh|telah)\b.{0,15}\b(buat|bikin|taruh|masukin|masukkan|catat|ajukan|usulkan)(kan|in)?\b.{0,25}\b(tugas|task|papan|operations center)/iu';

    /** Klaim sudah berdiskusi dengan rekan tertentu. */
    private const CONSULTED = '/\b(sudah|udah|udh|tadi|barusan)\b.{0,10}\b(diskusi|ngobrol|tanya|nanya|konsultasi|koordinasi|cek)\b.{0,10}\b(sama|dengan|ke)\b\s+(ramli|yusuf|yono|luna|nanda|adit|yosef)\b/iu';

    /**
     * @param  array{tools_used?:array<int,string>, created_tasks?:array<int,int>, spoke_agents?:array<int,string>, scheduled?:bool}  $evidence
     * @return array{text:string, violations:array<int,string>}
     */
    public static function validate(string $text, array $evidence = []): array
    {
        $violations = [];
        $tools = (array) ($evidence['tools_used'] ?? []);
        $tasks = (array) ($evidence['created_tasks'] ?? []);
        $spoke = array_map(fn ($n) => Str::lower($n), (array) ($evidence['spoke_agents'] ?? []));
        $scheduled = (bool) ($evidence['scheduled'] ?? false);

        $lines = preg_split("/\n/", str_replace("\r\n", "\n", $text));
        $out = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                $out[] = '';

                continue;
            }
            $kept = [];
            foreach (preg_split('/(?<=[.!?])\s+/u', $line) as $sentence) {
                $reason = self::unsupported($sentence, $tools, $tasks, $spoke, $scheduled);
                if ($reason) {
                    $violations[] = $reason;

                    continue;
                }
                $kept[] = $sentence;
            }
            $joined = trim(implode(' ', $kept));
            // Baris yang seluruh isinya klaim tanpa bukti dibuang tanpa bekas.
            if ($joined !== '') {
                $out[] = $joined;
            }
        }
        $clean = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)));

        return ['text' => $clean, 'violations' => array_values(array_unique($violations))];
    }

    private static function unsupported(string $sentence, array $tools, array $tasks, array $spoke, bool $scheduled): ?string
    {
        foreach (self::FILLER as $pattern) {
            if (preg_match($pattern, $sentence)) {
                return 'filler';
            }
        }
        if (preg_match(self::TASK_DONE, $sentence)) {
            return $tasks === [] ? 'claimed_task_without_record' : null;
        }
        if (preg_match(self::EXTERNAL_DONE, $sentence)) {
            return 'claimed_external_action';
        }
        if (preg_match(self::CONSULTED, $sentence, $m)) {
            $name = Str::lower($m[4]);

            return in_array($name, $spoke, true) ? null : 'claimed_collaboration_without_record';
        }
        if (preg_match(self::FUTURE, $sentence)) {
            // Janji "setelah di-approve" sah bila tugasnya benar-benar tersimpan.
            if (($tasks !== [] || $scheduled) && preg_match('/\b(approve|apruv|disetujui|setuju)\b/iu', $sentence)) {
                return null;
            }

            return 'unsupported_future_promise';
        }

        return null;
    }

    /**
     * Gerbang kontribusi untuk pesan yang TIDAK diminta langsung oleh Bos (nimbrung, obrolan spontan).
     * Murah dan deterministik: buang yang cuma setuju/siap, atau hampir sama dengan pesan terbaru.
     *
     * @param  array<int,string>  $recent  isi pesan terbaru di percakapan ini
     */
    public static function lowValue(string $text, array $recent = []): ?string
    {
        $plain = Str::lower(trim(preg_replace('/\s+/u', ' ', $text)));
        if ($plain === '') {
            return 'empty';
        }
        // Peringatan risiko tidak pernah dibuang, sependek apa pun.
        if (preg_match('/\b(risiko|resiko|bahaya|hati-hati|awas|rugi|bocor|jangan|stop|warning|salah|keliru|minus|defisit|telat|overdue)\b/u', $plain)) {
            return null;
        }
        $words = count(preg_split('/\s+/u', $plain));
        $hasSubstance = (bool) preg_match('/\d|%|rp|\?/u', $plain);
        if ($words <= 14 && ! $hasSubstance && preg_match('/^(siap|setuju|sepakat|oke|ok|sip|mantap|betul|bener|benar|iya|yoi|noted|nah|wkwk|haha|nanti aku|ntar aku|aku cek)\b/u', $plain)) {
            return 'acknowledgement_only';
        }
        if ($words <= 8 && ! $hasSubstance && preg_match('/\b(kata|sama)\s+(ramli|yusuf|yono|luna|nanda|adit|yosef)\b/u', $plain)) {
            return 'echo_only';
        }
        foreach ($recent as $previous) {
            $prev = Str::lower(trim(preg_replace('/\s+/u', ' ', $previous)));
            if ($prev === '') {
                continue;
            }
            similar_text($plain, $prev, $percent);
            if ($percent >= 85) {
                return 'duplicate';
            }
        }

        return null;
    }
}
