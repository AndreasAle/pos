<?php

/*
 * Perangkat dan paket bundling yang tampil di yokapos.com/bundling.
 * HANYA harga jual publik. Modal, harga impas, dan batas nego ada di sheet harga internal pemilik
 * (jangan ditulis di repo publik ini).
 *
 * Daftar harga v2, 2026-11-24. Setiap bundel termasuk YokaBasic 12 bulan (harga dari subscription_plans),
 * kunjungan setup / Google Meet, dan garansi tukar unit 30 hari.
 * 'status': 'preorder' = dipesan lewat tim (unit dibeli setelah pesanan, dites dengan Yoka sebelum dikirim).
 */
return [
    // Keputusan pemilik 2026-11-24: Adit (CS/BD) boleh menyebut harga paket dan perangkat di halaman ini persis,
    // tanpa diskon, dan meneruskan pesanan ke tim. Matikan dengan ADIT_QUOTE_BUNDLES=false.
    'adit_can_quote' => (bool) env('ADIT_QUOTE_BUNDLES', true),

    'plan_months' => 12,
    'warranty_days' => 30,

    'devices' => [
        'printer58' => ['name' => 'Printer struk 58mm', 'spec' => 'Bluetooth, kertas 58mm, port laci kas', 'price' => 349000, 'art' => 'printer'],
        'printer80' => ['name' => 'Printer struk 80mm', 'spec' => 'Struk lebih lebar, cocok untuk dapur dan resto', 'price' => 649000, 'art' => 'printer80'],
        'scanner' => ['name' => 'Barcode scanner', 'spec' => '2D, Bluetooth dan USB, bisa scan QR di layar HP', 'price' => 349000, 'art' => 'scanner'],
        'tablet' => ['name' => 'Tablet kasir', 'spec' => 'Layar besar untuk menu dan pembayaran', 'price' => 1349000, 'art' => 'tablet'],
        'stand' => ['name' => 'Stand tablet', 'spec' => 'Tablet berdiri rapi di meja kasir', 'price' => 150000, 'art' => 'stand'],
        'mpos' => ['name' => 'Mesin kasir Android', 'spec' => 'Layar sentuh dengan printer struk bawaan, bisa dibawa ke meja', 'price' => 3299000, 'art' => 'mpos'],
        'drawer' => ['name' => 'Laci kas', 'spec' => '4 sekat uang kertas, 5 koin, terkunci', 'price' => 549000, 'art' => 'drawer'],
        'paper' => ['name' => 'Kertas struk 58mm', 'spec' => '10 roll', 'price' => 50000, 'art' => 'paper'],
    ],

    'bundles' => [
        'printer' => [
            'name' => 'Paket Printer', 'for' => 'Warung yang sudah punya HP atau tablet',
            'items' => ['printer58'], 'price' => 1199000, 'status' => 'preorder',
        ],
        'scanner' => [
            'name' => 'Paket Scanner', 'for' => 'Toko kelontong dan retail yang jual barang berbarcode',
            'items' => ['scanner'], 'price' => 1199000, 'status' => 'preorder',
        ],
        'tablet' => [
            'name' => 'Paket Tablet', 'for' => 'Kasir tanpa struk cetak, layar lebih besar dari HP',
            'items' => ['tablet', 'stand'], 'price' => 2199000, 'status' => 'preorder',
        ],
        'tablet-printer' => [
            'name' => 'Paket Tablet + Printer', 'for' => 'Paling pas untuk warung makan dan kafe kecil',
            'items' => ['tablet', 'stand', 'printer58'], 'price' => 2399000, 'status' => 'preorder', 'featured' => true,
        ],
        'mpos' => [
            'name' => 'Paket Mesin Kasir', 'for' => 'Satu alat untuk transaksi dan cetak struk',
            'items' => ['mpos'], 'price' => 3999000, 'status' => 'preorder',
        ],
        'lengkap' => [
            'name' => 'Paket Lengkap', 'for' => 'Kasir lengkap dengan laci dan scanner',
            'items' => ['tablet', 'stand', 'printer80', 'drawer', 'scanner'], 'price' => 3299000, 'status' => 'preorder',
        ],
        'lengkap-pro' => [
            'name' => 'Paket Lengkap Pro', 'for' => 'Mesin kasir, laci, dan scanner untuk toko ramai',
            'items' => ['mpos', 'drawer', 'scanner'], 'price' => 4699000, 'status' => 'preorder',
            'note' => 'Laci dibuka dengan kunci (mesin kasir tidak punya port laci).',
        ],
        'mpos-scanner' => [
            'name' => 'Paket Mesin Kasir + Scanner', 'for' => 'Untuk toko yang tidak butuh laci',
            'items' => ['mpos', 'scanner'], 'price' => 4199000, 'status' => 'preorder',
        ],
    ],
];
