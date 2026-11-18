<?php

namespace App\Services\PlatformAgents;

/**
 * Lapisan penalaran bisnis (Workforce 3.0): cek domain yang disuntikkan sebagai fakta HANYA bila
 * topiknya relevan, plus validasi istilah yang deterministik. Bukan checklist untuk semua jawaban.
 */
final class BusinessReasoning
{
    public const ADS = 'ads_readiness';

    public const FUNNEL = 'funnel_terms';

    public const FINANCE = 'finance_terms';

    /** @return array<int,string> */
    public static function domains(string $lower): array
    {
        $d = [];
        if (preg_match('/\b(iklan|ads|campaign|budget iklan|boost|meta ads|tiktok ads|cac)\b/u', $lower)) {
            $d[] = self::ADS;
        }
        if (preg_match('/\b(churn|trial|aktivasi|konversi|retensi|berhenti langganan|cancel|pelanggan (hilang|kabur))\b/u', $lower)) {
            $d[] = self::FUNNEL;
        }
        if (preg_match('/\b(profit|laba|untung|omzet|pendapatan|revenue|mrr|cash|kas|runway|margin)\b/u', $lower)) {
            $d[] = self::FINANCE;
        }

        return $d;
    }

    /** @param array<int,string> $domains */
    public static function facts(array $domains): array
    {
        $map = [
            self::ADS => 'CEK KESIAPAN IKLAN sebelum menyarankan tambah budget: apakah pendaftaran lancar, apakah trial benar-benar aktif dipakai, apakah ada masalah onboarding terbuka, apakah konversi trial ke bayar bisa diukur, berapa budget yang disetujui. Uji kecil tetap boleh asal hipotesis, batas rugi, dan apa yang mau dipelajari ditulis jelas. Jangan anggap belanja iklan otomatis perlu.',
            self::FUNNEL => 'ISTILAH FUNNEL YOKA (jangan dicampur): pendaftaran -> aktivasi trial -> pemakaian trial -> trial kedaluwarsa -> konversi bayar -> retensi pelanggan berbayar -> pembatalan langganan. "Churn" HANYA untuk pelanggan BERBAYAR yang berhenti. Pendaftar sedikit atau trial yang tidak aktif/kedaluwarsa tanpa bayar BUKAN churn; sebut "aktivasi rendah" atau "trial tidak konversi".',
            self::FINANCE => 'ISTILAH KEUANGAN: pendapatan bukan laba; langganan berbayar bukan pengguna aktif; CAC estimasi bukan CAC teramati; saldo kas bukan pendapatan yang diakui; pendaftar trial bukan pelanggan bayar. Sebut mana data tool dan mana asumsi.',
        ];

        return array_values(array_intersect_key($map, array_flip($domains)));
    }

    /**
     * Kalimat yang menyebut churn padahal konteksnya trial/pendaftaran tanpa bukti pelanggan berbayar
     * yang berhenti. Dikembalikan sebagai nama pelanggaran untuk ResponseGuard.
     */
    public static function terminologyViolation(string $sentence): ?string
    {
        $s = mb_strtolower($sentence);
        if (preg_match('/\bchurn\b/u', $s)
            && preg_match('/\b(trial|pendaftar|daftar|registrasi|aktivasi|belum bayar|gratis)\b/u', $s)
            && ! preg_match('/\b(bukan churn|belum churn|tidak churn|bukan termasuk churn|berbayar|langganan berbayar)\b/u', $s)) {
            return 'terminology_trial_as_churn';
        }

        return null;
    }
}
