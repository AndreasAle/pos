<?php

namespace App\Http\Controllers;

/**
 * Panduan step-by-step (yokapos.com/panduan). Isi ditulis dari menu dan alur ASLI aplikasi
 * (sidebar layouts/app.blade.php, onboarding, Pengaturan Struk, Shift, Bot Telegram, saas.plans).
 * Kalau label menu di aplikasi berubah, perbarui teks di sini juga.
 */
class GuideController extends Controller
{
    public function index()
    {
        return view('guides.index', ['guides' => self::guides()]);
    }

    public function show(string $slug)
    {
        $guides = self::guides();
        abort_unless(isset($guides[$slug]), 404);
        $slugs = array_keys($guides);
        $i = array_search($slug, $slugs, true);

        return view('guides.show', [
            'guide' => $guides[$slug],
            'slug' => $slug,
            'number' => $i + 1,
            'total' => count($guides),
            'prev' => $i > 0 ? [$slugs[$i - 1], $guides[$slugs[$i - 1]]] : null,
            'next' => $i < count($slugs) - 1 ? [$slugs[$i + 1], $guides[$slugs[$i + 1]]] : null,
            'guides' => $guides,
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    public static function guides(): array
    {
        return [
            'mulai-pakai-yoka-pos' => [
                'category' => 'Mulai',
                'title' => 'Mulai pakai Yoka POS dalam 10 menit',
                'summary' => 'Dari daftar akun sampai transaksi pertama dan struk keluar. Cocok dibaca di hari pertama trial.',
                'minutes' => 10,
                'steps' => [
                    ['Buat akun pemilik', 'Buka yokapos.com lalu tekan Coba Gratis. Isi Nama Lengkap, Nomor HP, Email, dan Password.', 'Pakai email yang aktif, karena tautan konfirmasi dikirim ke sana.'],
                    ['Konfirmasi email', 'Buka email dari Yoka dan tekan tautan konfirmasinya. Kalau belum masuk, cek folder spam atau kirim ulang dari halaman Cek email Anda.', null],
                    ['Isi profil bisnis', 'Isi Nama bisnis, Jenis bisnis, Jumlah outlet, Kota, dan Nama outlet utama. Alamat boleh dikosongkan dulu.', 'Begitu profil bisnis dibuat, trial gratis 7 hari langsung berjalan.'],
                    ['Tambah 3 menu pertama', 'Buka Master Data, lalu Produk / Menu, lalu tambah produk. Cukup isi Nama Produk, Kategori, dan Harga Jual. Detail lain bisa menyusul.', 'Mulai dari 3 menu terlaris supaya cepat bisa transaksi.'],
                    ['Buka shift kasir', 'Buka menu Kasir / POS. Kalau belum ada shift, Yoka meminta kamu membuka shift: pilih Outlet dan isi Modal Awal (uang tunai di laci).', null],
                    ['Lakukan transaksi pertama', 'Ketuk menu yang dipesan, cek total, lalu tekan Bayar. Pilih metode pembayaran, misalnya tunai, lalu selesaikan.', 'Coba satu transaksi uji dengan nominal kecil dulu.'],
                    ['Cetak struk', 'Setelah pembayaran selesai, struk siap dicetak. Kalau printer belum tersambung, ikuti panduan Cara menyambungkan printer struk.', null],
                ],
                'faq' => [
                    ['Apakah trial benar-benar gratis?', 'Ya. Trial gratis 7 hari dimulai setelah profil bisnis dibuat. Kamu bisa memilih paket kapan saja.'],
                    ['Bisa dipakai di HP?', 'Bisa. Yoka berjalan di browser, jadi bisa dibuka di laptop, tablet, maupun HP.'],
                ],
            ],

            'menyambungkan-printer-struk' => [
                'category' => 'Kasir',
                'title' => 'Cara menyambungkan printer struk',
                'summary' => 'Atur ukuran kertas dan cara cetak: lewat dialog print browser (USB/PC) atau Bluetooth lewat RawBT di Android.',
                'minutes' => 5,
                'steps' => [
                    ['Buka Pengaturan Struk', 'Di menu samping, buka Pengaturan, lalu Pengaturan Struk.', null],
                    ['Pilih ukuran kertas', 'Pada Ukuran kertas, pilih Thermal 58mm (kecil) atau Thermal 80mm (standar) sesuai printer kamu.', 'Lihat lebar gulungan kertas di printer: 58 mm atau 80 mm.'],
                    ['Pilih cara cetak', 'Pada Cara cetak, pilih Dialog print browser untuk printer USB atau PC, atau Bluetooth lewat RawBT untuk tablet atau HP Android.', null],
                    ['Untuk Bluetooth: siapkan RawBT', 'Pasang aplikasi RawBT dari Play Store di perangkat Android, lalu sambungkan printer Bluetooth di dalam RawBT.', 'Nyalakan printer dan pasangkan (pair) Bluetooth-nya dulu di pengaturan Android.'],
                    ['Atur isi struk', 'Di bagian Tampilkan di struk, pilih informasi yang ingin muncul, misalnya Logo, Alamat, No. Telp, Nama kasir, dan Nama pelanggan.', null],
                    ['Simpan dan uji', 'Tekan Simpan Semua Pengaturan, lalu lakukan satu transaksi uji untuk memastikan struk tercetak rapi.', 'Perubahan berlaku untuk semua kasir setelah disimpan.'],
                ],
                'faq' => [
                    ['Printer saya USB, pakai yang mana?', 'Pilih Dialog print browser. Struk dicetak lewat jendela print di browser.'],
                    ['Bisa tanpa printer?', 'Bisa. Transaksi tetap tercatat walau struk tidak dicetak.'],
                ],
            ],

            'atur-menu-kategori-harga' => [
                'category' => 'Persiapan',
                'title' => 'Atur menu, kategori, dan harga',
                'summary' => 'Rapikan daftar menu supaya kasir cepat mencari dan margin per menu terlihat.',
                'minutes' => 8,
                'steps' => [
                    ['Buat kategori dulu', 'Buka Master Data, lalu Kategori. Buat kategori seperti Kopi, Non Kopi, Makanan, dan Snack.', 'Kategori yang rapi membuat layar kasir lebih cepat dipakai.'],
                    ['Tambah produk', 'Buka Produk / Menu dan tambah produk baru. Isi Nama Produk dan pilih Kategori.', null],
                    ['Isi Harga Jual dan Harga Modal', 'Harga Jual adalah harga ke pelanggan. Harga Modal adalah biaya membuatnya. Yoka menampilkan untung per porsi dari selisih keduanya.', 'Isi Harga Modal supaya laporan profit lebih akurat.'],
                    ['Tambah foto (opsional)', 'Unggah foto produk dalam format JPEG, PNG, atau WebP agar menu mudah dikenali di layar kasir.', null],
                    ['Atur outlet dan status', 'Pilih Outlet tempat produk dijual dan pastikan Produk aktif tercentang.', null],
                    ['Buat paket bila perlu', 'Untuk paket hemat, buka Bundling Produk dan gabungkan beberapa produk dalam satu harga.', null],
                ],
                'faq' => [
                    ['Apa itu SKU?', 'Kode unik produk. Boleh dikosongkan bila tidak memakai barcode.'],
                    ['Produk tidak muncul di kasir?', 'Cek apakah Produk aktif tercentang dan outlet-nya sesuai.'],
                ],
            ],

            'buka-tutup-shift-kasir' => [
                'category' => 'Kasir',
                'title' => 'Buka dan tutup shift kasir',
                'summary' => 'Catat modal awal, cocokkan uang di laci saat tutup, dan lihat selisih kas otomatis.',
                'minutes' => 5,
                'steps' => [
                    ['Buka shift', 'Buka menu Shift Kasir atau langsung Kasir / POS. Pilih Outlet dan isi Modal Awal (uang tunai) yang ada di laci.', 'Hitung uang laci sebelum toko buka.'],
                    ['Jalankan transaksi', 'Semua penjualan selama shift tercatat otomatis. Penjualan tunai ikut dihitung ke uang yang seharusnya ada di laci.', null],
                    ['Mulai tutup shift', 'Di akhir jam kerja, buka Shift Kasir lalu tekan Tutup Shift.', null],
                    ['Hitung kas akhir', 'Hitung uang tunai di laci, lalu isi Cash Aktual di Laci. Yoka menghitung yang Seharusnya di laci dari modal awal ditambah penjualan tunai.', 'QRIS, transfer, dan metode non-tunai tidak masuk laci.'],
                    ['Cek selisih', 'Bandingkan hasilnya. Kalau ada selisih, cek lagi transaksi tunai hari itu.', 'Cocokkan QRIS dan transfer dengan mutasi di aplikasi bank atau e-wallet.'],
                    ['Lihat riwayat', 'Semua shift tersimpan di Riwayat Shift dan Laporan Shift.', null],
                ],
                'faq' => [
                    ['Kenapa kasir diminta buka shift?', 'Transaksi di Kasir / POS butuh shift aktif agar uang kas bisa direkap.'],
                    ['Satu outlet bisa banyak kasir?', 'Bisa. Tambahkan akun di Pengaturan, lalu Manajemen User.'],
                ],
            ],

            'stok-bahan-dan-resep' => [
                'category' => 'Operasional',
                'title' => 'Stok bahan dan resep otomatis',
                'summary' => 'Hubungkan menu ke bahan baku supaya stok berkurang sendiri setiap ada penjualan.',
                'minutes' => 10,
                'steps' => [
                    ['Tambah bahan baku', 'Buka Operasional, lalu Bahan Baku. Isi Nama Bahan, Satuan, Stok Saat Ini, dan Stok Minimum.', 'Stok Minimum dipakai sebagai batas peringatan stok menipis.'],
                    ['Isi harga bahan', 'Isi harga per satuan agar biaya bahan per menu bisa dihitung.', null],
                    ['Susun resep', 'Buka Resep, pilih menu, lalu masukkan bahan dan jumlah yang dipakai untuk satu porsi. Contoh: Es Kopi Susu memakai 18 gram kopi dan 120 ml susu.', null],
                    ['Aktifkan pantau stok', 'Di form produk, centang Pantau stok via resep.', null],
                    ['Jual seperti biasa', 'Setiap order dibayar, stok bahan dipotong otomatis sesuai resep.', null],
                    ['Cek pergerakan', 'Buka Pergerakan Stok untuk melihat stok masuk, keluar, penyesuaian, dan penjualan.', 'Lakukan stok opname rutin dengan mengisi Stok aktual baru bila ada selisih.'],
                ],
                'faq' => [
                    ['Bisa dapat peringatan stok menipis?', 'Bisa, lewat Bot Telegram bila sudah dihubungkan.'],
                    ['Satuan apa saja yang bisa dipakai?', 'Pilih satuan yang sesuai saat menambah bahan, misalnya gram, ml, atau pcs.'],
                ],
            ],

            'membaca-laporan-penjualan' => [
                'category' => 'Laporan',
                'title' => 'Membaca laporan penjualan harian',
                'summary' => 'Lihat omzet, produk terlaris, dan estimasi profit, lalu export ke Excel atau PDF.',
                'minutes' => 5,
                'steps' => [
                    ['Buka Laporan Penjualan', 'Di menu samping, buka Laporan, lalu Laporan Penjualan.', null],
                    ['Pilih periode dan outlet', 'Atur tanggal (misalnya hari ini atau minggu ini) dan outlet yang ingin dilihat.', null],
                    ['Baca angka utama', 'Lihat omzet, jumlah transaksi, dan metode pembayaran yang dipakai pelanggan.', null],
                    ['Cek Produk Terlaris', 'Buka Produk Terlaris untuk tahu menu mana yang paling laku.', 'Pastikan menu terlaris tidak pernah kehabisan bahan.'],
                    ['Lihat Estimasi Profit', 'Buka Estimasi Profit untuk membandingkan harga jual dan modal per menu.', 'Hasilnya akurat bila Harga Modal produk sudah diisi.'],
                    ['Export laporan', 'Unduh laporan ke Excel, CSV, atau PDF untuk arsip atau dikirim ke tim.', null],
                ],
                'faq' => [
                    ['Laporan apa lagi yang tersedia?', 'Laporan Kasir, Laporan Shift, Laporan Inventory, Laba Rugi, Laporan Pajak, dan Retur Transaksi.'],
                    ['Bisa tanya laporan pakai chat?', 'Bisa, lewat Tanya Yoka di aplikasi atau Bot Telegram.'],
                ],
            ],

            'hubungkan-bot-telegram' => [
                'category' => 'Pantau Bisnis',
                'title' => 'Hubungkan Bot Telegram',
                'summary' => 'Terima ringkasan toko dan peringatan langsung di Telegram, lalu tanya omzet dengan bahasa biasa.',
                'minutes' => 3,
                'steps' => [
                    ['Buka Bot Telegram', 'Di menu samping, buka Pengaturan, lalu Bot Telegram.', 'Kalau tertulis fitur belum diaktifkan, hubungi support Yoka.'],
                    ['Tekan Hubungkan Telegram', 'Yoka membuat tautan khusus untuk bisnismu. Tautan berlaku 15 menit.', null],
                    ['Tekan Start di Telegram', 'Telegram terbuka di chat Yoka POS Bot. Tekan Start, lalu kembali ke Yoka dan tekan Saya sudah menekan Start.', null],
                    ['Kirim pesan tes', 'Tekan Kirim pesan tes untuk memastikan chat sudah tersambung.', null],
                    ['Pilih notifikasi', 'Atur notifikasi yang ingin diterima, lalu tekan Simpan pengaturan.', null],
                    ['Mulai bertanya', 'Ketik pertanyaan biasa di Telegram, misalnya "omzet hari ini berapa?" atau "omzet kemarin berapa?".', null],
                ],
                'faq' => [
                    ['Apa saja yang dikirim bot?', 'Ringkasan harian, stok menipis, shift yang belum ditutup, serta pesanan lunas, void, dan refund.'],
                    ['Bagaimana cara memutus?', 'Buka Bot Telegram di Yoka lalu tekan Putuskan.'],
                ],
            ],

            'lupa-password' => [
                'category' => 'Akun',
                'title' => 'Lupa password',
                'summary' => 'Atur ulang password lewat tautan yang dikirim ke email akun.',
                'minutes' => 2,
                'steps' => [
                    ['Buka halaman masuk', 'Di halaman Masuk yokapos.com, tekan Lupa password?.', null],
                    ['Isi email akun', 'Pada halaman Atur Ulang Password, isi email yang kamu pakai saat mendaftar, lalu kirim.', 'Kalau dulu daftar pakai Google, isi email Google tersebut.'],
                    ['Buka tautan di email', 'Buka email dari Yoka dan tekan tautan atur ulang password. Cek folder Spam bila belum masuk.', null],
                    ['Buat password baru', 'Isi password baru minimal 8 karakter, ulangi, lalu simpan. Setelah itu masuk dengan password baru.', null],
                ],
                'faq' => [
                    ['Kasir lupa PIN?', 'PIN kasir diatur pemilik atau admin di Manajemen User, Edit, PIN Kasir. Tidak perlu email.'],
                    ['Tim Yoka bisa kirim password saya?', 'Tidak. Demi keamanan, password hanya bisa diatur ulang lewat email akun sendiri.'],
                ],
            ],

            'tambah-kasir-dan-pin' => [
                'category' => 'Akun',
                'title' => 'Tambah kasir dan login pakai PIN',
                'summary' => 'Buat akun staf dengan role yang tepat, lalu kasir masuk di tablet cukup dengan PIN.',
                'minutes' => 5,
                'steps' => [
                    ['Buka Manajemen User', 'Di menu samping, buka Manajemen User, lalu tekan Tambah user.', 'Hanya pemilik dan admin yang bisa menambah user.'],
                    ['Isi data dan role', 'Isi Nama Lengkap, Email, dan No. HP. Pilih Role: Admin, Kasir, Dapur, atau Gudang, lalu pilih Outlet tempat dia bekerja.', 'Kasir hanya melihat layar kasir; Admin bisa membuka laporan.'],
                    ['Atur password dan PIN', 'Isi Password minimal 8 karakter. Di bagian PIN Kasir, isi PIN angka untuk login cepat di tablet.', null],
                    ['Pasangkan tablet ke outlet', 'Di tablet kasir, buka yokapos.com/kasir, lalu isi Kode Outlet pada halaman Pasangkan Perangkat.', 'Kode Outlet ada di menu Outlet.'],
                    ['Kasir masuk dengan PIN', 'Setelah tablet terpasang, kasir cukup memasukkan PIN untuk membuka kasir.', null],
                ],
                'faq' => [
                    ['Muncul "Belum ada kasir dengan PIN"?', 'Atur PIN dulu di Manajemen User, Edit, PIN Kasir.'],
                    ['Staf keluar kerja?', 'Edit user tersebut dan matikan User aktif, atau hapus PIN-nya supaya tidak bisa masuk lewat tablet.'],
                ],
            ],

            'tambah-outlet' => [
                'category' => 'Persiapan',
                'title' => 'Tambah outlet atau cabang',
                'summary' => 'Kelola beberapa cabang dalam satu akun, dengan menu, kasir, dan laporan per outlet.',
                'minutes' => 3,
                'steps' => [
                    ['Buka menu Outlet', 'Di menu samping, buka Outlet, lalu tambah outlet baru.', null],
                    ['Isi informasi outlet', 'Isi Nama Outlet, Kode Outlet (misalnya CB-01), No. Telepon, dan Alamat, lalu pastikan Outlet aktif tercentang.', 'Kode Outlet dipakai untuk memasangkan tablet kasir.'],
                    ['Atur produk per outlet', 'Di Produk / Menu, pilih Outlet tempat tiap produk dijual.', null],
                    ['Tempatkan staf', 'Di Manajemen User, pilih Outlet untuk tiap kasir.', null],
                ],
                'faq' => [
                    ['Berapa outlet yang bisa dipakai?', 'Jumlah outlet mengikuti paket langganan. Lihat perbandingannya di menu Langganan.'],
                ],
            ],

            'void-dan-refund-order' => [
                'category' => 'Kasir',
                'title' => 'Membatalkan (void) atau refund order',
                'summary' => 'Batalkan transaksi yang salah input atau kembalikan uang pembeli, dengan alasan yang tercatat.',
                'minutes' => 2,
                'steps' => [
                    ['Buka Riwayat Order', 'Di menu samping, buka Riwayat Order, lalu buka order yang sudah lunas.', 'Hanya pemilik dan admin yang bisa membatalkan atau refund, supaya transaksi lunas tidak bisa dihapus kasir.'],
                    ['Pilih tindakan', 'Tekan Batalkan Order untuk transaksi yang salah input, atau Refund Order bila uang dikembalikan ke pembeli.', 'Batalkan Order mengembalikan stok.'],
                    ['Isi alasan', 'Alasan wajib diisi, misalnya salah input menu atau pembeli batal.', 'Alasan tercatat di detail order dan laporan Retur Transaksi.'],
                    ['Cek laporan', 'Order yang dibatalkan atau di-refund tidak dihitung sebagai penjualan. Bila Bot Telegram aktif, pemilik menerima notifikasinya.', null],
                ],
                'faq' => [
                    ['Bisa void order yang belum dibayar?', 'Tombol ini hanya untuk order yang sudah lunas.'],
                ],
            ],

            'ekspor-laporan-excel-pdf' => [
                'category' => 'Laporan',
                'title' => 'Ekspor laporan ke Excel atau PDF',
                'summary' => 'Unduh laporan penjualan, produk, shift, dan profit untuk arsip atau pembukuan.',
                'minutes' => 2,
                'steps' => [
                    ['Buka laporan', 'Di menu samping, buka laporan yang dibutuhkan, misalnya Laporan Penjualan, Produk Terlaris, Laporan Shift, atau Estimasi Profit.', 'Laporan hanya bisa dibuka pemilik dan admin.'],
                    ['Atur periode', 'Pilih rentang tanggal dan outlet pada filter di atas laporan.', null],
                    ['Tekan Ekspor', 'Tekan Ekspor, lalu pilih Excel (.xlsx) atau PDF. File langsung terunduh.', null],
                ],
                'faq' => [
                    ['Mau data otomatis ke Google Sheets?', 'Bisa, lewat halaman Integrasi (feed penjualan ke Google Sheets).'],
                ],
            ],

            'qr-meja-pesan-dari-meja' => [
                'category' => 'Kasir',
                'title' => 'QR Meja: pelanggan pesan dari meja',
                'summary' => 'Pelanggan scan QR di meja dan memesan sendiri; kasir menerima dan menagih.',
                'minutes' => 6,
                'steps' => [
                    ['Buat area dan meja', 'Di menu samping, buka QR Meja. Tambah area bila perlu, lalu isi nomor meja (misalnya A01) dan tekan Buat meja dan QR.', null],
                    ['Cetak QR', 'Tekan Cetak dan tempel QR di meja. QR bersifat tetap; tiap kunjungan dibuat sebagai sesi baru.', null],
                    ['Terima pesanan', 'Pesanan masuk ke Pesanan QR. Periksa, lalu tekan Terima pesanan atau Tolak. Stok baru berkurang dan tiket masuk dapur setelah diterima.', null],
                    ['Tagih pembayaran', 'Pilih Tunai, QRIS di kasir, atau Debit / EDC, lalu Konfirmasi bayar.', null],
                    ['Tutup sesi meja', 'Setelah pelanggan selesai, tekan Tutup sesi meja di QR Meja.', null],
                ],
                'faq' => [
                    ['QR bocor atau disalahgunakan?', 'Tekan Ganti URL pada meja tersebut, lalu cetak ulang QR-nya.'],
                ],
            ],

            'catat-biaya-hutang-piutang' => [
                'category' => 'Laporan',
                'title' => 'Catat biaya, hutang, dan piutang',
                'summary' => 'Catat pengeluaran operasional, pembelian ke supplier, dan tagihan supaya laba rugi akurat.',
                'minutes' => 5,
                'steps' => [
                    ['Catat biaya', 'Buka Biaya, tekan Catat biaya, lalu isi tanggal, kategori, outlet, dan jumlah (misalnya sewa, listrik, gaji).', 'Biaya masuk ke perhitungan Laba Rugi.'],
                    ['Catat pembelian dan hutang', 'Buka Pembelian, tekan Catat pembelian, pilih Supplier dan jatuh tempo. Pembelian yang belum lunas muncul di Hutang.', null],
                    ['Bayar hutang', 'Di Hutang, tekan Bayar pada tagihan yang dilunasi. Sisa dan status jatuh tempo diperbarui.', null],
                    ['Catat piutang', 'Buka Piutang, tekan Catat piutang, isi pihak, jumlah, dan jatuh tempo untuk uang yang belum diterima.', null],
                    ['Lihat hasilnya', 'Buka Laba Rugi untuk melihat penjualan dikurangi modal dan biaya.', null],
                ],
                'faq' => [
                    ['Salah catat biaya?', 'Biaya yang salah bisa dibatalkan; statusnya menjadi Dibatalkan dan tidak dihitung.'],
                ],
            ],

            'cara-berlangganan' => [
                'category' => 'Akun',
                'title' => 'Cara berlangganan setelah trial',
                'summary' => 'Pilih paket dan bayar online agar toko tetap berjalan setelah trial 7 hari selesai.',
                'minutes' => 3,
                'steps' => [
                    ['Cek sisa trial', 'Bar di atas aplikasi menampilkan Masa coba gratis dan sisa harinya.', null],
                    ['Tekan Pilih Paket', 'Tekan tombol Pilih Paket di bar tersebut. Tombol ini hanya terlihat oleh pemilik (owner).', null],
                    ['Bandingkan paket', 'Lihat harga, jumlah outlet, dan jumlah user di tiap paket, lalu pilih yang sesuai.', 'Pilih berdasarkan jumlah outlet dan kasir yang kamu punya sekarang.'],
                    ['Bayar online', 'Selesaikan pembayaran lewat Midtrans dengan metode yang tersedia di halaman pembayaran.', null],
                    ['Paket aktif', 'Setelah pembayaran berhasil, paket aktif selama 30 hari dan statusnya terlihat di halaman Status Langganan.', null],
                ],
                'faq' => [
                    ['Data trial hilang setelah berlangganan?', 'Tidak. Semua menu, transaksi, dan laporan tetap tersimpan.'],
                    ['Butuh bantuan memilih paket?', 'Chat tim Yoka lewat WhatsApp, kami bantu pilihkan.'],
                ],
            ],
        ];
    }
}
