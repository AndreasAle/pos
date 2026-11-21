# 08 — Rollout dan rollback

Rollout (dijalankan pemilik, bukan agen):
1. Backup database `u598194357_yokapos`.
2. `./deploy.sh` (menjalankan migrate `2026_11_22_000001_adit_bd_intelligence`: 3 kolom + 1 kolom json di prospek, 2 tabel baru; tidak menghapus data).
3. Flag default: discovery, keberatan, penasihat negosiasi, rekomendasi trial = aktif (tidak mengirim apa pun). `ADIT_BD_TRIAL_DRAFTS=false`.
4. Opsional `.env`: `ADIT_BD_COST_PER_CUSTOMER`, `ADIT_BD_PAYMENT_FEE_PCT`, `ADIT_BD_FLOOR_PRICE` untuk analisis margin.

Mematikan cepat (tanpa rollback kode): set di `.env` server `ADIT_BD_DISCOVERY=false`, `ADIT_BD_OBJECTIONS=false`, `ADIT_BD_NEGOTIATION=false`, lalu `php artisan config:cache`. Penjaga harga (`scrubReply`) dan state machine tetap aktif karena itu kebijakan, bukan fitur.

Rollback penuh: revert commit, lalu `php artisan migrate:rollback --step=1` (menghapus 2 tabel baru dan kolom BD; data spreadsheet lama tidak tersentuh).
