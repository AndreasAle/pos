# Mode Offline Kasir — Implementasi v1

Rancangan: [01-baseline-and-design.md](01-baseline-and-design.md). Branch: `feat/pos-offline`.
Semua fitur di balik flag **`FEATURE_POS_OFFLINE`** (default `false`). Selama flag mati, kasir berjalan persis seperti sebelumnya, kecuali **idempotency** (`client_uuid`) yang aktif untuk semua penjualan.

## Yang dibangun

| Bagian | File |
|---|---|
| Migrasi: `client_uuid` (unique per bisnis), `offline_number`, `device_id`, `synced_at`, `offline_review`, `offline_meta` | `database/migrations/2026_11_23_000001_add_offline_sync_to_orders.php` |
| Order idempotent + mode sinkron offline (stok boleh minus, shift asal, waktu perangkat dibatasi, tandai `price_mismatch` / `underpaid` / `shift_closed` / `no_shift` / `stale`) | `app/Services/PosOrderService.php` |
| `POST /pos/order` menerima `client_uuid`; retry membalas `duplicate: true` tanpa order kedua dan tanpa WA struk kedua | `app/Http/Controllers/PosController.php` |
| Konfigurasi offline di halaman kasir (bisnis, outlet, kasir, shift, `catalog_version`, opsi struk, batas) | `PosController::offlineConfig()` |
| `POST /pos/sync` (batch maks 20, per order transaksi sendiri) dan `GET /pos/ping` (cek koneksi + token CSRF baru) | `app/Http/Controllers/PosSyncController.php` |
| Service worker `/pos-sw.js` (route, bukan file statis, karena `deploy.sh` hanya menyalin `build/`) | `PosServiceWorkerController.php`, `resources/js/pos-offline/sw.js` |
| Mesin offline: IndexedDB outbox, nomor `OFF-XXX-yymmdd-NNNN`, heartbeat 30 detik, sinkron otomatis, batas 12 jam / 300 transaksi | `resources/js/pos-offline/index.js`, `store.js` |
| Struk ESC/POS di perangkat (port `EscPosReceipt.php`), dicetak via `rawbt:` yang ditangkap app Android | `resources/js/pos-offline/escpos.js` |
| UI kasir: pil status (Online / Offline / N menunggu / N gagal), panel antrean + cetak ulang + sinkron sekarang, banner "hanya tunai", QRIS dan bayar terpisah dikunci saat offline, keranjang tahan reload | `resources/views/pos/index.blade.php` |
| Tutup shift: peringatan + konfirmasi bila perangkat masih punya antrean | `resources/views/shifts/close.blade.php` |
| Label "Offline" / "Perlu dicek" + alasan di daftar dan detail order | `resources/views/orders/index.blade.php`, `show.blade.php` |

## Alur
1. Kasir membuka `/pos` online sekali. Service worker terpasang, lalu menyimpan `/pos`, aset build, Alpine CDN, dan font.
2. Internet putus: pil berubah menjadi **Offline**. Reload atau buka app tetap menampilkan kasir dari cache. Membuka `/kasir` saat offline juga menampilkan kasir terakhir.
3. Bayar tunai: transaksi ditulis ke IndexedDB **sebelum** layar sukses muncul, dapat nomor `OFF-…`, lalu struk dicetak lokal.
4. Kalau jaringan putus **di tengah** request online (fetch gagal), transaksi tunai otomatis masuk antrean dengan `client_uuid` yang sama. Kalau server ternyata sudah menerimanya, sinkron membalas `duplicate`, jadi tidak ada dobel.
5. Online lagi: ping berhasil, antrean dikirim per 20 transaksi. Server membuat `ORD-…` resmi dengan `offline_number` dan `ordered_at` dari waktu perangkat.
6. Order yang ditolak server (produk dihapus, varian/add-on tidak valid, kasir bukan dari bisnis itu) tetap disimpan di perangkat dengan status merah "Ditolak server" dan butuh penanganan manual.

## Mengaktifkan (keputusan pemilik; belum dilakukan)
1. Backup database, lalu `php artisan migrate` di `yokapos_app`.
2. Set `FEATURE_POS_OFFLINE=true` di `.env` server yokapos.com, lalu jalankan `php artisan config:cache`.
3. Deploy via `./deploy.sh` (hasil build `public/build` sudah di-commit).
4. Di tablet: buka kasir online sekali, tunggu beberapa detik, lalu uji sesuai rencana di 01 bagian e.

`pos.conweb.id` tidak tersentuh. Flag tidak ada di `.env` klien, jadi tetap mati walaupun kodenya kelak ikut terbawa.

## Tes
- `node --test tests/js/pos-offline-escpos.test.mjs` → 5/5 lulus.
- `tests/Feature/Pos/OfflineSyncTest.php` (19 tes): idempotency, sinkron, replay, konflik, isolasi bisnis, batas batch, ping, service worker, flag. Lulus di MariaDB 11.4 lokal. **Seluruh suite: 857 tes lulus.**
- **Uji end-to-end di tablet (2026-11-23)**: Chrome 157 di tablet → server lokal via `adb reverse`, database demo lokal, flag aktif.
  1. Buka `/pos` online: service worker terpasang, `/pos` + aset + CDN langsung tersimpan.
  2. Wi-Fi/data dimatikan dan server dihentikan, lalu reload: kasir tampil dari cache dengan pil **Offline**.
  3. Bayar tunai Es Teh Manis Rp 8.800, bayar Rp 9.000: layar "Tersimpan Offline" dengan nomor `OFF-A93-261123-0001`, kembalian Rp 200. Banner hanya-tunai tampil, QRIS terkunci.
  4. Struk ESC/POS di perangkat: header, item, catatan, pajak, total, bayar, kembalian, dan label offline lengkap.
  5. Chrome ditutup paksa lalu dibuka lagi saat offline: antrean tetap 1.
  6. Server dan internet dinyalakan lagi: sinkron otomatis dalam ±6 detik, menjadi `ORD-261123-0001`. Di DB: 1 order, `created_at` = waktu di perangkat, shift benar, tanpa `offline_review`.
  7. Transaksi online biasa: `ORD-261123-0002`. Proses bayar diulang dengan uuid sama: order yang sama, tidak dobel.
- Bug yang ditemukan saat uji dan sudah diperbaiki: setelah aplikasi ditutup paksa, keranjang yang sudah dibayar muncul lagi. Sekarang keranjang tersimpan dihapus begitu transaksi berhasil, dan `record()` tidak menimpa transaksi dengan uuid yang sama.
- Belum diuji: cetak fisik ke printer Bluetooth (byte struk sudah diverifikasi), serta jalur di dalam app Yoka, karena app terkunci ke yokapos.com dan butuh deploy.

## Batasan v1 yang disengaja
- Hanya tunai, tanpa bundle, pelanggan, promo, diskon manual, poin, delivery, atau order hold.
- Login PIN dan buka shift tetap butuh internet.
- Struk offline hanya lewat Bluetooth/RawBT (mode printer "PC/laptop" tidak bisa offline). Struk dapur belum dicetak offline.
- Sinkron berjalan saat halaman kasir terbuka (bukan Background Sync di service worker), karena butuh sesi dan CSRF halaman.
- Antrean tersimpan di penyimpanan WebView. **Hapus data aplikasi = antrean hilang.** UI memperingatkan hal ini.

## Temuan lain (di luar lingkup, belum diubah)
- `inApp` di kasir hanya mengenali UA `POSKasirApp`. Aplikasi Yoka memakai `YokaPOSApp/1.0.0`, sehingga cetak dapur otomatis tidak jalan di app Yoka.
- Saat offline, Android WebView menampilkan halaman error Chromium, bukan layar "Coba Lagi" native. Dengan service worker ini tidak lagi terjadi untuk `/pos` dan `/kasir`.
