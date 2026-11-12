#!/bin/bash
#
# Deploy ke shared hosting Hostinger.
#
#   cd ~/yokapos_app && ./deploy.sh
#
# Dibuat karena menjalankan langkahnya satu per satu terlalu mudah salah:
# lupa menyalin build/ membuat seluruh situs kehilangan CSS, lupa migrate
# membuat kolom baru hilang, dan menyalin public/ seluruhnya justru menimpa
# index.php sehingga situs mati total.

set -euo pipefail

# Default HANYA situs Yoka (yokapos.com). Situs klien lama (pos.conweb.id / ~/pos_app)
# sengaja tidak termasuk: salah jalan di sana bisa mengubah toko yang sedang beroperasi.
APP_DIR="${APP_DIR:-/home/u598194357/yokapos_app}"

# WEB_ROOT mendukung satu target; WEB_ROOTS menerima daftar path dipisahkan spasi.
# Tanpa override, hanya yokapos.com yang diperbarui.
if [[ -n "${WEB_ROOTS:-}" ]]; then
    read -r -a WEB_ROOT_LIST <<< "$WEB_ROOTS"
elif [[ -n "${WEB_ROOT:-}" ]]; then
    WEB_ROOT_LIST=("$WEB_ROOT")
else
    WEB_ROOT_LIST=(
        "/home/u598194357/domains/yokapos.com/public_html"
    )
fi

# Pengaman: tolak bila menyasar aplikasi atau domain klien, kecuali sengaja diizinkan.
# Memakai skrip ini di ~/pos_app juga menjalankan migrasi pada database klien.
if [[ "${ALLOW_CLIENT_SITE:-}" != "yes" ]]; then
    if [[ "$APP_DIR" == *"/pos_app" ]]; then
        echo "DIBATALKAN: APP_DIR=$APP_DIR adalah aplikasi situs klien (pos.conweb.id)." >&2
        echo "Skrip ini tidak boleh dijalankan di sana. Pakai ~/yokapos_app." >&2
        exit 1
    fi
    for ROOT in "${WEB_ROOT_LIST[@]}"; do
        if [[ "$ROOT" == *"conweb.id"* ]]; then
            echo "DIBATALKAN: $ROOT milik situs klien (pos.conweb.id)." >&2
            echo "Untuk sengaja men-deploy ke sana, jalankan dengan ALLOW_CLIENT_SITE=yes." >&2
            exit 1
        fi
    done
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

    # Domain harus bootstrap source yang sama dengan APP_DIR. Kalau tidak, halaman bisa
    # memakai view/controller lama walaupun CSS baru sudah disalin.
    if ! grep -q "$APP_DIR/bootstrap/app.php" "$WEB_ROOT_ITEM/index.php"; then
        echo "    GAGAL: $WEB_ROOT_ITEM/index.php belum menunjuk ke $APP_DIR" >&2
        echo "    Ikuti bagian 'Domain utama yokapos.com' di DEPLOY-HOSTINGER.md." >&2
        exit 1
    fi

    mkdir -p "$WEB_ROOT_ITEM/build" "$WEB_ROOT_ITEM/images"
    cp -r "$APP_DIR/public/build/." "$WEB_ROOT_ITEM/build/"
    cp -r "$APP_DIR/public/images/." "$WEB_ROOT_ITEM/images/"

    # Foto produk/logo hasil upload disimpan di storage/app/public dan dilayani
    # lewat symlink <web root>/storage. Symlink yang hilang atau menunjuk ke
    # tempat lain membuat SEMUA foto 404. Perbaiki otomatis, dan jangan pernah
    # membuang folder asli sebelum isinya diselamatkan.
    STORAGE_TARGET="$APP_DIR/storage/app/public"
    LINK="$WEB_ROOT_ITEM/storage"
    mkdir -p "$STORAGE_TARGET"
    if [[ -L "$LINK" ]]; then
        if [[ "$(readlink -f "$LINK")" != "$(readlink -f "$STORAGE_TARGET")" ]]; then
            echo "    Symlink storage salah arah ($(readlink "$LINK")); diperbaiki"
            rm "$LINK"
            ln -s "$STORAGE_TARGET" "$LINK"
        fi
    elif [[ -d "$LINK" ]]; then
        echo "    storage adalah folder biasa; isinya disalin ke $STORAGE_TARGET"
        cp -rn "$LINK/." "$STORAGE_TARGET/"
        mv "$LINK" "$LINK.bak-$(date +%Y%m%d%H%M%S)"
        ln -s "$STORAGE_TARGET" "$LINK"
    elif [[ ! -e "$LINK" ]]; then
        ln -s "$STORAGE_TARGET" "$LINK"
    fi
    if [[ ! -r "$LINK/." ]]; then
        echo "    PERINGATAN: $LINK tidak terbaca; foto upload akan 404" >&2
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
