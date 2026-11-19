<?php

namespace Tests\Unit;

use App\Services\PlatformAgents\CustomerService\MenuKnowledge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Adit harus menguasai SETIAP menu aplikasi. Tes ini gagal bila ada menu samping baru
 * yang belum dijelaskan di MenuKnowledge, dan memastikan pertanyaan umum menemukan menu yang benar.
 */
class AditMenuKnowledgeTest extends TestCase
{
    public function test_every_sidebar_menu_has_knowledge(): void
    {
        $layout = file_get_contents(__DIR__.'/../../resources/views/layouts/app.blade.php');
        preg_match_all('/<x-nav-item[^>]*>(.*?)<\/x-nav-item>/s', $layout, $m);
        $names = array_map(fn ($m) => mb_strtolower($m[0]), MenuKnowledge::MENUS);

        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $label) {
            $label = trim(preg_replace('/\{\{.*?\}\}/s', 'Produk / Menu', $label));
            $this->assertContains(mb_strtolower($label), $names, "Menu \"{$label}\" belum dikuasai Adit (tambahkan ke MenuKnowledge).");
        }
        $this->assertContains('tanya yoka', $names);
    }

    public function test_map_lists_every_menu_and_stays_compact(): void
    {
        $peta = MenuKnowledge::peta();
        foreach (MenuKnowledge::MENUS as $menu) {
            $this->assertStringContainsString($menu[0], $peta);
        }
        $this->assertLessThan(5000, mb_strlen($peta));
    }

    public static function questions(): array
    {
        return [
            ['cara bikin varian ukuran L di menu?', 'produk'],
            ['bisa tambah topping extra keju?', 'produk'],
            ['gimana cetak label barcode', 'produk'],
            ['mau bikin paket hemat combo', 'bundling'],
            ['kategori mana yang dikirim ke dapur', 'kategori'],
            ['poin loyalitas pelanggan gimana', 'pelanggan'],
            ['bikin promo happy hour bisa?', 'promo'],
            ['layar dapur kds statusnya apa aja', 'dapur'],
            ['cara stok opname bahan baku', 'bahan_baku'],
            ['atur takaran resep per porsi', 'resep'],
            ['bisa bayar sebagian cash sebagian qris?', 'kasir'],
            ['pesanan bisa di hold dulu?', 'kasir'],
            ['tutup shift selisih kas', 'shift'],
            ['laporan pajak ppn dimana', 'laporan_pajak'],
            ['lihat laba rugi bulan ini', 'laba_rugi'],
            ['export laporan penjualan ke excel', 'laporan_penjualan'],
            ['catat pengeluaran listrik', 'biaya'],
            ['bayar hutang supplier', 'hutang'],
            ['pelanggan kasbon dicatat dimana', 'piutang'],
            ['ganti role kasir jadi admin', 'user'],
            ['ubah jenis bisnis jadi retail', 'profil_bisnis'],
            ['aktifkan service charge', 'pengaturan_struk'],
            ['struk dikirim lewat whatsapp bisa?', 'pengaturan_struk'],
            ['qris dinamis nominal terkunci gimana', 'qris'],
            ['siapa yang hapus produk saya? cek audit', 'audit_log'],
            ['sambung ke google sheets', 'integrasi'],
            ['refund order kemarin', 'riwayat_order'],
            ['pesanan dari meja qr belum masuk', 'pesanan_qr'],
        ];
    }

    #[DataProvider('questions')]
    public function test_question_finds_the_right_menu(string $question, string $menu): void
    {
        $this->assertContains($menu, MenuKnowledge::match($question), $question);
        $this->assertStringContainsString(MenuKnowledge::MENUS[$menu][0], MenuKnowledge::detail($question));
    }
}
