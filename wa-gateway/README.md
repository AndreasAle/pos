# Yoka WA Gateway

Gateway WhatsApp milik Yoka sendiri, sebagai pengganti Fonnte untuk **nomor resmi Yoka saja**. Dibangun di atas [Baileys](https://github.com/WhiskeySockets/Baileys): server tersambung ke WhatsApp seperti WhatsApp Web, setelah nomor di-scan QR.

> **Risiko yang diterima pemilik:** ini jalur tidak resmi dan melanggar ketentuan layanan WhatsApp. Nomor bisa dibatasi atau diblokir. Rem anti-spam di bawah memperkecil risiko itu, tetapi tidak menghapusnya. Fonnte tetap bisa dipakai kapan saja dengan mengganti satu baris `.env`.

## Cara kerja

```
Pelanggan chat ──> WhatsApp ──> Gateway (Baileys) ──POST sender,message,...──> Laravel webhook (sama seperti Fonnte)
                                                                                     │
                                                                              Adit membuat balasan
                                                                                     │
Pelanggan <── WhatsApp <── Gateway: rem anti-spam + antrean ──< POST /send (format Fonnte) ┘
```

- Pesan masuk diteruskan ke webhook yang sudah ada: `platform-ai/whatsapp/webhook/{secret}`. Kode Adit tidak berubah.
- Laravel mengirim lewat `POST /send` dengan field yang sama seperti Fonnte (`target`, `message`, `typing`, `delay`).
- Grup, status, channel, dan pesan dari nomor sendiri diabaikan.

## Rem anti-spam

Semua batas ditegakkan di gateway (`src/guard.js`). Bug di Laravel atau jawaban AI yang aneh tidak bisa menembusnya.

| Aturan | Default |
|---|---|
| Antrean satu per satu, jeda minimum antar pesan | 2,5 detik + acak |
| Efek "sedang mengetik" sebelum balasan | sesuai `delay` |
| Maksimal global per menit | 12 |
| Maksimal global per hari | 500 |
| Maksimal ke satu nomor per jam / per hari | 20 / 60 |
| Pesan sama persis ke nomor yang sama | ditolak selama 10 menit |
| **Pesan dingin** (nomor belum chat dalam 24 jam) per hari | 20 |
| Pesan dingin ke nomor yang sama | sekali per 72 jam, dan nomor dicek terdaftar di WA |
| Pelanggan kirim `STOP` / `berhenti` / `unsubscribe` | semua kiriman ke nomor itu diblokir sampai dia chat lagi |
| Panjang pesan | maks. 2.000 karakter |

Penghitung disimpan di `data/guard.json`, jadi batas tidak ter-reset saat gateway restart. Pesan yang ditahan dijawab HTTP 429 dengan alasan, dan alasannya muncul di Laravel sebagai error kirim.

## Kebutuhan

Gateway harus menyala 24 jam, jadi **tidak bisa di hosting Hostinger shared**. Butuh VPS kecil (1 vCPU, 1 GB RAM cukup) dengan Node.js 20+.

## Pemasangan di VPS

```bash
git clone https://github.com/AndreasAle/pos.git yoka && cd yoka/wa-gateway
npm ci --omit=dev
cp .env.example .env
nano .env        # isi GATEWAY_TOKEN dan WEBHOOK_URL
npm install -g pm2
pm2 start npm --name yoka-wa -- start
pm2 save && pm2 startup
```

`WEBHOOK_URL` sama dengan URL webhook yang dulu dipasang di Fonnte:
`https://yokapos.com/platform-ai/whatsapp/webhook/<YOKA_FONNTE_WEBHOOK_SECRET>`

Gateway hanya mendengar di `127.0.0.1`. Buka ke internet lewat reverse proxy HTTPS, contoh Nginx dengan subdomain `wa.yokapos.com`:

```nginx
server {
    server_name wa.yokapos.com;
    location / { proxy_pass http://127.0.0.1:3100; proxy_read_timeout 90s; }
    # sertifikat: certbot --nginx -d wa.yokapos.com
}
```

## Scan QR (login nomor)

1. Buka `https://wa.yokapos.com/qr?token=<GATEWAY_TOKEN>` di laptop. Jangan bagikan link ini.
2. Di HP nomor resmi Yoka: **WhatsApp → Perangkat tertaut → Tautkan perangkat**, lalu scan QR di layar.
3. Halaman memuat ulang sendiri dan menampilkan "Terhubung sebagai 62…".
4. Sesi tersimpan di `data/auth/`. Restart gateway tidak perlu scan ulang. Scan ulang hanya bila perangkat dikeluarkan dari HP.

Catatan: kalau sebelumnya nomor ini tertaut ke Fonnte, keluarkan perangkat Fonnte dulu dari HP supaya pesan masuk tidak diproses dua kali.

## Pindah dari Fonnte ke gateway

Di `.env` Laravel (server yokapos_app):

```
YOKA_WA_DRIVER=gateway
YOKA_WA_GATEWAY_URL=https://wa.yokapos.com
YOKA_WA_GATEWAY_TOKEN=<sama dengan GATEWAY_TOKEN>
```

Lalu `php artisan config:cache`. Untuk kembali ke Fonnte cukup set `YOKA_WA_DRIVER=fonnte` dan jalankan `config:cache` lagi.

## API

Semua endpoint selain `/health` wajib header `Authorization: <GATEWAY_TOKEN>` (atau `?token=` untuk halaman QR).

| Endpoint | Fungsi |
|---|---|
| `GET /health` | Status singkat, tanpa token |
| `GET /qr` | Halaman QR / status koneksi |
| `GET /status` | Status, nomor, dan pemakaian kuota anti-spam |
| `POST /send` | Kirim pesan: `target` (62…), `message`, `typing`, `delay` |
| `POST /logout` | Keluarkan sesi WhatsApp |

## Keamanan

- `data/` berisi kunci sesi WhatsApp. Siapa pun yang memegang folder ini bisa memakai nomor Yoka. Jangan di-commit (sudah di `.gitignore`), dan batasi akses VPS.
- `GATEWAY_TOKEN` minimal 24 karakter; gateway menolak menyala tanpa token.
- Jangan pernah memakai gateway ini untuk nomor merchant atau klien. Struk merchant tetap lewat Fonnte milik merchant masing-masing (`WhatsAppService`).

## Pengujian

```bash
npm test
```
