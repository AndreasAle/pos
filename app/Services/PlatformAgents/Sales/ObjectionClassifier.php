<?php

namespace App\Services\PlatformAgents\Sales;

/**
 * Taksonomi keberatan deterministik. Beberapa keberatan boleh muncul bersamaan.
 * Bukti = potongan kalimat asli prospek (tidak pernah dikarang).
 */
final class ObjectionClassifier
{
    private const RULES = [
        'REQUEST_STOP' => ['/\b(stop|berhenti|jangan\s+(hubungi|chat|kirim|wa)|unsubscribe|jangan\s+ganggu|hapus\s+nomor)\b/iu', 'Terima dengan sopan dalam satu pesan, jangan menawarkan lagi.', true],
        'REQUEST_HUMAN' => ['/\b(bicara|ngobrol|telp|telepon|ketemu|meeting|bertemu|minta)\b.{0,25}\b(orang|manusia|admin|sales|tim|owner|langsung)\b|\b(bisa|mau) (ketemu|meeting|zoom|video call)\b/iu', 'Bilang kamu tanyakan ke tim; jangan janji waktu.', true],
        'PRICE_TOO_HIGH' => ['/\b(mahal|kemahalan|budget|bujet|dana terbatas|nggak kuat bayar|ga kuat bayar|cuma (ada )?\d+|kurang murah|lebih murah)\b/iu', 'Pahami dulu yang harus dicakup budget; jelaskan nilai paket resmi; jangan menurunkan harga.', false],
        'ALREADY_USING_COMPETITOR' => ['/\b(udah|sudah|lagi|masih) (pakai|pake|langganan)\b.{0,20}\b(aplikasi|moka|majoo|pawoon|olsera|esb|qasir|kasir pintar|ipos|youtap|loyverse|iseller)\b/iu', 'Tanya apa yang kurang dari yang sekarang; jangan menjelekkan pesaing.', false],
        'NO_CURRENT_NEED' => ['/\b(belum (butuh|perlu)|nggak (butuh|perlu)|gak (butuh|perlu)|ga (butuh|perlu)|tidak (butuh|perlu)|masih kecil|manual aja cukup|cukup pakai buku)\b/iu', 'Hormati; tawarkan info singkat dan biarkan dia kembali kapan saja.', false],
        'PREVIOUS_BAD_EXPERIENCE' => ['/\b(pernah|dulu)\b.{0,40}\b(hilang|rusak|error|eror|ditipu|kapok|kecewa|zonk|ribet|mahal)\b|\b(kapok|trauma|troma|trauma)\b|\b(pernah|dulu)\b.{0,60}\b(servis|service|support|bantuan|cs|admin)\b.{0,25}\b(gak ada|nggak ada|ga ada|tidak ada|jelek|lama|susah|lambat|lemot)\b/iu', 'Akui pengalamannya, tanya apa penyebabnya, jelaskan hanya proses Yoka yang terverifikasi, tanpa jaminan.', false],
        'LOW_TRUST' => ['/\b(penipu|tipu|scam|aman (gak|nggak|ga|tidak)|bisa dipercaya|beneran (gak|nggak)|data (saya|aku) (aman|dijual))\b/iu', 'Jawab jujur dan spesifik; jangan pakai klaim "pasti aman"; tawarkan coba gratis untuk menilai sendiri.', false],
        'FEATURE_UNAVAILABLE' => ['/\b(ada (fitur|fasilitas)|bisa (konek|sambung|integrasi)|support)\b.{0,40}\b(akuntansi|jurnal|pajak|e-?faktur|payroll|gaji|absensi|marketplace|shopee|tokopedia|tiktok shop|accurate|jurnal\.id)\b/iu', 'Cek FAKTA PRODUK; bila tidak ada, bilang jujur tidak ada; jangan menjanjikan.', false],
        'TECHNICAL_CONCERN' => ['/\b(internet|offline|sinyal|printer|hp (jadul|kentang)|tablet|android|iphone|ios|perangkat|device)\b/iu', 'Jawab teknis dari FAKTA PRODUK; sarankan dicoba saat trial.', false],
        'DATA_MIGRATION_CONCERN' => ['/\b(pindah data|import|migrasi|data lama|menu (banyak|ratusan)|input ulang)\b/iu', 'Jelaskan cara memasukkan data sesuai fitur yang ada; jangan janji bantuan migrasi.', false],
        'PAYMENT_CONCERN' => ['/\b(bayar(nya)? (pakai|lewat|via)|transfer|qris|kartu kredit|cicil|tagihan|auto ?debet|potong otomatis)\b/iu', 'Jelaskan alur bayar resmi lewat Midtrans; jangan proses pembayaran di chat.', false],
        'TRIAL_UNCERTAINTY' => ['/\b(trial(nya)?|coba gratis)\b.{0,30}\b(bayar|kartu|ribet|habis|setelah|kepotong|otomatis)\b/iu', 'Jelaskan trial 7 hari tanpa kartu kredit sesuai fakta.', false],
        'NEED_INTERNAL_APPROVAL' => ['/\b(diskusi|tanya|izin|rundingan)\s+(dulu\s+)?(ke|sama|dengan)\s+(bos|owner|atasan|partner|suami|istri|keluarga)\b/iu', 'Tawarkan ringkasan singkat yang bisa diteruskan ke pengambil keputusan.', false],
        'NOT_DECISION_MAKER' => ['/\b(bukan (saya|aku) yang (putus|tentu|pegang)|saya (cuma|hanya) (karyawan|staf|kasir))\b/iu', 'Tanyakan dengan sopan siapa yang memutuskan; jangan memaksa.', false],
        'GUARANTEE_REQUEST' => ['/\b(jamin|garansi|dijamin|pasti (naik|untung|laris|rame))\b/iu', 'Jangan menjamin hasil; jelaskan yang bisa dinilai sendiri saat trial; bila minta jaminan tertulis, teruskan ke tim.', true],
        // Hanya bila menanyakan angka/data INTERNAL Yoka atau toko lain. "laporan margin" untuk usahanya sendiri BUKAN probe.
        'CONFIDENTIAL_PROBE' => ['/\b((modal|margin|untung|laba|biaya|harga pokok|harga beli|omzet)\s+(kalian|yoka|perusahaan|aplikasinya|kamu)|biaya server|data (toko|pelanggan|klien|merchant) (lain|kalian)|siapa (saja )?pelanggan (kalian|yoka|lain)|daftar pelanggan (kalian|yoka))\b/iu', 'Tolak dengan sopan: info internal dan data toko lain tidak dibagikan.', false],
    ];

    /** @return array<int, array{type:string, evidence:string, strategy:string, escalate:bool}> */
    public function classify(string $text): array
    {
        $out = [];
        foreach (self::RULES as $type => [$re, $strategy, $escalate]) {
            if (preg_match($re, $text, $m)) {
                $out[] = ['type' => $type, 'evidence' => mb_substr(trim($m[0]), 0, 120), 'strategy' => $strategy, 'escalate' => $escalate];
            }
        }

        return $out;
    }

    /** @return array<int,string> */
    public static function types(array $objections): array
    {
        return array_values(array_unique(array_column($objections, 'type')));
    }
}
