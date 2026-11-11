<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicFeaturePagesTest extends TestCase
{
    public function test_every_public_feature_page_can_be_opened(): void
    {
        $features = [
            'transaksi-kasir' => 'Satu layar.',
            'pembayaran-qris' => 'PAYMENT REQUEST',
            'hold-order' => 'Titip dulu.',
            'struk-printer' => 'YOKA COFFEE',
            'stok-resep' => 'Inventory logic',
            'kitchen-display' => 'KITCHEN DISPLAY / LIVE',
            'shift-karyawan' => 'SHIFT REPORT',
            'pelanggan-promosi' => 'YOKA MEMBER',
            'telegram' => 'Bukan AI generatif.',
            'laporan' => 'PENJUALAN HARIAN',
        ];

        foreach ($features as $feature => $uniqueContent) {
            $this->get(route('features.show', $feature))
                ->assertOk()
                ->assertSee('Yoka POS')
                ->assertSee($uniqueContent);
        }
    }

    public function test_unknown_feature_returns_not_found(): void
    {
        $this->get(route('features.show', 'tidak-ada'))->assertNotFound();
    }
}
