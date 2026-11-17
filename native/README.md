# Yoka POS iOS lokal

Project iOS mengikuti alur aplikasi Android Yoka: splash, onboarding, welcome,
dan registrasi dibuat native. Halaman kasir memakai WebView terbatas yang
terhubung ke `https://yokapos.com`; dashboard/backoffice tidak tersedia di app.

## Menjalankan

```bash
npm run ios:sync
npm run ios:open
```

Di Xcode, pilih simulator lalu tekan tombol Run. iPhone mendukung portrait dan
landscape; iPad tetap dikunci landscape untuk alur kasir tablet. Aplikasi hanya
memakai HTTPS, sama seperti flavor Yoka di Android.

## POS portrait di iPhone

- Modal pilihan produk, catatan item, hold order, dan daftar order hold tampil
  sebagai bottom sheet yang mudah dijangkau dengan satu tangan.
- Pembayaran memakai layar penuh agar total, metode bayar, input nominal, dan
  tombol proses tetap terbaca saat keyboard muncul.
- Ringkasan transaksi berhasil dapat digulir pada iPhone berlayar pendek.
- Bidang input memakai ukuran aman agar Safari tidak memperbesar halaman secara
  otomatis; tombol penting memiliki area sentuh minimal 44 poin.
- Safe area bawah iPhone dan gestur kembali ditangani oleh wrapper native.
- Penyesuaian ini hanya diinjeksi oleh aplikasi iOS pada iPhone, sehingga web,
  iPad, dan Android tidak berubah.

## Ketahanan koneksi dan sesi

- Banner native muncul ketika perangkat kehilangan internet.
- Tombol `Coba Lagi` tersedia bila halaman gagal dimuat dan otomatis mencoba
  kembali setelah koneksi pulih.
- Halaman kasir terakhir (`/pos`, shift, order, atau kitchen) disimpan untuk
  dipulihkan ketika aplikasi dibuka kembali.
- Jika sesi server kedaluwarsa, halaman login menjadi sumber status dan aplikasi
  tidak menganggap cookie sesi tamu sebagai login.
- Layar tidak tidur selama halaman kasir aktif. Saat pertama membuka POS,
  aplikasi menampilkan petunjuk Guided Access satu kali.
- Bila aplikasi masuk background ketika pembayaran masih diproses, kasir diminta
  memeriksa riwayat sebelum mencoba kembali untuk mencegah order ganda.

## Cetak di iOS

Pemanggilan cetak dari halaman struk ditangkap wrapper iOS. Aplikasi membuka
Preview Struk terlebih dahulu, lalu tombol `AirPrint` membuka dialog printer
sistem. Preview dapat diuji tanpa printer fisik; hasil akhir tetap perlu diuji
dengan printer outlet. Pengaturan RawBT tetap khusus Android.

Checklist distribusi tersedia di [APPSTORE.md](APPSTORE.md).
