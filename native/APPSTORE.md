# Persiapan App Store Yoka POS

## Sudah tersedia di project

- Bundle ID: `com.yokapos.app`.
- Versi awal: `1.0` (build `1`).
- App icon 1024 x 1024 dan launch screen Yoka.
- Privacy manifest tanpa tracking; penggunaan `UserDefaults` dijelaskan dengan
  reason code Apple `CA92.1`.
- HTTPS saja, tanpa izin kamera, mikrofon, lokasi, kontak, atau foto.
- Hapus akun tersedia dari Welcome melalui `/hapus-akun`.
- Langganan, harga, dan checkout diblokir dari aplikasi kasir.
- Google OAuth disembunyikan karena tidak didukung di WebView.

## Sebelum Archive/TestFlight

1. Aktifkan Apple Developer Program dan pilih Team di Signing & Capabilities.
2. Pastikan nama aplikasi `Yoka POS` tersedia di App Store Connect.
3. Naikkan build number setiap upload (`1`, `2`, `3`, dan seterusnya).
4. Isi App Privacy sesuai data yang benar-benar dikumpulkan server Yoka, bukan
   hanya data yang diproses native app.
5. Siapkan URL dukungan, URL kebijakan privasi, deskripsi, kata kunci, kategori,
   serta screenshot iPhone dan iPad.
6. Uji login pemilik, login PIN kasir, pembayaran, hold, riwayat, hapus akun,
   Print Preview/AirPrint, koneksi terputus, dan sesi kedaluwarsa di perangkat.
7. Berikan akun demo yang stabil beserta instruksi App Review.
8. Pastikan teks hukum di `/privasi`, `/syarat`, dan `/hapus-akun` bukan lagi DRAF.

## Catatan review

Yoka POS adalah aplikasi operasional kasir. Langganan bisnis dibuat dan dikelola
di luar aplikasi; aplikasi tidak menampilkan harga, tombol pembelian, atau ajakan
membeli. Guided Access bersifat opsional dan diaktifkan pemilik perangkat lewat
pengaturan iOS.
