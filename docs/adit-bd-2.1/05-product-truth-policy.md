# 05 — Kebenaran produk

Sumber fakta:
- Fitur: `AgentBrain::productFacts()` (fitur aktif / kerangka belum aktif / sedang dibangun / belum dipastikan) — setara AVAILABLE / IN_DEVELOPMENT / UNKNOWN.
- Harga, batas outlet/user: `subscription_plans`.
- Trial: 7 hari (`DraftWhatsAppReply::TRIAL_DAYS`).
- URL: hanya `yokapos.com`, `yokapos.com/register`, `yokapos.com/panduan`, dan `yokapos.com/panduan/{slug}` yang ada di `GuideController::guides()`.
- Layanan bantuan: WhatsApp (Adit, asisten AI) + tim Yoka bila perlu manusia + panduan. **Jam layanan dan target respon belum ditetapkan**, jadi tidak boleh dijanjikan.
- Perangkat: tidak ada katalog. Cetak lewat dialog print browser atau Bluetooth via RawBT (Android). Printer LAN/WiFi dan daftar merek: belum dipastikan.

`ResponseQuality` (gagal keras → kalimat dibuang): klaim 24 jam/nonstop/respon cepat/dijamin/garansi/"tim Indonesia langsung"/100%, dan URL yokapos.com yang tidak terdaftar. Penjaga BD 2.0 (harga tak resmi, gratis selamanya, klaim sudah disetujui) dan penjaga lama (diskon, klaim tiket/teknisi) tetap berjalan sebelumnya.

Keterbatasan: penyaring ini berbasis kalimat, belum regenerasi. Bila semua kalimat terbuang, tidak ada balasan terkirim dan percakapan tetap tercatat di kanban untuk ditangani manusia.
