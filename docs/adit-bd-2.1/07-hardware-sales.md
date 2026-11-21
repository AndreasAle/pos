# 07 — Printer dan perangkat

Tidak ada katalog perangkat di repo, jadi tidak dibuat data perangkat yang mengklaim kompatibilitas.

- Tanya printer ("dapet printer gak?") → IDENTIFY_HARDWARE_INTEREST: jawab langsung bahwa langganan tidak termasuk perangkat, jelaskan jalur cetak yang didukung (print browser, RawBT Bluetooth Android), sarankan cek tipe printer saat trial, boleh kirim panduan resmi. Tidak membuat proposal.
- Minta bundel ("aplikasi sama printer sekalian berapa", "printer gratis", "paket lengkap") → `CommercialPolicy` NEEDS_HUMAN_APPROVAL dengan `items=['printer/perangkat']` → kartu `commercial_proposal`. Adit bilang akan ditanyakan ke tim; tanpa harga perangkat.
- Dicatat di `intelligence.hardware`: interest, has_printer, connection (bluetooth/usb/lan), platform (android/ios/laptop), bundle_requested, compatibility="belum diverifikasi"; event `hardware`.

Model komersial SOFTWARE_ONLY berlaku saat ini. HARDWARE_PURCHASE dan APPROVED_BUNDLE belum ada sampai pemilik membuat katalog dan bundel resmi.
