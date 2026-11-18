# Yoka POS

Yoka POS adalah platform kasir (Point of Sale) berbasis web untuk usaha F&B dan ritel: kafe, restoran, bakery, dan toko. Satu sistem untuk transaksi kasir, stok bahan baku, shift, keuangan, laporan, integrasi layanan pesan-antar, dan asisten AI.

| | |
|---|---|
| Situs produk | [yokapos.com](https://yokapos.com) |
| Pengembang | PT Conwebid Indonesia Digital |
| Status | Produksi |
| Lisensi | Proprietary, seluruh hak cipta dilindungi |

---

## Daftar isi

1. [Fitur utama](#fitur-utama)
2. [Arsitektur dan teknologi](#arsitektur-dan-teknologi)
3. [Persyaratan sistem](#persyaratan-sistem)
4. [Instalasi lokal](#instalasi-lokal)
5. [Konfigurasi](#konfigurasi)
6. [Menjalankan pengujian](#menjalankan-pengujian)
7. [Deployment](#deployment)
8. [Backup dan pemulihan](#backup-dan-pemulihan)
9. [Keamanan](#keamanan)
10. [Dokumentasi lanjutan](#dokumentasi-lanjutan)
11. [Kontribusi](#kontribusi)
12. [Lisensi](#lisensi)

---

## Fitur utama

### Kasir
- Transaksi dengan varian, add-on, bundle, dan promosi (termasuk Beli X Gratis Y).
- Harga, diskon, pajak, dan service charge dihitung di server; harga dari perangkat tidak dipercaya.
- Hold order dengan nama pelanggan dan nomor meja.
- Pembayaran tunai, QRIS, transfer, e-wallet, dan split payment.
- Cetak struk via browser, Bluetooth/USB, dan RawBT (Android).
- Login kasir dengan PIN pada tablet yang sudah dipasangkan ke outlet (`/kasir`).
- Buka/tutup shift dengan rekap selisih kas.

### Pesanan dan meja
- QR order per meja (self-order) dengan persetujuan kasir.
- Layar dapur (kitchen display).
- Integrasi GoFood (GoBiz) dan ShopeeFood.

### Operasional dan stok
- Produk, kategori, bundle, promosi, dan multi-outlet.
- Resep per produk dengan pengurangan stok bahan baku otomatis dan riwayat pergerakan stok.
- Pelanggan dan poin loyalitas.

### Keuangan dan laporan
- Biaya, pembelian ke supplier, hutang, dan piutang.
- Laporan penjualan, shift, laba per menu, serta laporan keuangan.
- Ekspor Excel/CSV/PDF dan log aktivitas.

### Kecerdasan bisnis (AI)
- **Tanya Yoka**: asisten yang menjawab pertanyaan bisnis dari data toko sendiri.
- Insight otomatis dan usulan tindakan yang wajib disetujui pemilik sebelum dijalankan.
- Bot Telegram untuk ringkasan harian, notifikasi transaksi, dan tanya-jawab.

### Platform (internal Yoka)
- Panel admin platform dengan login terpisah dan verifikasi dua langkah.
- Tim AI Yoka (AI Workforce) dan Telegram Virtual Office, dengan aturan persetujuan manusia untuk tindakan sensitif.
- Manajemen langganan dan pembayaran (Midtrans, BayarPro).

### Aplikasi iOS
- Shell iOS berbasis Capacitor (`capacitor.config.ts`, folder `ios/`).

---

## Arsitektur dan teknologi

| Lapisan | Teknologi |
|---|---|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Blade, Alpine.js, Tailwind CSS 4 |
| Build aset | Vite 6 (hasil build di-commit di `public/build`) |
| Database | MySQL 8 / MariaDB 10.4+ |
| Antrean dan jadwal | Laravel Queue (database) dan Scheduler via cron |
| Pembayaran | Midtrans, BayarPro |
| Pesan | Telegram Bot API, Fonnte (WhatsApp), SMTP |
| AI | OpenAI API |
| Hosting | Hostinger (shared hosting), deploy lewat GitHub Actions |

**Multi-tenant.** Semua bisnis berbagi satu database. Setiap data bisnis memiliki `business_id`, dan setiap query wajib dibatasi ke bisnis milik pengguna yang login. Hasil audit isolasi ada di [`docs/security/03-multi-tenant-isolation-audit.md`](docs/security/03-multi-tenant-isolation-audit.md).

---

## Persyaratan sistem

- PHP 8.2 atau lebih baru, dengan ekstensi `pdo_mysql`, `gd`, `zip`, `intl`, `bcmath`, `sodium`
- Composer 2
- Node.js 20 dan npm
- MySQL 8 atau MariaDB 10.4+ (`explicit_defaults_for_timestamp=ON`)
- Klien `mysqldump` dan `mysql`, untuk fitur backup

---

## Instalasi lokal

```bash
git clone https://github.com/AndreasAle/pos.git
cd pos

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Atur koneksi database di `.env`, lalu:

```bash
php artisan migrate
```

Jalankan server, antrean, log, dan Vite sekaligus:

```bash
composer run dev
```

Aplikasi tersedia di `http://localhost:8000`.

### Data demo

Gunakan database terpisah, lalu isi data contoh:

```bash
php artisan db:seed --class=SubscriptionPlanSeeder
php artisan db:seed --class=DemoBusinessSeeder
```

Jangan menjalankan seeder pada database produksi.

---

## Konfigurasi

Semua konfigurasi dibaca dari `.env`. Daftar lengkap ada di [`.env.example`](.env.example).

| Kelompok | Variabel utama | Keterangan |
|---|---|---|
| Aplikasi | `APP_ENV`, `APP_DEBUG`, `APP_URL` | Produksi wajib `APP_DEBUG=false` |
| Database | `DB_*` | |
| Email | `MAIL_*` | Verifikasi email, reset password, kode 2FA platform |
| Login Google | `GOOGLE_*` | Laravel Socialite |
| Pembayaran | `MIDTRANS_*`, `BAYARPRO_*` | |
| AI | `YOKA_AI_API_KEY` | Opsional; tanpa key, parser lokal tetap berjalan |
| Telegram | `TELEGRAM_*` | Bot merchant dan Telegram Virtual Office |
| WhatsApp | `YOKA_FONNTE_TOKEN` | |
| Backup | `YOKA_BACKUP_*` | Lihat [Backup dan pemulihan](#backup-dan-pemulihan) |

Token dan secret hanya disimpan di `.env` server dan tidak boleh di-commit.

Jadwal otomatis memerlukan cron tiap menit:

```bash
* * * * * /usr/bin/php /path/ke/aplikasi/artisan schedule:run
```

---

## Menjalankan pengujian

Pengujian memakai MySQL/MariaDB dengan database khusus `fnb_pos_test` (lihat `phpunit.xml`). Database ini dikosongkan setiap kali tes berjalan, jadi jangan pernah mengarahkannya ke database berisi data.

```bash
php artisan test
```

Tes keamanan saja:

```bash
php artisan test tests/Feature/Security
```

Pemeriksaan dependensi:

```bash
composer audit
npm audit --audit-level=critical
```

GitHub Actions menjalankan audit dependensi, pemindaian rahasia (gitleaks), dan tes keamanan pada setiap pull request (`.github/workflows/security.yml`).

---

## Deployment

Deploy produksi berjalan otomatis saat ada push ke `main` (`.github/workflows/deploy-production.yml`) dan menjalankan `deploy.sh` di server.

Sebelum deploy:

1. Backup database produksi.
2. Jika ada perubahan tampilan, jalankan `npm run build` lalu commit hasilnya.
3. Pastikan semua tes lulus.

`deploy.sh` hanya menyasar yokapos.com, dan proses deploy dihentikan jika `APP_DEBUG` masih aktif. Aturan lengkap tentang server, termasuk larangan menyentuh situs klien, ada di [`AGENTS.md`](AGENTS.md).

---

## Backup dan pemulihan

Backup database dienkripsi (libsodium), diverifikasi setelah dibuat, dan dihapus otomatis setelah masa simpan berakhir.

```bash
# Buat backup
php artisan yoka:backup

# Verifikasi file backup
php artisan yoka:backup --verify-only=/path/file.sql.gz.enc

# Uji pemulihan ke database sementara (nama wajib mengandung "restoretest")
php artisan yoka:backup-restore-test /path/file.sql.gz.enc nama_restoretest --drop
```

Backup terjadwal setiap hari pukul 02.30 bila `YOKA_BACKUP_ENABLED=true`. Simpan salinan `YOKA_BACKUP_KEY` di luar server, karena tanpa key backup tidak dapat dipulihkan. Prosedur lengkap ada di [`docs/security/04-backup-disaster-recovery.md`](docs/security/04-backup-disaster-recovery.md).

---

## Keamanan

- Repositori ini publik. Jangan commit `.env`, password, PIN, token, atau data pelanggan.
- Laporkan celah keamanan secara pribadi kepada tim pengembang, bukan lewat issue publik.
- Ringkasan kondisi keamanan: [`docs/security/executive-security-report.md`](docs/security/executive-security-report.md)
- Rencana penanganan insiden: [`docs/security/05-incident-response-plan.md`](docs/security/05-incident-response-plan.md)

Dokumen ISO/IEC 27001 dan UU PDP di repositori ini adalah penilaian internal, bukan sertifikasi.

---

## Dokumentasi lanjutan

| Topik | Dokumen |
|---|---|
| Keamanan dan audit | [`docs/security/`](docs/security/) |
| ISMS, Statement of Applicability, privasi (UU PDP) | [`docs/compliance/`](docs/compliance/) |
| Modul keuangan | [`docs/yoka-finance-modules.md`](docs/yoka-finance-modules.md) |
| Laporan penjualan | [`docs/yoka-sales-reporting-release.md`](docs/yoka-sales-reporting-release.md) |
| Yoka Intelligence | [`docs/yoka-intelligence-operations.md`](docs/yoka-intelligence-operations.md) |
| AI Workforce | [`docs/yoka-ai-workforce-architecture.md`](docs/yoka-ai-workforce-architecture.md) |
| Telegram Virtual Office | [`docs/yoka-telegram-office-setup.md`](docs/yoka-telegram-office-setup.md) |
| Aturan untuk agen AI | [`AGENTS.md`](AGENTS.md) |

---

## Kontribusi

1. Buat branch dari `main`.
2. Sertakan tes untuk setiap perubahan logika, dan tes regresi untuk setiap perbaikan keamanan.
3. Pastikan `php artisan test` dan `composer audit` lulus.
4. Ajukan pull request; deploy terjadi setelah merge ke `main`.

Panduan desain admin: kartu putih `rounded-xl`, hijau `#16A34A`, navy `#0B1B3F`, komponen `x-kpi`, `x-panel`, `x-pill`, `x-empty`, tanpa emoji.

---

## Lisensi

Hak cipta © PT Conwebid Indonesia Digital. Seluruh hak cipta dilindungi. Perangkat lunak ini tidak boleh disalin, didistribusikan, atau digunakan tanpa izin tertulis.
