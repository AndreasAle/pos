<?php

namespace App\Services\PlatformAgents;

use App\Services\PlatformTelegramRouter;
use Illuminate\Support\Str;

/**
 * Klasifikasi niat pesan Bos secara deterministik (tanpa model), dipakai untuk menegakkan
 * kebijakan aksi di lapisan tool, bukan hanya di prompt.
 *
 * Kebijakan inti (lihat docs/yoka-ai-workforce-action-policy.md):
 *  - Pekerjaan internal yang aman (analisis, hitungan, ide, draf, ringkasan) dikerjakan langsung
 *    di balasan; tool create_task tidak tersedia.
 *  - create_task hanya tersedia bila Bos eksplisit meminta tugas/catatan, atau permintaannya
 *    adalah aksi eksternal yang memang wajib lewat approval (kirim pesan, iklan, uang, harga, refund).
 */
class AgentIntent
{
    public const DIRECT_QUESTION = 'direct_question';

    public const ANALYSIS = 'analysis';

    public const CALCULATION = 'calculation';

    public const BRAINSTORM = 'brainstorm';

    public const DRAFT = 'draft';

    public const TASK_CREATION = 'task_creation';

    public const TASK_DELEGATION = 'task_delegation';

    public const APPROVAL = 'approval';

    public const TASK_STATUS = 'task_status';

    public const DISCUSSION = 'discussion';

    public const EXTERNAL_ACTION = 'external_action';

    public const REFERENCE = 'reference';

    /** Niat yang boleh memakai create_task. */
    public const TASK_ALLOWED = [self::TASK_CREATION, self::TASK_DELEGATION, self::EXTERNAL_ACTION];

    /**
     * @return array{intent:string, allows_task:bool, reference:bool, consult:?string, deliverable:bool}
     */
    public static function classify(string $text): array
    {
        $t = Str::lower(trim($text));
        $reference = self::isReference($t);
        $consult = self::consultTarget($text);

        $intent = match (true) {
            (bool) preg_match('/\b(jadi(k|kan|in)|catat|bikin(in|kan)?|buat(kan|in)?|masukin|masukkan|taruh|simpan)\b.{0,40}\b(tugas|task|todo|to-do|kanban|papan|dashboard|operations center)\b/u', $t)
                || (bool) preg_match('/^(catat|jadikan tugas|jadiin tugas)\b/u', $t) => self::TASK_CREATION,
            (bool) preg_match('/\b(tugaskan|delegasikan|oper(kan)?|assign)\b/u', $t) => self::TASK_DELEGATION,
            (bool) preg_match('/^(ya|y|oke|ok|setuju|approve|acc|gas|lanjut)\b.{0,30}$/u', $t) => self::APPROVAL,
            (bool) preg_match('/\b(status|progres|progress)\b.{0,30}\b(tugas|task|kerjaan|pekerjaan)\b|\btugas(nya)?\b.{0,20}\b(gimana|sampai mana|udah|sudah)\b/u', $t) => self::TASK_STATUS,
            (bool) preg_match('/\b(kirim(kan|in)?|blast|broadcast|wa(-|\s)?in|hubungi|telepon|chat)\b.{0,40}\b(pelanggan|customer|toko|klien|lead|prospek|merchant|user)\b/u', $t)
                || (bool) preg_match('/\b(pasang|jalankan|nyalakan|aktifkan|naikkan|turunkan|ubah|ganti)\b.{0,30}\b(iklan|ads|campaign|budget iklan|harga|tarif)\b/u', $t)
                // Uang keluar nyata, bukan sekadar menyebut "bayar" di pertanyaan hitungan.
                || (bool) preg_match('/\b(refund(kan|in)?|transfer(kan|in)?|bayarkan|bayarin|top ?up(kan|in)?|kembalikan dana)\b/u', $t) => self::EXTERNAL_ACTION,
            $reference => self::REFERENCE,
            $consult !== null => self::DISCUSSION,
            (bool) preg_match('/\b(hitung|simulasi|proyeksi|estimasi|berapa|kalkulasi|margin|payback|ltv|cac|harga paket|biaya paket)\b/u', $t) => self::CALCULATION,
            (bool) preg_match('/\b(draf|draft|tulis(kan|in)?|script|naskah|caption|template pesan|pesan pembuka)\b/u', $t) => self::DRAFT,
            (bool) preg_match('/\b(ide|hook|brainstorm|usulan|saran|konsep)\b/u', $t) => self::BRAINSTORM,
            (bool) preg_match('/\b(analisis|analisa|rangkum|ringkas|evaluasi|bandingkan|review|cek|kondisi|rekap)\b/u', $t) => self::ANALYSIS,
            default => self::DIRECT_QUESTION,
        };

        return [
            'intent' => $intent,
            'allows_task' => in_array($intent, self::TASK_ALLOWED, true),
            'reference' => $reference,
            'consult' => $consult,
            // Permintaan yang hasilnya berupa barang jadi: jangan diringkas jadi obrolan pendek.
            'deliverable' => in_array($intent, [self::CALCULATION, self::DRAFT, self::BRAINSTORM, self::ANALYSIS, self::REFERENCE], true),
        ];
    }

    /** "coba kirim di sini dulu", "mana hasilnya", "yang tadi tampilin dong". */
    public static function isReference(string $lower): bool
    {
        return (bool) preg_match('/\b(kirim|kirimin|share|tampil(kan|in)|tunjuk(kan|in)|kasih liat|paste|tempel)\b.{0,25}\b(di ?sini|ke ?sini|sini|grup|disini)\b/u', $lower)
            || (bool) preg_match('/\b(mana|lihat|liat|tampilin|kirim)\b.{0,15}\bhasil(nya)?\b/u', $lower)
            || (bool) preg_match('/\b(yang|yg) tadi\b/u', $lower);
    }

    /** "Yusuf, tanya Luna ..." -> 'finance'. Hanya bila ada pemanggil dan target berbeda. */
    public static function consultTarget(string $text): ?string
    {
        if (! preg_match('/\b(tanya(kan|in)?|tanyain|minta pendapat|cek ke|konfirmasi ke|diskusi(in)? (sama|dengan)|ngobrol (sama|dengan))\s+(?:ke\s+)?('.PlatformTelegramRouter::CALL_NAMES.')\b/iu', $text, $m)) {
            return null;
        }

        return PlatformTelegramRouter::agentKeyFor($m[count($m) - 1]);
    }
}
