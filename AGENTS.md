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

## Tim AI Yoka (`/platform/ai-team`)
Tujuan jangka panjang: agen AI menjadi pekerja penuh tim Yoka. Dibangun bertahap, satu agen sampai benar-benar bekerja dulu.
- Obrolan agen: persona, keahlian, dan tool baca-data ada di `app/Services/PlatformAgents/AgentBrain.php`. Gaya wajib seperti rekan kerja (aku/kamu, panggil "Bos", berani berpendapat); jangan kembalikan ke bahasa laporan.
- Agen = loop LLM dengan tool (lihat `app/Services/PlatformAgents/TrialAgent.php`, dipakai Adit/CS). Batas keras (kuota, siapa boleh dihubungi, anti-duplikat) ditulis di kode, bukan di prompt.
- Tiga tingkat otonomi: bebas (baca data, analisis, draf), perlu approve manusia (kirim pesan, iklan, uang), dilarang permanen (refund, ubah harga, scraping, pesan massal).
- Agen hanya menyentuh data platform Yoka (`yokapos_app`). Jangan beri akses ke `pos_app` atau data klien live.
- Pesan keluar PROAKTIF (follow-up trial, outreach sales, broadcast) hanya terkirim lewat tombol "Setujui & kirim"; jalan pintas via papan kanban diblokir.
- Pengecualian (keputusan pemilik, 2026-11-19): Adit (CS) membalas sendiri WhatsApp MASUK ke nomor resmi Yoka (`app/Jobs/DraftWhatsAppReply.php`). Batas keras di kode: pesan di luar topik hanya dibaca, topik sensitif (refund/tagihan/data/keamanan/hukum) hanya dibalas "sedang dicek" dan ditandai merah, tanpa diskon/promo/janji tanggal, batas balasan per nomor per jam dan per hari, satu pesan diproses sekali. Setiap percakapan tercatat otomatis sebagai kartu kanban `whatsapp_conversation` tanpa approval. Jangan longgarkan batas ini tanpa izin pemilik.
- Urutan: Trial/CS, lalu Finance dan Accounting, terakhir Marketing dan Ads (butuh token eksternal dan menyentuh uang).
- Token (`YOKA_FONNTE_TOKEN`, Meta, TikTok, WhatsApp) hanya di `.env` server, tidak pernah di repo.
