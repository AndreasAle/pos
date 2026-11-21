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
            'selling_price' => null,          // belum ditetapkan
            'warranty' => null,
            'ownership' => 'milik pembeli',
            'stock' => null,
            'shipping_cost' => null,
            'installation' => null,
            'status' => 'candidate',          // candidate | approved | retired
        ],
    ],

    'bundles' => [
        'starter-kit' => [
            'name' => 'Yoka POS Starter Kit',
            'item' => 'mp-58n',
            'plan_slug' => 'starter',
            'initial_price' => 349000,        // usulan, BELUM disetujui
            'includes_months' => 1,
            'renewal' => 'Starter sesuai harga paket resmi per bulan',
            // Pembagian pendapatan untuk pembukuan: perangkat vs langganan (tidak semuanya pendapatan SaaS).
            'revenue_allocation' => ['hardware' => null, 'subscription' => null],
            'terms' => ['shipping' => null, 'installation' => null, 'warranty' => null, 'returns' => null, 'refunds' => null, 'taxes' => null],
            'status' => 'draft',              // draft | approved | retired
        ],
    ],
];
