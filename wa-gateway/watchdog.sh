#!/bin/bash
#
# Watchdog gateway WhatsApp Yoka (Hostinger shared hosting tidak punya pm2/systemd).
# Dijalankan cron tiap menit dari hPanel:
#   * * * * * /bin/bash /home/u598194357/wa-gateway/watchdog.sh
#
# Bila proses gateway mati, nyalakan lagi dan catat di watchdog.log.
# Hanya menyentuh ~/wa-gateway (nomor resmi Yoka), tidak menyentuh pos_app atau situs klien.

set -u
DIR="/home/u598194357/wa-gateway"
SERVER="$DIR/src/server.js"
LOG="$DIR/watchdog.log"
LOCK="$DIR/data/watchdog.lock"

cd "$DIR" || exit 1
mkdir -p "$DIR/data"

# Cegah dua watchdog berjalan bersamaan.
exec 9>"$LOCK"
flock -n 9 || exit 0

if pgrep -u "$(id -u)" -f "$SERVER" > /dev/null 2>&1; then
    exit 0
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') gateway mati, menyalakan ulang" >> "$LOG"
# Socket lama yang tertinggal membuat server gagal listen.
rm -f "$DIR/data/gateway.sock"
nohup node --env-file="$DIR/.env" "$SERVER" >> "$DIR/gateway.log" 2>&1 &
sleep 10

if pgrep -u "$(id -u)" -f "$SERVER" > /dev/null 2>&1; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') gateway hidup lagi (pid $(pgrep -u "$(id -u)" -f "$SERVER" | head -1))" >> "$LOG"
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') GAGAL menyalakan gateway, cek gateway.log" >> "$LOG"
fi

# Simpan log tetap kecil.
tail -n 500 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
