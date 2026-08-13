# Deploy ke Hostinger (shared hosting) via Git

Panduan khusus untuk akun `u598194357@id-dci-web1987`.

**Struktur yang dipakai:** kode aplikasi di `~/pos_app` (di luar `public_html`),
web root hanya berisi isi folder `public/`. Dengan begini `.env`, database,
dan `vendor/` **tidak bisa diakses lewat browser sama sekali**.

```
~/pos_app/                          ← seluruh source code (hasil git clone)
~/domains/conweb.id/public_html/pos ← hanya isi public/ (index.php, build/, dll)
```

---

## ⚠️ Baca ini dulu: subdomain vs subfolder

Laravel di **subfolder** (`conweb.id/pos`) menimbulkan masalah path yang
merepotkan — aset Vite mengarah ke `/build/...` yang jadi `conweb.id/build`,
bukan `conweb.id/pos/build`. Bisa diakali dengan `ASSET_URL`, tapi cookie
sesi dan storage link masih sering bikin masalah.

**Sangat disarankan pakai subdomain:** `pos.conweb.id`

Di hPanel → **Domains → Subdomains** → buat `pos`, arahkan document root ke
`~/domains/conweb.id/public_html/pos`. Gratis, 2 menit, dan menghilangkan
seluruh kelas bug ini. Lebih meyakinkan juga waktu ditunjukkan ke klien.

Panduan di bawah memakai subdomain. Kalau tetap mau subfolder, lihat catatan
di bagian akhir.

---

## 1. Siapkan database (hPanel, bukan SSH)

**Databases → Management → Create database**

Catat ketiganya, nanti dipakai di `.env`:

| | contoh |
| --- | --- |
| Nama database | `u598194357_pos` |
| Username | `u598194357_pos` |
| Password | (buat yang kuat, simpan) |

## 2. Pastikan versi PHP 8.2+

**Advanced → PHP Configuration** → pilih **PHP 8.2** atau lebih baru.
Aplikasi ini butuh minimal 8.2 dan tidak akan jalan di bawah itu.

Cek dari SSH:

```bash
php -v
```

## 3. Clone repo

```bash
cd ~
git clone https://github.com/AndreasAle/pos.git pos_app
cd pos_app
```

## 4. Install dependency PHP

```bash
composer install --no-dev --optimize-autoloader
```

Kalau `composer` tidak dikenali, coba `php composer.phar install --no-dev --optimize-autoloader`
atau cari path-nya lewat `which composer`.

> Node/npm **tidak diperlukan** — folder `public/build` sudah ikut di repo,
> jadi CSS dan JS sudah dalam bentuk jadi.

## 5. Buat file .env

```bash
cp .env.example .env
nano .env
```

Isi yang wajib diubah:

```ini
APP_NAME="FNB POS System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pos.conweb.id

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u598194357_pos
DB_USERNAME=u598194357_pos
DB_PASSWORD=password-database-kamu

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
```

> `APP_DEBUG=false` itu **wajib**. Kalau `true`, halaman error akan
> menampilkan seluruh isi `.env` — termasuk password database dan server key
> Midtrans — ke siapa pun yang memicu error.

Simpan dengan `Ctrl+O`, `Enter`, lalu keluar `Ctrl+X`.

## 6. Generate key & migrasi

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=SubscriptionPlanSeeder --force
```

`--force` diperlukan karena di mode production Laravel minta konfirmasi interaktif.

> Jangan pernah menjalankan `migrate:fresh` di server ini — perintah itu
> menghapus seluruh data.

## 7. Pindahkan isi public/ ke web root

```bash
WEBROOT=~/domains/conweb.id/public_html/pos

rm -f $WEBROOT/default.php
cp -r ~/pos_app/public/. $WEBROOT/
```

> Perintah `cp` utuh ini **hanya untuk pemasangan pertama**, karena `index.php`
> di web root belum disesuaikan. Setelah langkah 8, jangan pernah mengulanginya —
> `index.php` hasil edit akan tertimpa dan seluruh situs mati dengan HTTP 500.
> Lihat bagian "Update kode berikutnya" untuk perintah salin yang aman.

## 8. Arahkan index.php ke aplikasi

Jangan edit manual di nano — salah satu kurung tertinggal saja, seluruh situs
mati dengan parse error. Tulis ulang file-nya utuh dengan satu perintah:

```bash
cat > ~/domains/conweb.id/public_html/pos/index.php <<'EOF'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = '/home/u598194357/pos_app/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require '/home/u598194357/pos_app/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once '/home/u598194357/pos_app/bootstrap/app.php';

$app->handleRequest(Request::capture());
EOF
```

Tanda kutip pada `<<'EOF'` wajib ada — tanpa itu shell akan mengganti
`$maintenance` dan `$app` menjadi string kosong.

Verifikasi:

```bash
php -l ~/domains/conweb.id/public_html/pos/index.php
```

Harus muncul `No syntax errors detected`.

## 9. Symlink storage (untuk gambar produk & logo)

```bash
ln -s /home/u598194357/pos_app/storage/app/public \
      ~/domains/conweb.id/public_html/pos/storage
```

## 10. Permission

```bash
chmod -R 775 ~/pos_app/storage ~/pos_app/bootstrap/cache
```

## 11. Optimasi

```bash
cd ~/pos_app
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Setiap kali `.env` diubah, **wajib** jalankan `php artisan config:cache` lagi.
> Kalau tidak, perubahannya tidak akan terbaca.

## 12. Cron — scheduler & queue

Shared hosting tidak punya Supervisor, jadi queue worker dijalankan lewat cron.

**hPanel → Advanced → Cron Jobs**, buat dua entri, keduanya tiap menit (`* * * * *`):

```bash
cd /home/u598194357/pos_app && php artisan schedule:run >> /dev/null 2>&1
```

```bash
cd /home/u598194357/pos_app && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

Yang pertama menjalankan alert stok menipis, alert shift belum ditutup, dan
rekap harian. Yang kedua mengirim email dan struk WhatsApp yang mengantre —
`--stop-when-empty` membuatnya berhenti sendiri kalau antrean kosong, dan
`--max-time=55` mencegah dua proses saling tumpang tindih.

## 13. Buka aplikasinya

**https://pos.conweb.id**

Login pakai akun yang kamu buat lewat halaman registrasi. Database masih
kosong (hanya berisi paket langganan), jadi bisnis pertama dibuat dari
proses pendaftaran.

---

## Update kode berikutnya

```bash
cd ~/pos_app
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
cp -r ~/pos_app/public/build/. ~/domains/conweb.id/public_html/pos/build/
cp -r ~/pos_app/public/images/. ~/domains/conweb.id/public_html/pos/images/
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

Kedua baris `cp` diperlukan karena aset build dan foto menu berada di
`pos_app/public`, sementara yang dilayani browser ada di web root.

> ⚠️ **Salin `build/` dan `images/` saja — jangan `public/.` seluruhnya.**
> Menyalin seluruh isi `public/` menimpa `index.php` di web root dengan versi
> bawaan repo, yang mencari `vendor/autoload.php` di folder yang salah. PHP mati
> sebelum Laravel sempat jalan, dan seluruh situs balas HTTP 500 polos tanpa
> pesan apa pun serta tanpa jejak di `laravel.log`. Kalau terlanjur, tulis ulang
> `index.php` dengan perintah di langkah 8.
>
> `npm` tidak perlu dijalankan di server — aset sudah dalam bentuk jadi di repo.

---

## Kalau pakai subfolder (conweb.id/pos)

Wajib tambahkan **dua-duanya** di `.env`:

```ini
APP_URL=https://conweb.id/pos
ASSET_URL=https://conweb.id/pos
```

lalu:

```bash
cd ~/pos_app && php artisan config:cache
```

Tanpa `ASSET_URL`, halaman tetap terbuka tetapi **tampil tanpa CSS sama
sekali** — Laravel mencari aset di `conweb.id/build` sementara file-nya ada di
`conweb.id/pos/build`. Ini penyebab paling sering orang mengira deploy-nya
gagal, padahal hanya salah path aset.

URL aplikasi jadi `https://conweb.id/pos` (bukan `pos.conweb.id`), dan langkah
symlink storage tetap sama.

---

## Checklist sebelum dipakai klien

- [ ] `APP_DEBUG=false` — cek dengan `php artisan about | grep -i debug`
- [ ] HTTPS aktif (hPanel → SSL, aktifkan juga Force HTTPS)
- [ ] Buka `https://pos.conweb.id/.env` di browser — **harus 404**. Kalau isinya terbaca, hentikan dan perbaiki struktur folder.
- [ ] Kedua cron job sudah terdaftar dan tercatat berjalan
- [ ] Backup database pertama sudah dibuat (hPanel → Backups)
- [ ] Registrasi akun owner berhasil dan bisa masuk dashboard
