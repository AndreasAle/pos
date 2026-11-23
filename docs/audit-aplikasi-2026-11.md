# Audit Aplikasi Yoka POS — apa yang masih kurang

Tanggal: 2026-11-23. Cakupan: web yokapos.com (Laravel) dan app tablet Android (flavor `yoka`).

Dasar audit:
- **[FAKTA]**: dibaca dari kode, diuji di tablet, atau dicek di yokapos.com hari ini.
- **[PASAR]**: pengetahuan umum tentang Moka, Majoo, dan Olsera. Belum diverifikasi fitur per fitur.

---

## Ringkasan
Fitur Yoka **sudah lengkap untuk kelas UMKM F&B**: kasir, varian/add-on, bundle, promo, poin, split bill, QRIS, shift, stok + resep, pembelian/supplier, piutang, biaya, laporan, QR meja + self-order, dapur, ShopeeFood, cetak Bluetooth, mode offline v1, dan 872 tes otomatis.

Yang kurang **bukan jumlah fitur**, melainkan:
1. **Kesiapan rilis app**: build rilis, Play Store, dan uji printer sungguhan.
2. **Beberapa bug dan celah kecil** yang langsung terasa di kasir.
3. **Fitur pembeda** yang biasa dicari pemilik: laporan di HP, absensi, dan layar pelanggan.

---

## P0 — Perbaiki sebelum dijual luas

| # | Masalah | Bukti | Dampak |
|---|---|---|---|
| 1 | **Cetak dapur otomatis tidak jalan di app Yoka.** Kode kasir mengenali app hanya lewat UA `POSKasirApp`, sedangkan app Yoka mengirim `YokaPOSApp/1.0.0`. | [FAKTA] `pos/index.blade.php` (`inApp: /POSKasirApp/…`) | Outlet dengan dapur harus menekan cetak manual |
| 2 | **Belum pernah diuji cetak ke printer thermal sungguhan** di app Yoka (online maupun offline). Tablet tes tidak punya printer. | [FAKTA] daftar Bluetooth di tablet | Risiko terbesar di hari pertama outlet |
| 3 | **Alpine.js diambil dari CDN dengan versi mengambang** (`alpinejs@3.x.x`) di 14 halaman. Versi baru bisa merusak kasir tanpa ada deploy, dan kalau CDN lambat atau diblokir, layar kosong. | [FAKTA] 14 file view | Kasir bisa mati mendadak |
| 4 | **App di tablet masih build debug** (`com.yokapos.kasir.debug`). Branch Android `feat/device-setup-flow` belum di-push. | [FAKTA] | Belum bisa dirilis atau dibagikan ke klien |
| 5 | **Order uji di production** (`ORD-261123-0001`, `ORD-261123-0002`) belum di-void. | [FAKTA] | Laporan bisnis tes tercampur |

## P1 — Kurang terasa "lancar" untuk kasir dan pemilik

| # | Masalah | Bukti |
|---|---|---|
| 6 | **Mode offline masih terbatas:** hanya tunai, tanpa bundle/pelanggan/promo/diskon/poin/hold. Login PIN dan buka shift juga butuh internet. | [FAKTA] docs/offline-mode |
| 7 | **Struk offline hanya lewat Bluetooth.** Printer "PC/laptop" dan struk dapur tidak bisa offline. | [FAKTA] |
| 8 | **Tombol "Cetak ke Dapur" tampil di layar sukses offline** tapi tidak melakukan apa-apa. Sengaja dibiarkan atas permintaan pemilik. | [FAKTA] |
| 9 | **Pemilik tidak punya laporan di HP.** App hanya untuk kasir, sedangkan dashboard/laporan harus dibuka di browser. Moka/Majoo/Olsera umumnya punya app atau tampilan pemilik di HP. | [FAKTA] `KASIR_PAGES`; [PASAR] |
| 10 | **Order tanpa shift** (pemilik berjualan tanpa buka shift) ditandai "perlu dicek". Uang tunai pemilik tidak masuk hitungan laci mana pun. | [FAKTA] uji hari ini |
| 11 | **Sesi web 120 menit** (`SESSION_LIFETIME`). PIN login memakai "remember", jadi kasir aman. Pemilik yang login dengan email di tablet bisa kehabisan sesi di tengah hari. | [FAKTA] `.env.example`, `PinLoginController` |
| 12 | **Kasir tanpa email** dari setup cepat mendapat alamat `@kasir.yokapos.invalid`. Untuk login web nanti perlu diganti di back office, dan halaman Pengguna belum menjelaskannya. | [FAKTA] `DeviceSetupController` |

## P2 — Fitur yang umum di pesaing tapi belum ada [PASAR]
Belum diverifikasi satu per satu di produk pesaing. Anggap ini daftar kandidat, bukan daftar wajib.

| Fitur | Status di Yoka |
|---|---|
| **App/tampilan pemilik di HP** (omzet hari ini, notifikasi) | Belum. Ada Telegram bot internal, belum untuk merchant. |
| **Absensi karyawan** (clock-in dengan PIN/foto) | Belum ada modul |
| **Layar pelanggan** (second screen total/QR) | Belum ada |
| **Stok opname terjadwal + transfer stok antar outlet** | Ada stok dan resep; opname/transfer belum terlihat |
| **Pembayaran EDC/kartu terintegrasi** | Belum (hanya tunai, QRIS, dan metode manual) |
| **GoFood/GrabFood inbound** | Baru ShopeeFood (GoBiz OAuth sedang dikerjakan) |
| **Uang muka/reservasi meja** | Belum |
| **Label/barcode produk** | Ada (`products/barcode-select`) |

## P3 — Kesehatan teknis

| # | Temuan | Bukti |
|---|---|---|
| 13 | `resources/views/pos/index.blade.php` sekitar 2.300 baris (HTML + Alpine dalam satu file). Setiap perubahan kasir berisiko. | [FAKTA] |
| 14 | Tidak ada tes browser (E2E) untuk layar kasir. Semua tes adalah tes PHP + 5 tes JS struk, sehingga bug seperti pil "diam" tadi tidak tertangkap tes. | [FAKTA] |
| 15 | Chunk JS `YokaProductExperience` 1,3 MB (landing page). Kasir tidak terkena karena service worker tidak memuatnya, tapi landing page jadi berat. | [FAKTA] build Vite |
| 16 | Folder repo berisi perubahan yang belum jelas pemiliknya (`GoBizOAuthController.php`, `konten-tiktok/`). | [FAKTA] git status |

---

## Usulan urutan kerja
1. **Minggu ini:** P0 #1 (cetak dapur di app Yoka), P0 #3 (bundel Alpine ke Vite, tanpa CDN), P0 #5 (void order uji). Lalu uji P0 #2 dengan printer thermal sungguhan di satu outlet.
2. **Rilis app:** build rilis + push branch Android, lalu closed testing Play Store (P0 #4).
3. **Kenyamanan:** pemilik wajib buka shift atau "shift pemilik" otomatis (P1 #10), serta offline untuk bundle dan diskon manual (P1 #6).
4. **Pembeda:** ringkasan omzet di HP untuk pemilik (P2), lalu absensi.
