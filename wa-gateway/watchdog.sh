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

# Cron hPanel berjalan dengan PATH minimal, jadi `node` sering tidak ketemu. Cari path absolutnya.
NODE_BIN="${NODE_BIN:-}"
if [ -z "$NODE_BIN" ] && [ -f "$DIR/.node_bin" ]; then NODE_BIN="$(cat "$DIR/.node_bin")"; fi
if [ -z "$NODE_BIN" ] || [ ! -x "$NODE_BIN" ]; then NODE_BIN="$(command -v node 2>/dev/null || true)"; fi
if [ -z "$NODE_BIN" ] || [ ! -x "$NODE_BIN" ]; then
    for c in "$HOME"/.nvm/versions/node/*/bin/node /opt/alt/alt-nodejs*/root/usr/bin/node /usr/local/bin/node /usr/bin/node; do
        if [ -x "$c" ]; then NODE_BIN="$c"; fi
    done
fi
if [ -z "$NODE_BIN" ] || [ ! -x "$NODE_BIN" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') GAGAL: node tidak ditemukan. Simpan path-nya: which node > $DIR/.node_bin" >> "$LOG"
    exit 1
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') gateway mati, menyalakan ulang" >> "$LOG"
# Socket lama yang tertinggal membuat server gagal listen.
rm -f "$DIR/data/gateway.sock"
nohup "$NODE_BIN" --env-file="$DIR/.env" "$SERVER" >> "$DIR/gateway.log" 2>&1 &
sleep 10

if pgrep -u "$(id -u)" -f "$SERVER" > /dev/null 2>&1; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') gateway hidup lagi (pid $(pgrep -u "$(id -u)" -f "$SERVER" | head -1))" >> "$LOG"
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') GAGAL menyalakan gateway, cek gateway.log" >> "$LOG"
fi

# Simpan log tetap kecil.
tail -n 500 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
