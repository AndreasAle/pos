#!/bin/bash
#
# Deploy ke shared hosting Hostinger.
#
#   cd ~/pos_app && ./deploy.sh
#
# Dibuat karena menjalankan langkahnya satu per satu terlalu mudah salah:
# lupa menyalin build/ membuat seluruh situs kehilangan CSS, lupa migrate
# membuat kolom baru hilang, dan menyalin public/ seluruhnya justru menimpa
# index.php sehingga situs mati total.

set -euo pipefail

APP_DIR="${APP_DIR:-/home/u598194357/pos_app}"
WEB_ROOT="${WEB_ROOT:-/home/u598194357/domains/conweb.id/public_html/pos}"

cd "$APP_DIR"

echo "==> Mengambil kode terbaru"
git pull

echo "==> Dependency PHP"
composer install --no-dev --optimize-autoloader

echo "==> Migrasi database"
php artisan migrate --force

# Hanya build/ dan images/ — JANGAN salin public/ seluruhnya, karena index.php
# di web root sudah disesuaikan untuk menunjuk aplikasi di luar public_html.
echo "==> Menyalin aset ke web root"
mkdir -p "$WEB_ROOT/build" "$WEB_ROOT/images"
cp -r "$APP_DIR/public/build/." "$WEB_ROOT/build/"
cp -r "$APP_DIR/public/images/." "$WEB_ROOT/images/"

echo "==> Membangun ulang cache"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Menyegarkan queue worker"
php artisan queue:restart

# Nama file aset berubah setiap kali CSS atau JS diubah. Kalau manifest menunjuk
# file yang tidak ada di web root, halaman tampil tanpa CSS sama sekali —
# gejalanya membingungkan karena tidak ada error di mana pun.
echo "==> Memeriksa aset"
CSS_FILE=$(php -r '$m = json_decode(file_get_contents("public/build/manifest.json"), true); echo $m["resources/css/app.css"]["file"];')

if [ -f "$WEB_ROOT/$CSS_FILE" ]; then
    echo "    OK: $CSS_FILE ada di web root"
else
    echo "    GAGAL: $CSS_FILE tidak ditemukan di web root — situs akan tampil tanpa CSS" >&2
    exit 1
fi

echo
echo "Selesai. Buka https://pos.conweb.id"
