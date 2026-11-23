<?php

namespace App\Services\PlatformAgents\Sales;

/**
 * Playbook per segmen usaha. Hanya konteks: topik pembuka, topik discovery, dan pemetaan masalah -> fitur yang
 * benar-benar ada (lihat AgentBrain::productFacts). Segmen baru cukup ditambah di SEGMENTS.
 */
final class IndustryPlaybook
{
    private const SEGMENTS = [
        'pecel_lele' => [
            'label' => 'warung pecel lele',
            'match' => ['pecel', 'lele', 'penyetan', 'penyet', 'lalapan'],
            'opening_topic' => 'pencatatan pesanan makan di tempat dan bungkus waktu warung lagi ramai',
            'value' => 'Kami bantu warung pecel lele supaya pesanan makan di tempat dan bungkus langsung tercatat, jadi setoran harian tidak perlu dihitung dari nota.', 'object' => 'warungnya', 'opening_question' => 'kalau warung sedang ramai, biasanya pesanan masih dicatat pakai kertas atau sudah pakai aplikasi?', 'audience' => 'usaha kuliner',
            'discovery' => ['cara catat pesanan sekarang', 'makan di tempat vs bungkus', 'jam ramai', 'tutup kas dan rekap harian', 'stok lele/ayam', 'perlu printer struk atau tidak'],
        ],
        'warung_makan' => [
            'label' => 'warung makan',
            'match' => ['warung', 'nasi', 'bakso', 'mie ayam', 'geprek', 'ayam goreng', 'soto', 'sate', 'seblak', 'rumah makan', 'warteg'],
            'opening_topic' => 'pencatatan pesanan dan rekap penjualan harian',
            'value' => 'Kami bantu warung makan supaya pesanan langsung tercatat dan rekap penjualan harian jadi otomatis, tanpa hitung dari nota.', 'object' => 'warungnya', 'opening_question' => 'pesanan dan rekap penjualan harian biasanya masih dicatat manual atau sudah pakai aplikasi?', 'audience' => 'usaha kuliner',
            'discovery' => ['cara catat pesanan sekarang', 'jam ramai', 'rekap harian', 'stok bahan', 'jumlah karyawan'],
        ],
        'kafe' => [
            'label' => 'kafe',
            'match' => ['kafe', 'cafe', 'coffee', 'kopi', 'boba', 'tea', 'minuman', 'juice', 'jus'],
            'opening_topic' => 'pesanan saat jam ramai dan pemantauan stok bahan',
            'value' => 'Kami bantu kafe supaya pesanan saat ramai langsung tercatat dan stok bahan kepantau tanpa hitung manual.', 'object' => 'kafenya', 'opening_question' => 'pesanan dan stok bahannya biasanya masih dicatat manual atau sudah pakai aplikasi?', 'audience' => 'kafe dan usaha kuliner',
            'discovery' => ['cara catat pesanan', 'stok bahan', 'jumlah barista/kasir', 'laporan harian'],
        ],
        'resto' => [
            'label' => 'restoran',
            'match' => ['resto', 'restoran', 'restaurant', 'seafood', 'catering'],
            'opening_topic' => 'pesanan dari meja ke dapur dan laporan harian',
            'value' => 'Kami bantu restoran supaya pesanan dari meja langsung masuk ke dapur dan laporan harian jadi otomatis.', 'object' => 'restorannya', 'opening_question' => 'pesanan ke dapur dan laporan hariannya biasanya masih manual atau sudah pakai aplikasi?', 'audience' => 'usaha kuliner',
            'discovery' => ['alur pesanan ke dapur', 'nomor meja', 'laporan harian', 'jumlah outlet'],
        ],
        'retail' => [
            'label' => 'toko',
            'match' => ['toko', 'retail', 'minimarket', 'kelontong', 'grosir', 'fashion', 'butik', 'distro'],
            'opening_topic' => 'pencatatan stok dan penjualan harian',
            'value' => 'Kami bantu toko supaya stok dan penjualan harian langsung tercatat tanpa hitung manual.', 'object' => 'tokonya', 'opening_question' => 'stok dan penjualan hariannya biasanya masih dicatat manual atau sudah pakai aplikasi?', 'audience' => 'toko',
            'discovery' => ['cara catat stok', 'selisih stok', 'laporan harian'],
        ],
    ];

    /** Masalah -> fitur yang terverifikasi di productFacts. Tanpa klaim hasil. */
    public const VALUE_MAP = [
        'antrean kasir' => 'kasir/POS dengan login PIN di tablet, dan QR Meja supaya pelanggan bisa pesan sendiri dari meja',
        'laporan' => 'laporan penjualan, produk terlaris, shift, estimasi profit, dan laba rugi, bisa diekspor ke Excel/PDF',
        'margin' => 'laporan estimasi profit dan laba rugi; angka modalnya mengikuti harga pokok/resep yang diisi pemilik',
        'stok' => 'stok bahan baku, resep, dan laporan inventory; akurasinya bergantung pada resep dan stok awal yang diisi',
        'selisih uang' => 'shift kasir dan Audit Log aktivitas',
        'pesanan dapur' => 'layar dapur untuk role Dapur',
        'kertas' => 'transaksi tercatat digital di kasir, jadi rekap harian tidak perlu dijumlah dari nota',
    ];

    /** @return array{key:string,label:string,opening_topic:string,opening_question:string,discovery:array}|null */
    public static function for(string $businessType, string $businessName = '', string $notes = ''): ?array
    {
        $hay = ' '.mb_strtolower($businessType.' '.$businessName.' '.$notes).' ';
        foreach (self::SEGMENTS as $key => $s) {
            foreach ($s['match'] as $w) {
                if (str_contains($hay, $w)) {
                    return ['key' => $key] + $s;
                }
            }
        }

        return null;
    }

    public static function context(?array $segment, ?string $pain): string
    {
        $lines = [];
        if ($segment) {
            $lines[] = "- Segmen: {$segment['label']}. Topik yang biasanya relevan (pilih SATU bila cocok, bukan kuesioner): ".implode(', ', $segment['discovery']).'.';
        }
        foreach (self::VALUE_MAP as $problem => $capability) {
            if ($pain && str_contains(mb_strtolower($pain), explode(' ', $problem)[0])) {
                $lines[] = "- Masalah \"{$problem}\" -> fitur yang benar-benar ada: {$capability}. Jelaskan manfaatnya tanpa angka atau jaminan.";
            }
        }

        return implode("\n", $lines);
    }
}
