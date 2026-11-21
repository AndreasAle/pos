<?php

/*
 * Kebijakan layanan resmi Yoka yang boleh disebut Adit. Kosong = belum ditetapkan pemilik,
 * dan Adit TIDAK boleh mengarang jam atau target respon.
 */
return [
    // Contoh: "Senin-Sabtu 09.00-17.00 WIB". null = belum ditetapkan.
    'human_hours' => env('YOKA_SUPPORT_HOURS') ?: null,
    // Target respon resmi, mis. "maksimal 1x24 jam kerja". null = belum ditetapkan.
    'response_target' => env('YOKA_SUPPORT_RESPONSE_TARGET') ?: null,
    'channels' => [
        'WhatsApp resmi Yoka (dijawab Adit, asisten AI, kapan saja; tim manusia ikut menangani bila perlu)',
        'Panduan tertulis di yokapos.com/panduan',
    ],
    'escalation' => 'Adit mencatat kasus ke tim Yoka; tim manusia menindaklanjuti. Tidak ada janji waktu selama jam/target belum ditetapkan.',
    'installation_help' => env('YOKA_SUPPORT_INSTALLATION') ?: null, // mis. "bantuan setup jarak jauh lewat WhatsApp"
    'package_entitlements' => [], // belum ada perbedaan layanan per paket
];
