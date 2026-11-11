<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicFeaturePagesTest extends TestCase
{
    public function test_every_public_feature_page_can_be_opened(): void
    {
        $features = [
            'transaksi-kasir',
            'pembayaran-qris',
            'hold-order',
            'struk-printer',
            'stok-resep',
            'kitchen-display',
            'shift-karyawan',
            'pelanggan-promosi',
            'telegram',
            'laporan',
        ];

        foreach ($features as $feature) {
            $this->get(route('features.show', $feature))
                ->assertOk()
                ->assertSee('Yoka POS');
        }
    }

    public function test_unknown_feature_returns_not_found(): void
    {
        $this->get(route('features.show', 'tidak-ada'))->assertNotFound();
    }
}
