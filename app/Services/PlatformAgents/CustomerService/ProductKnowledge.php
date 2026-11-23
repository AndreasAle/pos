<?php

namespace App\Services\PlatformAgents\CustomerService;

use App\Http\Controllers\GuideController;
use Illuminate\Support\Str;

/**
 * Domain 2: APA yang boleh dikatakan tentang Yoka. Sumbernya panduan resmi (yokapos.com/panduan,
 * GuideController::guides()), yang ditulis dari menu asli aplikasi. Tidak ada vector DB: pencocokan
 * kata kunci sederhana, maksimal dua panduan per pesan, supaya token tetap kecil.
 */
final class ProductKnowledge
{
    /** Sinonim bahasa pelanggan -> kata di panduan. */
    private const ALIASES = [
        'print' => 'printer', 'nge-print' => 'printer', 'ngeprint' => 'printer', 'bluetooth' => 'printer', 'rawbt' => 'printer', 'thermal' => 'printer', 'struk' => 'struk',
        'menu' => 'menu', 'produk' => 'menu', 'harga jual' => 'harga', 'kategori' => 'kategori',
        'shift' => 'shift', 'tutup kasir' => 'shift', 'buka kasir' => 'shift', 'setoran' => 'shift',
        'stok' => 'stok', 'bahan' => 'stok', 'resep' => 'resep',
        'laporan' => 'laporan', 'omzet' => 'laporan', 'penjualan' => 'laporan', 'profit' => 'laporan',
        'telegram' => 'telegram', 'notifikasi' => 'telegram',
        'langganan' => 'berlangganan', 'bayar' => 'berlangganan', 'paket' => 'berlangganan', 'trial' => 'trial', 'aktif' => 'berlangganan',
        'daftar' => 'mulai', 'mulai' => 'mulai', 'outlet' => 'outlet', 'cabang' => 'outlet', 'kasir' => 'kasir',
        'password' => 'password', 'kata sandi' => 'password', 'lupa' => 'lupa', 'reset' => 'password', 'pin' => 'pin',
        'staf' => 'kasir', 'karyawan' => 'kasir', 'user' => 'user', 'role' => 'user', 'tablet' => 'pin',
        'void' => 'void', 'refund' => 'refund', 'batalkan' => 'void', 'batalin' => 'void', 'salah input' => 'void',
        'export' => 'ekspor', 'ekspor' => 'ekspor', 'excel' => 'ekspor', 'pdf' => 'ekspor', 'unduh' => 'ekspor', 'download' => 'ekspor',
        'qr' => 'qr', 'meja' => 'meja', 'self order' => 'qr',
        'kredit' => 'kredit', 'tanya yoka' => 'kredit', 'yoka ai' => 'kredit', 'top up' => 'kredit', 'topup' => 'kredit', 'top-up' => 'kredit', 'saldo ai' => 'kredit', 'ai nya' => 'kredit', 'ai-nya' => 'kredit',
        'hutang' => 'hutang', 'utang' => 'hutang', 'piutang' => 'piutang', 'biaya' => 'biaya', 'pengeluaran' => 'biaya', 'supplier' => 'hutang', 'laba rugi' => 'biaya',
    ];

    /** @return array<int, array{slug:string, title:string, score:int}> */
    public static function match(string $text, int $limit = 2): array
    {
        $text = Str::lower($text);
        $terms = [];
        foreach (self::ALIASES as $needle => $term) {
            if (str_contains($text, $needle)) {
                $terms[$term] = true;
            }
        }
        if ($terms === []) {
            return [];
        }

        $scored = [];
        foreach (GuideController::guides() as $slug => $guide) {
            $title = Str::lower($guide['title'].' '.$slug);
            $body = Str::lower($guide['summary'].' '.implode(' ', array_map(fn ($s) => $s[0].' '.$s[1], $guide['steps'])));
            $score = 0;
            foreach (array_keys($terms) as $term) {
                $score += (str_contains($title, $term) ? 3 : 0) + (str_contains($body, $term) ? 1 : 0);
            }
            if ($score >= 3) {
                $scored[] = ['slug' => $slug, 'title' => $guide['title'], 'score' => $score];
            }
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /** Ringkasan panduan yang cocok untuk prompt. Kosong bila tidak ada yang relevan. */
    public static function render(string $text): string
    {
        $guides = GuideController::guides();
        $blocks = [];
        foreach (self::match($text) as $hit) {
            $g = $guides[$hit['slug']];
            $steps = implode(' ', array_map(fn ($i, $s) => ($i + 1).'. '.$s[0].': '.$s[1], array_keys($g['steps']), $g['steps']));
            $faq = implode(' ', array_map(fn ($f) => 'T: '.$f[0].' J: '.$f[1], $g['faq'] ?? []));
            $blocks[] = "[{$g['title']}] tautan: yokapos.com/panduan/{$hit['slug']} | {$g['summary']} | Langkah: {$steps}".($faq !== '' ? " | FAQ: {$faq}" : '');
        }

        return $blocks === [] ? '' : "PANDUAN RESMI (sumber langkah yang boleh kamu berikan; jangan menambah langkah lain):\n".implode("\n", $blocks);
    }
}
