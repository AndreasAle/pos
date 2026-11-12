<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicFeaturePagesTest extends TestCase
{
    /** Every feature page is rendered by the same template, so each one must show its own copy and photo. */
    public function test_every_public_feature_page_can_be_opened(): void
    {
        $features = [
            'transaksi-kasir' => 'Layani antrean lebih cepat dari satu layar',
            'pembayaran-qris' => 'Pembayaran cepat, nominal tercatat tepat',
            'hold-order' => 'Simpan pesanan, lanjutkan kapan saja',
            'struk-printer' => 'Struk langsung siap setelah pembayaran',
            'stok-resep' => 'Stok bahan berkurang otomatis setiap ada penjualan',
            'kitchen-display' => 'Pesanan dari kasir langsung terlihat di dapur',
            'shift-karyawan' => 'Kontrol uang kas dan akses setiap anggota tim',
            'pelanggan-promosi' => 'Buat pelanggan punya alasan untuk kembali',
            'telegram' => 'Tanya kondisi toko langsung dari Telegram',
            'laporan' => 'Ambil keputusan dari angka yang mudah dipahami',
        ];

        foreach ($features as $feature => $headline) {
            $this->get(route('features.show', $feature))
                ->assertOk()
                ->assertSee('Yoka POS')
                ->assertSee($headline)
                ->assertSee('Cara kerjanya')
                ->assertSee('Yang kamu dapatkan')
                ->assertSee('Fitur lainnya')
                ->assertSee('Saatnya bisnis', false); // shared footer
        }
    }

    public function test_feature_pages_share_one_template_without_rounded_styling(): void
    {
        $html = $this->get(route('features.show', 'hold-order'))->assertOk()->getContent();
        $main = substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));

        $this->assertStringNotContainsString('rounded', $main);
        $this->assertStringNotContainsString('shadow', $main);
        $this->assertStringContainsString('images/features/hold-order.webp', $main);
    }

    public function test_unknown_feature_returns_not_found(): void
    {
        $this->get(route('features.show', 'tidak-ada'))->assertNotFound();
    }
}
