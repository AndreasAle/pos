# Deployment Produksi

Checklist ini wajib dijalani **sebelum** uang asli merchant masuk ke sistem.
Urutannya sengaja: nomor 1–4 tidak boleh dilewat.

---

## 1. Environment

Salin `.env.example` ke `.env`, lalu:

```bash
php artisan key:generate
```

Wajib diubah dari default:

| Key | Nilai produksi | Kenapa |
| --- | --- | --- |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | **Kritis.** Kalau `true`, halaman error membocorkan seluruh isi `.env` — termasuk password database dan server key Midtrans — ke siapa pun yang memicu error. |
| `APP_URL` | `https://domain-kamu.com` | Dipakai untuk link struk & callback |
| `SESSION_SECURE_COOKIE` | `true` | Cookie sesi hanya dikirim lewat HTTPS |
| `MIDTRANS_IS_PRODUCTION` | `true` | Kalau lupa, transaksi asli masuk ke sandbox |

Verifikasi sebelum lanjut:

```bash
php artisan about --only=environment
```

## 2. Database

```bash
php artisan migrate --force
php artisan db:seed --class=SubscriptionPlanSeeder
```

`--force` diperlukan karena di `production` Laravel minta konfirmasi interaktif.

Jangan jalankan `migrate:fresh` di produksi — itu menghapus semua data.

## 3. Optimasi & aset

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Setiap kali `.env` berubah, ulangi `php artisan config:cache` — kalau tidak, perubahannya tidak terbaca.

## 4. Queue worker — WAJIB

Email alert stok, alert shift, rekap harian, dan struk WhatsApp semuanya masuk antrean
(`QUEUE_CONNECTION=database`). **Tanpa worker yang jalan, semua itu diam-diam tidak pernah terkirim.**

`/etc/supervisor/conf.d/pos-worker.conf`:

```ini
[program:pos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/pos/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/pos/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start pos-worker:*
```

Setelah tiap deploy, worker harus di-restart supaya memuat kode baru:

```bash
php artisan queue:restart
```

## 5. Scheduler (cron)

`routes/console.php` menjadwalkan alert stok menipis, alert shift belum ditutup, dan
rekap harian jam 21:00. Semuanya butuh satu baris cron:

```bash
* * * * * cd /var/www/pos && php artisan schedule:run >> /dev/null 2>&1
```

Cek jadwal terbaca:

```bash
php artisan schedule:list
```

## 6. Backup otomatis

Minimal: dump harian + simpan 14 hari.

```bash
#!/bin/bash
# /usr/local/bin/pos-backup.sh
set -euo pipefail
STAMP=$(date +%F-%H%M)
DEST=/var/backups/pos
mkdir -p "$DEST"
mysqldump --single-transaction --quick fnb_pos | gzip > "$DEST/db-$STAMP.sql.gz"
tar czf "$DEST/uploads-$STAMP.tar.gz" -C /var/www/pos/storage/app/public .
find "$DEST" -type f -mtime +14 -delete
```

```bash
chmod +x /usr/local/bin/pos-backup.sh
# cron: tiap hari jam 02:00
0 2 * * * /usr/local/bin/pos-backup.sh
```

`--single-transaction` bikin dump konsisten tanpa mengunci tabel — kasir tetap bisa jualan saat backup jalan.

**Backup yang belum pernah dites bukan backup.** Sekali saja, restore ke database kosong dan pastikan datanya utuh.

## 7. Webhook Midtrans

Daftarkan URL ini di dashboard Midtrans (Settings > Configuration):

```
https://domain-kamu.com/midtrans/callback
```

Endpoint ini dikecualikan dari CSRF (lihat `bootstrap/app.php`) dan memverifikasi
signature SHA512 — request tanpa signature yang sah ditolak dengan 400.

Uji dengan tombol "Send test notification" di dashboard Midtrans, lalu cek
`storage/logs/laravel.log` — setiap callback dicatat.

## 8. Permission file

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## 9. Monitoring

- **Uptime** — UptimeRobot (gratis) ping `https://domain-kamu.com/login` tiap 5 menit
- **Disk** — log & backup bisa membengkak; pasang alert di 80%
- **Log** — pantau `storage/logs/laravel.log`. Cari `Balance credit failed` (saldo merchant gagal masuk) dan `Midtrans invalid signature`

---

## Urutan deploy rutin

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

---

## Sebelum serah terima ke merchant pertama

- [ ] `APP_DEBUG=false` sudah diverifikasi lewat `php artisan about`
- [ ] HTTPS aktif, HTTP redirect ke HTTPS
- [ ] Queue worker jalan (`supervisorctl status`)
- [ ] Cron scheduler jalan (cek log setelah 1 menit)
- [ ] Backup pertama sudah dibuat **dan sudah dites restore**
- [ ] Webhook Midtrans sudah dites dari dashboard
- [ ] Ganti semua password akun demo, atau hapus akun demo-nya
- [ ] `php artisan test` hijau di server
