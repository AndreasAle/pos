# Yoka POS

Sistem kasir (Point of Sale) modern untuk cafe, resto, bakery, dan bisnis F&B. Transaksi, stok bahan baku, shift kasir, hingga laporan laba dalam satu platform.

Dikembangkan oleh **PT Conwebid Indonesia Digital**.

## Fitur

**Kasir**
- Transaksi cepat dengan varian, topping/add-on, bundle, dan promo (termasuk "Beli 1 Gratis 1").
- Hold order dengan nama dan nomor meja, lalu lanjutkan kapan saja.
- Pembayaran tunai dan QRIS, dengan cetak struk (Bluetooth, USB, dan RawBT di Android).
- Login kasir lewat PIN dan kode outlet di `/kasir`.
- Buka dan tutup shift, dengan rekap selisih kas.

**Operasional**
- Manajemen produk, kategori, bundle, dan promosi.
- Resep per produk dengan stok bahan baku yang berkurang otomatis, plus riwayat pergerakan stok.
- Layar dapur (kitchen) untuk memantau pesanan masuk.
- Data pelanggan dan poin loyalitas.
- Banyak outlet dan banyak pengguna dengan peran berbeda.

**Laporan**
- Laporan penjualan, shift, dan laba (profit per menu).
- Ekspor ke Excel/CSV dan PDF.
- Audit log aktivitas.

**Akun dan langganan**
- Registrasi dengan verifikasi email dan reset password.
- Paket langganan per bisnis.

## Teknologi

| Bagian | Teknologi |
| --- | --- |
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Blade, Alpine.js, Tailwind CSS 4 |
| Build | Vite 6 |
| Database | MySQL / MariaDB |

## Instalasi

Prasyarat: PHP 8.2+, Composer, Node.js 18+, dan MySQL/MariaDB.

```bash
git clone https://github.com/AndreasAle/pos.git
cd pos

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Atur koneksi database di `.env`, lalu jalankan migrasi:

```bash
php artisan migrate
```

Jalankan aplikasi:

```bash
php artisan serve
npm run dev
```

Buka `http://localhost:8000`.

### Data demo

Untuk mencoba dengan data contoh, gunakan database terpisah agar data asli tidak tersentuh. Lihat petunjuk di `tools/serve-demo.php`.

## Deploy

Build aset produksi:

```bash
npm run build
```

Di server, jalankan skrip deploy dari folder aplikasi:

```bash
./deploy.sh
```

> Jangan pernah menjalankan seeder yang menghapus data atau mereset database di server produksi.

## Keamanan

- Jangan commit file `.env`, PIN, atau password apa pun. Repo ini publik.
- Konfigurasi rahasia (kredensial email, PIN awal) hanya diisi lewat `.env` di server.

## Lisensi

Hak cipta © PT Conwebid Indonesia Digital. Seluruh hak cipta dilindungi.
