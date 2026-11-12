# Aturan untuk agen AI (Codex, Claude, dll.)

## Dua aplikasi di satu server Hostinger

| | Yoka POS (produk kita) | Situs klien (LIVE) |
|---|---|---|
| Domain | yokapos.com | pos.conweb.id |
| Folder app | `~/yokapos_app` | `~/pos_app` |
| Folder web | `~/domains/yokapos.com/public_html` | `~/domains/conweb.id/public_html/pos` |
| Database | `u598194357_yokapos` | `u598194357_pos` |

Situs klien dipakai outlet yang sedang berjalan (Relasi Coffee, KOPI MERTUAKU).

## Larangan keras
- Jangan ubah, deploy, `git pull`, migrate, seed, restart, atau hapus cache apa pun di `~/pos_app`, `pos.conweb.id`, atau `domains/conweb.id`.
- Jangan arahkan `index.php` yokapos.com ke `~/pos_app`. Harus selalu ke `~/yokapos_app`.
- Jangan jalankan `ALLOW_CLIENT_SITE=yes ./deploy.sh` kecuali pemilik repo menyuruhnya secara eksplisit di chat.
- Jangan commit rahasia (`.env`, password, PIN, token). Repo ini publik.
- Jangan jalankan seeder yang menghapus data.

## Deploy
- `./deploy.sh` hanya menyasar yokapos.com. Skrip menolak jalan bila diarahkan ke `pos_app` atau `conweb.id`.
- Backup database dulu sebelum `migrate` di server.
- Cron di hPanel: `/usr/bin/php /home/u598194357/yokapos_app/artisan schedule:run` tiap menit (hanya yokapos_app).
- Hasil build Vite (`public/build`) di-commit; jalankan `npm run build` sebelum deploy tampilan.

## Desain admin
Ikuti sistem desain Yoka yang sudah ada (kartu putih `rounded-xl`, hijau `#16A34A`, navy `#0B1B3F`, komponen `x-kpi`, `x-panel`, `x-pill`, `x-empty`). Tanpa emoji.
