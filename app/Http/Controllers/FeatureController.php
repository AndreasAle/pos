<?php

namespace App\Http\Controllers;

class FeatureController extends Controller
{
    public function show(string $feature)
    {
        $features = $this->features();

        abort_unless(isset($features[$feature]), 404);

        return view('features.show', [
            'feature' => $features[$feature],
            'featureSlug' => $feature,
            'featureGroups' => $this->groups($features),
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function features(): array
    {
        return [
            'transaksi-kasir' => [
                'category' => 'Kasir & Pembayaran',
                'title' => 'Transaksi Kasir',
                'headline' => 'Layani antrean lebih cepat dari satu layar',
                'description' => 'Pilih menu, atur varian dan topping, terima pembayaran, lalu cetak struk tanpa berpindah aplikasi.',
                'image' => 'images/features/transaksi-kasir.webp',
                'image_alt' => 'Kasir melayani pelanggan menggunakan Yoka POS',
                'highlights' => [
                    ['Pesanan fleksibel', 'Dine-in, takeaway, dan delivery tercatat dalam alur yang sama.'],
                    ['Varian & add-on', 'Ukuran, topping, catatan, dan harga tambahan dihitung otomatis.'],
                    ['Harga aman', 'Harga transaksi selalu dibaca dari database, bukan dari perangkat kasir.'],
                ],
                'steps' => [['Pilih produk', 'Cari menu atau scan barcode.'], ['Atur pesanan', 'Tambahkan varian, topping, dan catatan.'], ['Bayar & cetak', 'Terima pembayaran dan keluarkan struk.']],
                'metrics' => [['Satu layar', 'Dari pesanan sampai pembayaran'], ['Multi metode', 'Tunai, QRIS, debit, transfer'], ['Real-time', 'Stok dan laporan langsung berubah']],
            ],
            'pembayaran-qris' => [
                'category' => 'Kasir & Pembayaran',
                'title' => 'Pembayaran & QRIS',
                'headline' => 'Pembayaran cepat, nominal tercatat tepat',
                'description' => 'Terima tunai, QRIS, transfer, debit, e-wallet, hingga split payment dengan total yang dihitung server.',
                'image' => 'images/features/pembayaran-qris.webp',
                'image_alt' => 'Layar pembayaran Yoka POS',
                'highlights' => [
                    ['QRIS dinamis', 'Nominal transaksi langsung tertanam pada QR untuk mengurangi salah bayar.'],
                    ['Split payment', 'Satu tagihan dapat dibayar menggunakan beberapa metode sekaligus.'],
                    ['Konfirmasi aman', 'Status pembayaran dan saldo merchant diproses secara idempoten.'],
                ],
                'steps' => [['Pilih metode', 'Kasir memilih tunai, QRIS, atau kombinasi.'], ['Konfirmasi', 'Sistem memvalidasi nominal pembayaran.'], ['Tercatat otomatis', 'Metode dan nilai masuk ke laporan.']],
                'metrics' => [['6 metode', 'Pilihan pembayaran utama'], ['QRIS', 'Statis, dinamis, atau Midtrans'], ['Split', 'Gabungkan beberapa pembayaran']],
            ],
            'hold-order' => [
                'category' => 'Kasir & Pembayaran',
                'title' => 'Hold Order',
                'headline' => 'Simpan pesanan, lanjutkan kapan saja',
                'description' => 'Tahan pesanan menggunakan nama pelanggan atau nomor meja, lalu buka kembali tanpa mengetik ulang.',
                'image' => 'images/features/hold-order.webp',
                'image_alt' => 'Operasional pesanan di sebuah kafe',
                'highlights' => [
                    ['Nama pelanggan', 'Order lebih mudah ditemukan saat pelanggan kembali ke kasir.'],
                    ['Nomor meja', 'Pesanan dine-in tetap rapi di tengah jam sibuk.'],
                    ['Lintas perangkat', 'Order tersimpan di server dan dapat dilanjutkan dari register lain.'],
                ],
                'steps' => [['Isi pesanan', 'Masukkan item seperti transaksi biasa.'], ['Simpan ke hold', 'Tambahkan nama atau nomor meja.'], ['Buka & selesaikan', 'Muat kembali order lalu lanjutkan pembayaran.']],
                'metrics' => [['Tersimpan aman', 'Tidak hilang saat halaman ditutup'], ['Mudah dicari', 'Nama dan meja terlihat jelas'], ['Tanpa input ulang', 'Semua item kembali ke keranjang']],
            ],
            'struk-printer' => [
                'category' => 'Kasir & Pembayaran',
                'title' => 'Struk & Printer',
                'headline' => 'Struk langsung siap setelah pembayaran',
                'description' => 'Cetak struk melalui browser, printer thermal, atau RawBT di Android, serta simpan versi PDF untuk pelanggan.',
                'image' => 'images/features/struk-printer.webp',
                'image_alt' => 'Printer struk thermal untuk Yoka POS',
                'highlights' => [
                    ['Printer thermal', 'Layout struk disiapkan untuk kertas kasir 58 mm dan 80 mm.'],
                    ['RawBT Android', 'Kirim struk ke printer Bluetooth dari perangkat kasir.'],
                    ['PDF & kitchen ticket', 'Struk pelanggan dan tiket dapur memiliki format masing-masing.'],
                ],
                'steps' => [['Selesaikan order', 'Struk dibuat dari data transaksi final.'], ['Pilih perangkat', 'Browser, RawBT, atau tanpa cetak.'], ['Cetak atau bagikan', 'Gunakan struk fisik maupun PDF.']],
                'metrics' => [['58/80 mm', 'Dukungan ukuran kertas'], ['Bluetooth', 'Cetak lewat RawBT Android'], ['PDF', 'Mudah disimpan dan dibagikan']],
            ],
            'stok-resep' => [
                'category' => 'Operasional Bisnis',
                'title' => 'Stok & Resep',
                'headline' => 'Stok bahan berkurang otomatis setiap ada penjualan',
                'description' => 'Hubungkan produk dengan resep agar susu, kopi, tepung, dan bahan lain selalu mengikuti transaksi aktual.',
                'image' => 'images/features/stok-resep.webp',
                'image_alt' => 'Tim memantau stok dan laporan Yoka POS',
                'highlights' => [
                    ['Resep per produk', 'Tentukan kebutuhan setiap bahan untuk satu porsi menu.'],
                    ['Riwayat stok', 'Semua stok masuk, keluar, penyesuaian, dan penjualan memiliki jejak.'],
                    ['Restock akurat', 'Biaya rata-rata bahan diperbarui menggunakan perhitungan tertimbang.'],
                ],
                'steps' => [['Buat bahan', 'Masukkan satuan, stok, dan batas minimum.'], ['Susun resep', 'Hubungkan bahan ke produk dan bundle.'], ['Jual seperti biasa', 'Stok dipotong otomatis saat order dibayar.']],
                'metrics' => [['Otomatis', 'Potong stok dari transaksi'], ['Terlacak', 'Jejak pergerakan setiap bahan'], ['Peringatan', 'Notifikasi saat stok menipis']],
            ],
            'kitchen-display' => [
                'category' => 'Operasional Bisnis',
                'title' => 'Kitchen Display',
                'headline' => 'Pesanan dari kasir langsung terlihat di dapur',
                'description' => 'Tim dapur melihat item, topping, dan catatan pelanggan tanpa menunggu kertas pesanan berpindah tangan.',
                'image' => 'images/features/kitchen-display.webp',
                'image_alt' => 'Tim operasional kafe menyiapkan pesanan',
                'highlights' => [
                    ['Status pesanan', 'Pantau antrean dari pesanan baru hingga selesai.'],
                    ['Catatan lengkap', 'Varian, add-on, dan permintaan pelanggan ikut tampil.'],
                    ['Kategori terpilih', 'Hanya kategori yang perlu diproses dapur yang dikirim.'],
                ],
                'steps' => [['Order dibayar', 'Kasir menyelesaikan transaksi.'], ['Masuk ke dapur', 'Pesanan relevan muncul pada kitchen display.'], ['Perbarui status', 'Tim menandai proses sampai pesanan siap.']],
                'metrics' => [['Real-time', 'Pesanan masuk tanpa input ulang'], ['Lebih jelas', 'Catatan pelanggan ikut tampil'], ['Terfokus', 'Filter berdasarkan kebutuhan dapur']],
            ],
            'shift-karyawan' => [
                'category' => 'Operasional Bisnis',
                'title' => 'Shift & Karyawan',
                'headline' => 'Kontrol uang kas dan akses setiap anggota tim',
                'description' => 'Kasir membuka shift dengan modal awal, menutupnya dengan uang aktual, dan sistem menghitung selisih secara otomatis.',
                'image' => 'images/features/shift-karyawan.webp',
                'image_alt' => 'Pemilik memantau operasional dan shift kasir',
                'highlights' => [
                    ['Rekonsiliasi kas', 'Bandingkan kas yang seharusnya dengan uang fisik saat tutup shift.'],
                    ['Login PIN', 'Perangkat kasir dapat dipasangkan ke outlet dan digunakan staf lewat PIN.'],
                    ['Hak akses', 'Owner, admin, kasir, kitchen, dan warehouse memiliki akses berbeda.'],
                ],
                'steps' => [['Buka shift', 'Masukkan outlet dan modal kas awal.'], ['Jalankan transaksi', 'Penjualan tunai masuk ke perhitungan shift.'], ['Tutup & cocokkan', 'Masukkan kas aktual dan lihat selisihnya.']],
                'metrics' => [['5 role', 'Akses sesuai tanggung jawab'], ['PIN kasir', 'Masuk cepat dari perangkat outlet'], ['Rekonsiliasi', 'Selisih kas langsung terlihat']],
            ],
            'pelanggan-promosi' => [
                'category' => 'Pelanggan & Penjualan',
                'title' => 'Pelanggan & Promosi',
                'headline' => 'Buat pelanggan punya alasan untuk kembali',
                'description' => 'Simpan profil pelanggan, berikan poin loyalitas, dan jalankan promo atau bundle tanpa perhitungan manual.',
                'image' => 'images/features/pelanggan-promosi.webp',
                'image_alt' => 'Pemilik usaha melihat data pelanggan dan penjualan',
                'highlights' => [
                    ['Poin loyalitas', 'Poin didapat dan ditukar langsung saat transaksi.'],
                    ['Promo fleksibel', 'Diskon nominal, persentase, hingga beli-gratis dapat diatur.'],
                    ['Produk bundle', 'Gabungkan beberapa produk dalam satu paket dan harga khusus.'],
                ],
                'steps' => [['Daftarkan pelanggan', 'Simpan nama dan kontak saat dibutuhkan.'], ['Terapkan program', 'Pilih promo, bundle, atau penukaran poin.'], ['Pantau riwayat', 'Lihat transaksi dan perkembangan pelanggan.']],
                'metrics' => [['Loyalty', 'Poin tercatat per transaksi'], ['Buy & get', 'Promo beli dan gratis'], ['Bundle', 'Paket produk dalam satu harga']],
            ],
            'telegram' => [
                'category' => 'Pantau Bisnis',
                'title' => 'Asisten Bisnis Telegram',
                'headline' => 'Tanya kondisi toko langsung dari Telegram',
                'description' => 'Cek omzet, profit, stok, produk terlaris, pelanggan, jam ramai, dan shift menggunakan bahasa sehari-hari.',
                'image' => 'images/hero/slide-owner.jpg',
                'image_alt' => 'Pemilik toko memantau bisnis dari ponsel',
                'highlights' => [
                    ['Tanya dengan bahasa biasa', 'Ketik “omzet hari ini berapa?” atau “stok susu masih ada?”.'],
                    ['Notifikasi otomatis', 'Terima transaksi baru, stok menipis, shift terbuka, void, dan refund.'],
                    ['Ringkasan berkala', 'Laporan harian dikirim langsung ke chat yang ditautkan bisnis.'],
                ],
                'steps' => [['Hubungkan bot', 'Buat tautan dari pengaturan Yoka.'], ['Tekan Start', 'Chat Telegram ditautkan ke bisnis.'], ['Tanya kapan saja', 'Gunakan tombol cepat atau tulis pertanyaan sendiri.']],
                'metrics' => [['Chat langsung', 'Jawaban dari data bisnis'], ['Peringatan', 'Stok, shift, void, dan refund'], ['Privat', 'Setiap chat ditautkan ke satu bisnis']],
            ],
            'laporan' => [
                'category' => 'Pantau Bisnis',
                'title' => 'Laporan Bisnis',
                'headline' => 'Ambil keputusan dari angka yang mudah dipahami',
                'description' => 'Pantau penjualan, produk, kasir, shift, inventory, dan estimasi profit dengan filter tanggal serta outlet.',
                'image' => 'images/about/dashboard-tim.webp',
                'image_alt' => 'Dashboard laporan bisnis Yoka POS',
                'highlights' => [
                    ['Penjualan & produk', 'Lihat omzet, transaksi, metode bayar, dan produk terlaris.'],
                    ['Profit per menu', 'Bandingkan harga jual dan modal untuk mengetahui margin.'],
                    ['Export siap pakai', 'Unduh laporan ke Excel, CSV, atau PDF.'],
                ],
                'steps' => [['Pilih periode', 'Tentukan tanggal dan outlet yang ingin dianalisis.'], ['Baca ringkasan', 'Lihat angka utama dan rincian transaksi.'], ['Export laporan', 'Simpan untuk arsip atau kirim ke tim.']],
                'metrics' => [['6 laporan', 'Penjualan sampai inventory'], ['Multi-outlet', 'Filter performa setiap cabang'], ['Excel & PDF', 'Export dalam sekali klik']],
            ],
        ];
    }

    /** @param array<string, array<string, mixed>> $features */
    private function groups(array $features): array
    {
        return [
            'Kasir & Pembayaran' => array_intersect_key($features, array_flip(['transaksi-kasir', 'pembayaran-qris', 'hold-order', 'struk-printer'])),
            'Operasional Bisnis' => array_intersect_key($features, array_flip(['stok-resep', 'kitchen-display', 'shift-karyawan', 'pelanggan-promosi'])),
            'Pantau Bisnis' => array_intersect_key($features, array_flip(['telegram', 'laporan'])),
        ];
    }
}
