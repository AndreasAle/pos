<?php

/*
 * Katalog perangkat dan bundel. Adit hanya boleh menyebut harga perangkat/bundel yang
 * status = 'approved' DAN kompatibilitasnya 'verified'. Semua lainnya = minta penawaran ke tim.
 * Harga beli bersifat internal (tidak pernah masuk prompt).
 */
return [
    'items' => [
        'mp-58n' => [
            'model' => 'MP-58N',
            'type' => 'printer struk thermal 58mm',
            'supplier' => null,
            'connectivity' => ['bluetooth', 'usb'],
            'platforms' => ['android (RawBT)', 'browser print'],
            'compatibility' => 'unverified', // unverified | verified | incompatible — ubah setelah dites dengan Yoka POS
            'purchase_cost' => 166000,       // referensi riset produk, belum harga beli pasti
            'selling_price' => 349000,        // daftar harga v1.0 (2026-11-23); baru boleh disebut setelah status approved + verified
            'warranty' => null,
            'ownership' => 'milik pembeli',
            'stock' => null,
            'shipping_cost' => null,
            'installation' => null,
            'status' => 'candidate',          // candidate | approved | retired
        ],
    ],

    // Daftar harga v1.0 (2026-11-23). Tetap 'draft' sampai printer lulus tes dan syarat (ongkir, garansi, retur) terisi.
    // Harga bundel = harga paket + printer, dengan potongan perangkat untuk durasi lebih panjang.
    'bundles' => [
        'basic-1-58' => [
            'name' => 'YokaBasic 1 Bulan + Printer 58mm',
            'item' => 'mp-58n',
            'plan_slug' => 'starter',
            'initial_price' => 498000,
            'includes_months' => 1,
            'renewal' => 'perpanjangan YokaBasic sesuai harga paket resmi',
            'revenue_allocation' => ['hardware' => null, 'subscription' => null],
            'terms' => ['shipping' => null, 'installation' => null, 'warranty' => null, 'returns' => null, 'refunds' => null, 'taxes' => null],
            'status' => 'draft',
        ],
        'basic-3-58' => [
            'name' => 'YokaBasic 3 Bulan + Printer 58mm',
            'item' => 'mp-58n',
            'plan_slug' => 'starter',
            'initial_price' => 699000,
            'includes_months' => 3,
            'renewal' => 'perpanjangan YokaBasic sesuai harga paket resmi',
            'revenue_allocation' => ['hardware' => null, 'subscription' => null],
            'terms' => ['shipping' => null, 'installation' => null, 'warranty' => null, 'returns' => null, 'refunds' => null, 'taxes' => null],
            'status' => 'draft',
        ],
        'basic-6-58' => [
            'name' => 'YokaBasic 6 Bulan + Printer 58mm',
            'item' => 'mp-58n',
            'plan_slug' => 'starter',
            'initial_price' => 999000,
            'includes_months' => 6,
            'renewal' => 'perpanjangan YokaBasic sesuai harga paket resmi',
            'revenue_allocation' => ['hardware' => null, 'subscription' => null],
            'terms' => ['shipping' => null, 'installation' => null, 'warranty' => null, 'returns' => null, 'refunds' => null, 'taxes' => null],
            'status' => 'draft',
        ],
        'basic-12-58' => [
            'name' => 'YokaBasic 12 Bulan + Printer 58mm',
            'item' => 'mp-58n',
            'plan_slug' => 'starter',
            'initial_price' => 1649000,
            'includes_months' => 12,
            'renewal' => 'perpanjangan YokaBasic sesuai harga paket resmi',
            'revenue_allocation' => ['hardware' => null, 'subscription' => null],
            'terms' => ['shipping' => null, 'installation' => null, 'warranty' => null, 'returns' => null, 'refunds' => null, 'taxes' => null],
            'status' => 'draft',
        ],
    ],
];
