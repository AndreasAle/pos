# Mode Offline Kasir Yoka — Baseline & Rancangan (v1)

Tanggal: 2026-11-23 · Status: **draf, menunggu persetujuan pemilik** · Belum ada kode fitur.

Penanda di dokumen ini:
- **[FAKTA-UJI]**: diamati langsung di tablet.
- **[FAKTA-KODE]**: dibaca dari kode (repo `POS` atau `POS-Android`).
- **[ASUMSI]**: belum dibuktikan dan perlu dicek saat implementasi.

---

## a. Baseline di tablet

### Perangkat & aplikasi
| Hal | Nilai | Sumber |
|---|---|---|
| Tablet | Xiaomi 22081283G (Android), via adb USB | FAKTA-UJI |
| Aplikasi | `com.yokapos.kasir.debug` v1.0.0 (flavor `yoka`, build debug) | FAKTA-UJI |
| Bentuk | APK native (Splash/Onboarding/Welcome/Register) + **WebView** ke `https://yokapos.com` (fix, tidak bisa diganti) | FAKTA-UJI + FAKTA-KODE `app/build.gradle` |
| Android System WebView | 157.0.8103.50 | FAKTA-UJI |
| Chrome | 157.0.8103.80 | FAKTA-UJI |
| User-Agent | `... YokaPOSApp/1.0.0` | FAKTA-UJI |
| `serviceWorker` / `SyncManager` / IndexedDB / Cache API | semua **tersedia** | FAKTA-UJI (CDP) |
| Service worker terdaftar / cache / database IndexedDB | **0 / 0 / 0** | FAKTA-UJI |
| `setDomStorageEnabled`, `setDatabaseEnabled` | `true`; `setCacheMode(LOAD_DEFAULT)` | FAKTA-KODE `MainActivity.java:868-878` |
| RawBT | `ru.a402d.rawbtprinter` terpasang, tetapi app Yoka mencetak sendiri (Bluetooth SPP / TCP 9100) lewat `ReceiptPrinter` | FAKTA-UJI + FAKTA-KODE |

Catatan: di tablet juga ada `com.conwebs.pos` (aplikasi situs klien). Aplikasi ini tidak disentuh.

### Langkah reproduksi & hasil
Akun tes milik pemilik, login sendiri oleh pemilik. Halaman: `https://yokapos.com/pos`.

1. Kondisi online: halaman kasir tampil normal, menu 20 produk. **[FAKTA-UJI]**
2. `adb shell svc wifi disable && adb shell svc data disable`. Ping ke 8.8.8.8 gagal ("Network is unreachable"), `navigator.onLine === false`. **[FAKTA-UJI]**
3. **Halaman yang sudah terbuka tetap bisa dipakai.** Tambah item (Kopi Tubruk Rp 12.000, modal add-on muncul), total terhitung, buka modal Pembayaran, pilih Cash, Uang pas. Semuanya jalan karena data menu sudah tertanam di halaman. **[FAKTA-UJI]**
4. **Tidak ada indikator offline** di layar kasir: tidak ada banner, tombol Bayar tetap aktif. **[FAKTA-UJI]**
5. Tekan **Proses bayar**: muncul dialog native browser *"Koneksi error: Failed to fetch"*. Setelah OK, modal Pembayaran tetap terbuka dan **keranjang masih ada (1 item, tersimpan di memori)**. **[FAKTA-UJI]**
6. **Reload halaman saat offline**: halaman error Chromium *"Halaman web tidak tersedia — net::ERR_INTERNET_DISCONNECTED"*. Layar error native ("Coba Lagi") **tidak muncul**. Kasir tidak bisa apa-apa. **[FAKTA-UJI]**
7. Wi-Fi dan data dinyalakan lagi (ping OK). **Halaman tidak pulih sendiri**: tetap di `chrome-error://chromewebdata/` sampai dinavigasi ulang secara manual. **[FAKTA-UJI]**
8. Setelah dibuka ulang: **keranjang hilang** (0 item). **[FAKTA-UJI]**
9. Daftar order: tidak ada order bertanggal 23-11-2026. Transaksi offline **tidak tercatat** dan **tidak ada dobel**. **[FAKTA-UJI]**
10. Cetak struk saat offline tidak bisa dites karena tidak ada order yang tersimpan. Dari kode, struk ESC/POS dibuat server (`/receipt/{id}/print?via=rawbt` → `EscPosReceipt::render`), dan app Android mengambilnya lewat HTTP (`fetchEscPos`). Jadi **saat offline struk pasti tidak bisa dicetak**. **[FAKTA-KODE]**

Screenshot ada di scratchpad sesi, tidak ikut di repo.

### Ketergantungan checkout pada server [FAKTA-KODE]
| Bagian | Lokasi | Butuh server? |
|---|---|---|
| Halaman kasir + menu/harga/varian/add-on/bundle/promo (JSON tertanam di Blade) | `PosController::index` | ya, saat halaman dimuat |
| Wajib shift terbuka (role cashier) | `PosController::index` → redirect `shifts.open` | ya |
| Simpan order | `POST /pos/order` → `PosOrderService::createOrder` (1 transaksi DB) | ya |
| Harga dihitung ulang di server (harga dari klien diabaikan) | `cartRules()` + service | ya |
| Pajak, service, promo, diskon manual, poin | service | ya |
| Nomor order `ORD-yymmdd-NNNN` (MAX+1 per bisnis per hari) | `generateOrderNumber()` | ya |
| Potong stok bahan via resep, tolak bila stok kurang (kecuali `allow_negative_stock`) | `InventoryService::deductFromRecipe` | ya |
| Validasi uang tunai cukup | service | ya |
| Poin pelanggan, WA struk, notifikasi Telegram | service/controller | ya |
| Struk ESC/POS / RawBT | `EscPosReceipt`, `receipt/print.blade.php` | ya |
| Struk dapur | `ReceiptController::kitchen` | ya |
| Hold order / drafts | `/pos/hold`, `/pos/drafts` | ya |
| QRIS (dinamis / Midtrans) | `/pos/qris/*`, `/midtrans/*` | ya (by design) |
| **Idempotency**: tidak ada kunci unik dari klien | tabel `orders` | Respons hilang lalu kasir retry = **order dobel** |
| Sesi | `SESSION_LIFETIME` default 120 menit | cookie sesi + CSRF |

---

## b. Ruang lingkup Offline v1

**Masuk v1:**
1. Kasir yang **sudah pernah membuka `/pos` online** di perangkat itu bisa membuka kasir lagi saat offline (termasuk reload atau app dibuka ulang).
2. Tambah item: produk, varian, add-on, catatan, dine-in/takeaway.
3. Bayar **TUNAI saja** (uang pas atau lebih, ada kembalian).
4. Cetak struk via jalur `rawbt:` yang ditangkap app Android (Bluetooth/TCP), dengan struk disusun di perangkat dan ditandai **"OFFLINE"**.
5. Order masuk **antrean lokal (IndexedDB)** dan **disinkron otomatis** saat online, tanpa dobel.
6. Indikator jelas: Online / Offline / *N transaksi menunggu sinkron*.

**Ditolak saat offline, dengan pesan jelas:** QRIS, transfer, e-wallet, debit, bayar terpisah, poin/redeem, pilih pelanggan, promo yang butuh server, bundle (v1), hold/draft, delivery platform, struk WA.
Pesan: *"Sedang offline. Hanya pembayaran tunai yang bisa diproses. QRIS dan non-tunai aktif lagi saat internet tersambung."*

**Di luar v1:** buka/tutup shift offline, refund/void offline, login PIN offline pertama kali, sinkron antar-perangkat saat offline.

---

## c. Rancangan teknis

### c1. Service worker (`public/sw.js`, scope `/`)
- **Precache saat online**: shell `/pos` (HTML terakhir yang berhasil dimuat), aset `public/build/*` (Vite, nama ber-hash), font, ikon, gambar produk (best-effort, maks N MB).
- `/pos` (navigasi): **network-first, timeout 3 detik**, fallback ke cache. Halaman cache diberi flag `data-offline-snapshot` + waktu snapshot.
- `/pos/order` dan endpoint POST lain **tidak pernah di-cache**.
- Hanya aktif untuk path kasir. Back office tidak di-cache, supaya data sensitif tidak tertinggal di perangkat.
- Versi SW diikat ke manifest Vite. Saat deploy, SW baru `skipWaiting` hanya bila antrean kosong.
- **[ASUMSI]** SW di WebView 157 jalan untuk `https://yokapos.com` tanpa ubah APK. API sudah terbukti ada (FAKTA-UJI), tapi registrasi dan intercept navigasi perlu dites. Bila `onReceivedError` native ternyata menimpa fallback SW, perlu perubahan kecil di APK.

### c2. Snapshot katalog
- Endpoint baru `GET /pos/catalog.json` berisi data yang sama dengan JSON di Blade, ditambah `settings` (pajak/service/struk), `outlet`, `business`, `shift_id`, `catalog_version` (hash), dan `generated_at`.
- Disimpan di IndexedDB `yoka-pos/{business_id}/{outlet_id}`. Halaman memakai snapshot bila offline.

### c3. Antrean order (IndexedDB)
Store `outbox`, satu record per order:
```
{ client_uuid, business_id, outlet_id, user_id, shift_id, device_id,
  offline_number, created_at_device, catalog_version,
  items[], totals{subtotal,tax,service,grand}, paid_amount, change,
  status: queued|syncing|synced|rejected|needs_review, attempts, last_error,
  server_order_id?, server_order_number? }
```
- Ditulis **sebelum** struk dicetak dan sebelum keranjang dikosongkan. Kalau gagal ditulis, transaksi tidak dianggap selesai.
- Minta `navigator.storage.persist()`. **[ASUMSI]** WebView mengizinkannya. Kalau tidak, risikonya data terhapus saat penyimpanan perangkat penuh.

### c4. ID order dari klien (idempotent)
- `client_uuid` = UUID v7 (`crypto.randomUUID()` sebagai fallback), dibuat **untuk setiap transaksi, online maupun offline**. Ini juga menutup bug dobel saat online.
- Migrasi: `orders.client_uuid CHAR(36) NULL` + **unique index `(business_id, client_uuid)`**, kolom `source ENUM('online','offline_sync')`, `device_id`, `offline_number`, `ordered_at_device`.
- Server: kalau `client_uuid` sudah ada, kembalikan order yang sama (HTTP 200, `duplicate: true`) tanpa membuat order baru.

### c5. Nomor struk offline
- Format: `OFF-{kodeDevice3}-{yymmdd}-{seq4}`, contoh `OFF-A7K-261123-0001`. Sequence per perangkat per hari disimpan di IndexedDB.
- `device_id` dibuat sekali (UUID) lalu disimpan. Kode 3 huruf diambil dari hash. Tidak bisa bentrok dengan `ORD-...`.
- Saat sinkron, server tetap membuat `ORD-...` resmi dan menyimpan `offline_number`. Pencarian order dan cetak ulang mendukung dua nomor ini.

### c6. Endpoint sinkron batch
`POST /pos/sync` (middleware `json`, auth, CSRF), body `{ device_id, orders: [...maks 20] }`.
- Diproses **per order dalam transaksi DB sendiri-sendiri**: satu order gagal tidak menggagalkan yang lain.
- Memakai ulang `PosOrderService::createOrder` dengan flag `offline` (beda aturan: lihat c7). `ordered_at` diambil dari waktu perangkat (dibatasi ke rentang shift), `synced_at = now()`.
- Respons per order: `synced` (+ `order_id`, `order_number`) / `duplicate` / `needs_review` (+ alasan) / `rejected` (+ alasan, khusus yang tidak bisa dicatat sama sekali, misalnya produk dari bisnis lain).
- Throttle per user, contohnya 30 per menit.
- Pemicu sinkron: event `online`, Background Sync (`sync` tag) bila tersedia, interval 30 detik saat ada antrean, dan saat halaman dibuka.

### c7. Konflik — prinsip: **uang tunai sudah diterima = fakta**. Server tidak boleh menolak order offline hanya karena aturan bisnis. Order dicatat lalu ditandai untuk dicek.
| Konflik | Keputusan |
|---|---|
| Harga berubah sejak snapshot | Pakai **harga snapshot** (yang dibayar pelanggan). Simpan `price_mismatch` lalu tandai `needs_review`. Bukan celah: hanya harga dari `catalog_version` yang tercatat di server (hash dicek) yang diterima. |
| Produk dinonaktifkan/dihapus | Tetap dicatat (soft-deleted masih bisa diacu), tandai `needs_review`. |
| Stok kurang | Potong stok sampai minus walau `allow_negative_stock` mati, catat `StockMovement` bertipe `sale_offline`, lalu tampilkan peringatan di laporan stok. |
| Shift sudah ditutup saat sinkron | Order masuk ke shift asal (`shift_id` dari perangkat). Kalau shift sudah tutup, tambah ke `offline_late_cash` dan buat catatan selisih kas shift. Jangan buka ulang shift diam-diam. |
| Bisnis/outlet/user tidak cocok dengan sesi | `rejected`. Order tetap disimpan lokal dengan status merah, butuh admin. |
| Pajak/service berubah | Pakai nilai snapshot, sama seperti harga. |
| Total klien ≠ hitung ulang server dengan snapshot | `needs_review` (tanda manipulasi atau bug). |

### c8. Indikator UI (sistem desain Yoka, tanpa emoji)
- Pil di header: **Online** (hijau `#16A34A`) / **Offline** (amber) / **Menyinkron N** / **N gagal** (merah, bisa diklik).
- Banner saat offline di atas daftar produk, tombol non-tunai dinonaktifkan dengan pesan dari bagian b.
- Panel "Antrean offline": daftar order lokal, status, tombol "Sinkron sekarang" dan "Cetak ulang".
- Ganti dialog `alert()` "Koneksi error" dengan toast yang tidak menghilangkan keranjang.
- Keranjang aktif disimpan ke `sessionStorage`/IndexedDB, supaya reload tidak menghapusnya.

### c9. Struk offline
- Port `EscPosReceipt::render` ke JS (`resources/js/pos/escpos.js`) dengan format byte yang sama. Header dan footer struk diambil dari snapshot settings.
- Cetak: `location.href = 'rawbt:base64,' + b64`. Link ini ditangkap `MainActivity.printRawbtLink()` dan langsung dikirim ke printer, tanpa jaringan **[FAKTA-KODE]**. Di browser biasa link ini membuka app RawBT.
- Tes kesetaraan (golden test): render PHP vs JS untuk order yang sama harus menghasilkan byte identik.
- Mode printer "PC/laptop" (iframe `/receipt/.../print`) tidak didukung offline. Tampilkan pesan.

### c10. Keamanan
- Data lokal dipisah per `business_id/outlet_id/user_id`. Saat logout/Ganti Kasir: antrean **tidak** dihapus (uang sudah diterima), tapi tidak bisa dibuka kasir lain dari bisnis lain. Ganti bisnis di perangkat yang sama memakai namespace baru.
- Sesi kedaluwarsa saat offline: kasir tetap boleh berjualan sampai batas waktu offline. Saat online lagi dan sesi habis, sinkron **ditahan** sampai login PIN, lalu antrean dikirim atas nama user yang tercatat di order (server memvalidasi bahwa user itu milik bisnis yang sama).
- CSRF: ambil token baru (`GET /pos/csrf`) sebelum sinkron. Respons 419 → refresh token lalu ulangi.
- Tidak ada data pelanggan, PIN, atau token di IndexedDB. Katalog berisi harga, tidak berisi HPP (`cost_price` dihapus dari snapshot).
- Server tidak percaya `paid_amount` < total, `client_uuid` milik bisnis lain, atau `catalog_version` yang tidak dikenal.

### c11. Batas
- Maks **12 jam** sejak terakhir online, atau maks **300 transaksi** di antrean. Lewat batas itu, kasir diblokir dengan pesan "Sambungkan internet untuk sinkron".
- Snapshot katalog lebih tua dari 24 jam: peringatan. Lebih tua dari 72 jam: mode offline ditolak.
- Kedua angka dibuat bisa diatur per bisnis di settings (default di atas). **[ASUMSI]** angka default ini perlu disetujui pemilik.

---

## d. Dampak

| Area | Dampak |
|---|---|
| **Stok** | Pemotongan stok tertunda sampai sinkron, sehingga laporan stok real-time kurang selama offline. Stok bisa minus (lihat c7). |
| **Laporan** | Pakai `ordered_at` (waktu perangkat), jadi penjualan masuk ke tanggal dan jam yang benar. Laporan hari ini bisa berubah setelah sinkron. Tambah filter/kolom "Offline" dan daftar `needs_review`. |
| **Shift** | Ringkasan dan tutup shift harus memperingatkan bila perangkat masih punya antrean ("3 transaksi belum tersinkron"). Tutup shift dari perangkat yang antreannya belum kosong diblokir. |
| **Multi-perangkat** | Antar perangkat tidak bisa saling melihat saat offline. Nomor offline unik per perangkat. Stok bersama bisa "terjual dobel" lalu minus; diterima di v1. Hold order tidak tersedia offline. |
| **Dapur/KDS, QR order, Telegram, WA** | Order offline muncul di dapur atau notifikasi **setelah sinkron**. Struk dapur offline belum termasuk v1. |
| **Saldo merchant / QRIS** | Tidak terpengaruh (non-tunai ditolak offline). |
| **Situs klien `pos.conweb.id`** | Tidak tersentuh: semua perubahan hanya di kode `yokapos_app`, deploy hanya ke yokapos.com. Kode ini dipakai bersama klien. Kalau klien ikut deploy kelak, keputusan ada di pemilik. |

---

## e. Rencana tes & risiko

### Tes otomatis
- PHPUnit: `/pos/sync` idempotent (kirim 2x = 1 order), batch campuran, semua konflik c7, isolasi bisnis, batas umur, CSRF/419, sesi habis.
- PHPUnit: `/pos/order` dengan `client_uuid` dobel = order yang sama (menutup bug dobel online).
- Golden test ESC/POS PHP vs JS.
- JS (Vitest): outbox, nomor offline, batas, state machine sinkron.

### Tes di tablet ini (adb + CDP devtools)
1. Online: buka `/pos`, SW terdaftar, cache terisi.
2. `svc wifi/data disable`, lalu reload: kasir tetap tampil dari cache.
3. 3 transaksi tunai kecil (catatan "TES OFFLINE"), struk keluar ber-label OFFLINE.
4. Kill app (`am force-stop`), buka lagi offline: antrean masih 3.
5. QRIS ditolak dengan pesan.
6. `enable`, lalu dalam 30 detik antrean jadi 0, order `ORD-...` muncul di back office dengan `offline_number`.
7. Putus jaringan **di tengah** sinkron: tidak ada dobel.
8. Ubah harga produk di back office sebelum sinkron: order `needs_review`.
9. Tutup shift sebelum sinkron: alur selisih kas.
10. Uji di Chrome biasa (bukan app) di tablet yang sama.

### Risiko
| Risiko | Mitigasi |
|---|---|
| WebView/aplikasi menghapus storage (clear data, uninstall, penyimpanan penuh) = **uang tercatat hilang** | `storage.persist()`, peringatan "jangan hapus data aplikasi", batas 12 jam/300 trx, sinkron agresif. Nanti: cadangan antrean ke native (`PosApp`). |
| SW menyajikan halaman basi setelah deploy | network-first + versi SW mengikuti build, snapshot ditampilkan dengan tanggalnya |
| Jam perangkat salah | Kirim `created_at_device` + offset terhadap jam server terakhir, lalu server mengoreksi. |
| Kecurangan kasir (transaksi offline lalu dihapus dari antrean) | Antrean tidak bisa dihapus dari UI. Hitungan "transaksi offline dibuat" per perangkat dilaporkan saat sinkron, selisih ditandai. |
| `onReceivedError` native menimpa fallback SW | Dites di tahap 1. Kalau terjadi, ubah APK kecil. |
| Kode dipakai bersama klien | Fitur di balik flag `FEATURE_POS_OFFLINE` (env), default mati. |

---

## f. Rencana implementasi bertahap

| Tahap | Isi | Perkiraan |
|---|---|---|
| **0. Idempotency online** | Migrasi `client_uuid` + unique index, `/pos/order` menerima & menghormati `client_uuid`, klien kirim UUID. Tes. Bisa dirilis sendiri: menutup bug dobel. | S · ~0,5–1 hari |
| **1. Shell offline** | `sw.js`, precache aset + `/pos`, flag fitur, indikator online/offline, keranjang tahan reload, ganti `alert()`. Uji di tablet (termasuk isu `onReceivedError`). | M · 1–2 hari |
| **2. Snapshot katalog** | `GET /pos/catalog.json` + hash versi, simpan di IndexedDB, halaman memakai snapshot saat offline, blokir non-tunai. | M · 1–1,5 hari |
| **3. Outbox + checkout offline** | IndexedDB outbox, nomor `OFF-...`, alur bayar tunai offline, panel antrean, batas 12 jam/300. | M–L · 2–3 hari |
| **4. Sinkron server** | `POST /pos/sync`, migrasi kolom offline, aturan konflik c7, CSRF/sesi, Background Sync. Tes PHPUnit lengkap. | L · 2–3 hari |
| **5. Struk offline** | Port `EscPosReceipt` ke JS + golden test, cetak via `rawbt:`, label OFFLINE, cetak ulang dari antrean. | M · 1–2 hari |
| **6. Laporan & shift** | Filter/label offline, daftar `needs_review`, peringatan & blok tutup shift, selisih kas. | M · 1–2 hari |
| **7. Uji lapangan** | Skenario e di tablet, uji 1 outlet tes 1 minggu, lalu nyalakan flag untuk bisnis terpilih. | 2–3 hari + masa uji |

Total kasar: **~11–17 hari kerja**. Tahap 0 bisa dikerjakan dan di-deploy duluan.

Tidak perlu rilis APK untuk v1, kecuali tes tahap 1 membuktikan `onReceivedError` mengganggu. **[ASUMSI]**

---

## Order uji
Tidak ada. Satu-satunya percobaan (Kopi Tubruk Rp 12.000, tunai, saat offline) **gagal tersimpan** dan tidak masuk server. Tidak ada yang perlu di-void.
