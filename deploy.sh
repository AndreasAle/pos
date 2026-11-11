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

# Kedua domain melayani aplikasi Laravel yang sama. WEB_ROOT tetap didukung
# untuk deploy satu target saat troubleshooting; WEB_ROOTS menerima daftar
# path yang dipisahkan spasi. Tanpa override, domain utama dan domain lama
# diperbarui bersama-sama.
if [[ -n "${WEB_ROOTS:-}" ]]; then
    read -r -a WEB_ROOT_LIST <<< "$WEB_ROOTS"
elif [[ -n "${WEB_ROOT:-}" ]]; then
    WEB_ROOT_LIST=("$WEB_ROOT")
else
    WEB_ROOT_LIST=(
        "/home/u598194357/domains/yokapos.com/public_html"
        "/home/u598194357/domains/conweb.id/public_html/pos"
    )
fi

cd "$APP_DIR"

echo "==> Mengambil kode terbaru"
git pull --ff-only origin main

echo "==> Dependency PHP"
composer install --no-dev --optimize-autoloader

# Migrasi membaca config. Cache config lama dari deploy sebelumnya harus
# dibuang dulu, kalau tidak migrasi melihat setelan lama (mis. zona waktu).
# Selama migrasi situs ditutup sebentar, supaya tidak ada transaksi yang
# tercatat di tengah perubahan data. Gagal atau tidak, situs dibuka lagi.
echo "==> Migrasi database"
php artisan down --retry=5
trap 'php artisan up' EXIT
php artisan config:clear
php artisan migrate --force
php artisan up
trap - EXIT

# Hanya build/ dan images/ — JANGAN salin public/ seluruhnya, karena index.php
# di web root sudah disesuaikan untuk menunjuk aplikasi di luar public_html.
echo "==> Menyalin aset ke semua domain"
for WEB_ROOT_ITEM in "${WEB_ROOT_LIST[@]}"; do
    if [[ ! -d "$WEB_ROOT_ITEM" ]]; then
        echo "    GAGAL: document root tidak ditemukan: $WEB_ROOT_ITEM" >&2
        echo "    Tambahkan domain di hPanel dan siapkan index.php sebelum deploy." >&2
        exit 1
    fi

    if [[ ! -f "$WEB_ROOT_ITEM/index.php" ]]; then
        echo "    GAGAL: index.php tidak ditemukan di $WEB_ROOT_ITEM" >&2
        exit 1
    fi

    # Kedua domain harus bootstrap source yang sama. Kalau tidak, halaman bisa
    # memakai view/controller lama walaupun CSS baru sudah disalin.
    if ! grep -q "$APP_DIR/bootstrap/app.php" "$WEB_ROOT_ITEM/index.php"; then
        echo "    GAGAL: $WEB_ROOT_ITEM/index.php belum menunjuk ke $APP_DIR" >&2
        echo "    Ikuti bagian 'Domain utama yokapos.com' di DEPLOY-HOSTINGER.md." >&2
        exit 1
    fi

    mkdir -p "$WEB_ROOT_ITEM/build" "$WEB_ROOT_ITEM/images"
    cp -r "$APP_DIR/public/build/." "$WEB_ROOT_ITEM/build/"
    cp -r "$APP_DIR/public/images/." "$WEB_ROOT_ITEM/images/"

    if [[ ! -e "$WEB_ROOT_ITEM/storage" ]]; then
        ln -s "$APP_DIR/storage/app/public" "$WEB_ROOT_ITEM/storage"
    fi

    echo "    OK: aset disalin ke $WEB_ROOT_ITEM"
done

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

for WEB_ROOT_ITEM in "${WEB_ROOT_LIST[@]}"; do
    if [[ -f "$WEB_ROOT_ITEM/build/$CSS_FILE" ]]; then
        echo "    OK: $CSS_FILE ada di $WEB_ROOT_ITEM"
    else
        echo "    GAGAL: $CSS_FILE tidak ditemukan di $WEB_ROOT_ITEM — domain akan tampil tanpa CSS" >&2
        exit 1
    fi
done

echo
echo "Selesai. Periksa:"
echo "  - https://yokapos.com"
echo "  - https://pos.conweb.id"
