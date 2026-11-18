<?php

namespace App\Http\Controllers;

/**
 * Artikel SEO (yokapos.com/artikel): edukasi untuk pemilik kafe, resto, dan toko.
 * Angka di contoh adalah ilustrasi berlabel, bukan data pelanggan. Klaim fitur Yoka hanya
 * yang sudah aktif (lihat AgentBrain::productFacts).
 *
 * Format section: ['h' => judul H2, 'p' => [paragraf...], 'list' => [poin...] (opsional), 'ol' => bool (opsional)]
 */
class ArticleController extends Controller
{
    public function index()
    {
        return view('articles.index', ['articles' => self::articles()]);
    }

    public function show(string $slug)
    {
        $articles = self::articles();
        abort_unless(isset($articles[$slug]), 404);

        return view('articles.show', [
            'article' => $articles[$slug],
            'slug' => $slug,
            'related' => collect($articles)->except($slug)->take(3)->all(),
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    public static function articles(): array
    {
        return [
            'cara-menghitung-hpp-menu-cafe' => [
                'title' => 'Cara Menghitung HPP Menu Cafe (Rumus dan Contoh Lengkap)',
                'description' => 'Rumus HPP per porsi untuk menu kopi dan makanan, contoh hitungan langkah demi langkah, dan kesalahan yang sering bikin margin bocor.',
                'keyword' => 'cara menghitung HPP menu cafe',
                'category' => 'Keuangan',
                'image' => 'images/features/stok-resep.webp',
                'minutes' => 7,
                'published' => '2026-11-19',
                'intro' => 'HPP (harga pokok penjualan) per porsi adalah biaya bahan yang habis untuk membuat satu menu. Tanpa angka ini, harga jual hanya tebakan dan kamu tidak tahu menu mana yang benar-benar menghasilkan untung.',
                'sections' => [
                    ['h' => 'Apa itu HPP menu?', 'p' => [
                        'Dalam bisnis F&B, HPP menu biasanya dihitung dari bahan baku yang dipakai untuk satu porsi: kopi, susu, gula, cup, sedotan, dan seterusnya. Inilah dasar untuk menentukan harga jual dan membaca laba kotor.',
                        'Biaya tetap seperti sewa, gaji, dan listrik tidak dimasukkan ke HPP per porsi. Biaya itu dihitung terpisah saat menyusun laba rugi bulanan.',
                    ]],
                    ['h' => 'Rumus HPP per porsi', 'p' => [
                        'Rumusnya sederhana: HPP per porsi = jumlah biaya setiap bahan yang dipakai dalam satu porsi.',
                        'Biaya satu bahan = (harga beli ÷ isi kemasan) × takaran per porsi.',
                    ]],
                    ['h' => 'Contoh: menghitung HPP es kopi susu', 'p' => [
                        'Angka di bawah ini ilustrasi. Ganti dengan harga belanja tokomu sendiri.',
                    ], 'list' => [
                        'Kopi bubuk Rp 200.000 per 1.000 gram, dipakai 18 gram: Rp 200 × 18 = Rp 3.600',
                        'Susu UHT Rp 20.000 per 1.000 ml, dipakai 120 ml: Rp 20 × 120 = Rp 2.400',
                        'Gula aren cair Rp 30.000 per 1.000 ml, dipakai 20 ml: Rp 30 × 20 = Rp 600',
                        'Es batu, cup, tutup, dan sedotan: Rp 1.500',
                        'Total HPP per porsi: Rp 8.100',
                    ]],
                    ['h' => 'Dari HPP ke persentase food cost', 'p' => [
                        'Food cost % = HPP ÷ harga jual × 100%. Kalau es kopi susu dijual Rp 25.000, food cost-nya Rp 8.100 ÷ Rp 25.000 = 32,4%.',
                        'Banyak pelaku F&B memakai patokan food cost sekitar 25 sampai 35% untuk minuman dan makanan. Patokan ini bukan aturan mutlak; yang penting kamu tahu angkanya per menu dan memantaunya rutin.',
                    ]],
                    ['h' => 'Kesalahan yang sering terjadi', 'list' => [
                        'Lupa menghitung kemasan (cup, paper bag, sedotan) padahal nilainya bisa ribuan rupiah per porsi.',
                        'Memakai harga beli lama padahal harga bahan sudah naik.',
                        'Takaran di dapur tidak sama dengan takaran di hitungan, misalnya barista menuang susu "kira-kira".',
                        'Tidak memperhitungkan bahan yang terbuang (susu basi, roti kedaluwarsa).',
                    ]],
                    ['h' => 'Cara praktis memantau HPP setiap hari', 'p' => [
                        'Menghitung di kertas cukup untuk awal, tetapi cepat ketinggalan saat harga bahan berubah. Di Yoka POS, kamu bisa mengisi Harga Modal per produk dan menyusun resep per menu, sehingga stok bahan berkurang otomatis dan laporan Estimasi Profit menunjukkan margin tiap menu.',
                    ]],
                ],
                'faq' => [
                    ['Apakah gaji barista masuk HPP per porsi?', 'Umumnya tidak. HPP per porsi berisi bahan baku dan kemasan. Gaji masuk biaya operasional bulanan.'],
                    ['Seberapa sering HPP perlu dihitung ulang?', 'Setiap kali harga bahan utama berubah, atau minimal sebulan sekali.'],
                ],
                'links' => [['Stok & Resep', 'features.show', 'stok-resep'], ['Panduan stok bahan dan resep', 'guides.show', 'stok-bahan-dan-resep']],
            ],

            'cara-menentukan-harga-jual-menu' => [
                'title' => 'Cara Menentukan Harga Jual Menu agar Cafe Tetap Untung',
                'description' => 'Tiga metode menentukan harga jual menu cafe dan resto: dari food cost, dari margin, dan dari harga pasar, lengkap dengan contoh.',
                'keyword' => 'cara menentukan harga jual menu',
                'category' => 'Keuangan',
                'image' => 'images/features/transaksi-kasir.webp',
                'minutes' => 6,
                'published' => '2026-11-19',
                'intro' => 'Harga jual yang terlalu murah membuat toko ramai tapi uangnya tidak ke mana-mana. Terlalu mahal, pelanggan pindah ke sebelah. Kuncinya: mulai dari HPP, lalu sesuaikan dengan pasar.',
                'sections' => [
                    ['h' => 'Langkah pertama: ketahui HPP setiap menu', 'p' => [
                        'Semua metode di bawah membutuhkan HPP per porsi. Kalau belum punya, hitung dulu bahan dan kemasan per menu.',
                    ]],
                    ['h' => 'Metode 1: dari target food cost', 'p' => [
                        'Harga jual = HPP ÷ target food cost. Contoh ilustrasi: HPP Rp 8.000 dengan target food cost 30% menghasilkan harga Rp 8.000 ÷ 0,30 = Rp 26.700, lalu dibulatkan menjadi Rp 27.000.',
                    ]],
                    ['h' => 'Metode 2: dari target margin rupiah', 'p' => [
                        'Tentukan berapa rupiah laba kotor yang kamu mau dari satu porsi. Kalau HPP Rp 8.000 dan kamu ingin laba kotor Rp 15.000 per porsi, harga jual minimal Rp 23.000.',
                        'Metode ini cocok untuk menu bervolume besar yang harganya sensitif.',
                    ]],
                    ['h' => 'Metode 3: bandingkan dengan harga pasar', 'p' => [
                        'Cek harga menu sejenis di sekitar lokasimu dan di aplikasi pesan-antar. Kalau hasil hitunganmu jauh di atas pasar, cari cara menurunkan HPP: ganti pemasok, sesuaikan takaran, atau ubah ukuran cup.',
                    ]],
                    ['h' => 'Hal yang sering terlupa', 'list' => [
                        'Potongan komisi aplikasi pesan-antar: banyak toko memasang harga berbeda untuk online.',
                        'Biaya transaksi non-tunai bila ada.',
                        'Pajak dan service charge: putuskan sejak awal apakah harga sudah termasuk atau belum, lalu tampilkan jelas di struk.',
                        'Menu pancingan boleh bermargin tipis, asal ada menu pendamping yang marginnya tebal.',
                    ]],
                    ['h' => 'Evaluasi harga setiap bulan', 'p' => [
                        'Harga yang tepat hari ini bisa salah bulan depan. Pantau menu terlaris dan margin per menu secara rutin. Di Yoka POS, laporan Produk Terlaris dan Estimasi Profit membantu kamu melihat menu mana yang laku tetapi marginnya tipis.',
                    ]],
                ],
                'faq' => [
                    ['Berapa food cost yang ideal?', 'Patokan yang sering dipakai sekitar 25 sampai 35%, tetapi sesuaikan dengan konsep dan harga sewa tempatmu.'],
                    ['Bolehkah harga online beda dengan harga di toko?', 'Boleh dan umum dilakukan untuk menutup komisi platform, asal konsisten dan jelas bagi pelanggan.'],
                ],
                'links' => [['Laporan Bisnis', 'features.show', 'laporan'], ['Panduan atur menu dan harga', 'guides.show', 'atur-menu-kategori-harga']],
            ],

            'aplikasi-kasir-cafe' => [
                'title' => 'Aplikasi Kasir Cafe: 8 Fitur yang Wajib Ada Sebelum Memilih',
                'description' => 'Checklist memilih aplikasi kasir untuk cafe dan resto: transaksi cepat, QRIS, struk, shift, stok resep, laporan, hak akses, dan dukungan.',
                'keyword' => 'aplikasi kasir cafe',
                'category' => 'Operasional',
                'image' => 'images/hero/slide-kasir.webp',
                'minutes' => 6,
                'published' => '2026-11-19',
                'intro' => 'Aplikasi kasir bukan sekadar pengganti mesin hitung. Untuk cafe, aplikasi yang tepat mempercepat antrean, menjaga uang kas, dan memberi tahu menu mana yang benar-benar menguntungkan.',
                'sections' => [
                    ['h' => 'Checklist fitur aplikasi kasir cafe', 'ol' => true, 'list' => [
                        'Transaksi cepat dari satu layar: pilih menu, varian, topping, dan catatan tanpa pindah halaman.',
                        'Pembayaran beragam: tunai, QRIS, transfer, debit, hingga split payment.',
                        'Struk yang fleksibel: printer thermal 58/80 mm, Bluetooth di Android, atau PDF.',
                        'Shift kasir dengan modal awal dan rekap kas akhir, supaya selisih uang langsung terlihat.',
                        'Stok bahan dan resep: stok berkurang otomatis setiap penjualan.',
                        'Laporan yang mudah dibaca: omzet, produk terlaris, dan estimasi profit, bisa diekspor.',
                        'Hak akses per peran: owner, admin, kasir, dapur, dan gudang tidak melihat hal yang sama.',
                        'Dukungan yang responsif saat ada kendala di jam ramai.',
                    ]],
                    ['h' => 'Pertanyaan sebelum berlangganan', 'list' => [
                        'Apakah bisa dipakai di perangkat yang sudah ada (laptop, tablet, HP)?',
                        'Apakah ada masa coba gratis untuk menguji alur kasir tokomu?',
                        'Apakah data penjualan bisa diekspor kalau suatu saat pindah?',
                        'Berapa biaya bulanan untuk jumlah outlet dan kasir yang kamu punya?',
                    ]],
                    ['h' => 'Coba dulu dengan transaksi nyata', 'p' => [
                        'Cara paling jujur menilai aplikasi kasir adalah mencobanya di jam ramai. Masukkan tiga menu terlaris, buka shift, lalu layani beberapa pesanan sungguhan.',
                        'Yoka POS menyediakan trial gratis 7 hari dan berjalan di browser, jadi bisa langsung dicoba di laptop, tablet, atau HP yang sudah kamu punya.',
                    ]],
                ],
                'faq' => [
                    ['Apakah aplikasi kasir harus pakai tablet khusus?', 'Tidak harus. Aplikasi berbasis browser seperti Yoka bisa dipakai di laptop, tablet, atau HP.'],
                    ['Apakah bisa untuk lebih dari satu outlet?', 'Bisa, pilih paket yang sesuai jumlah outlet dan user.'],
                ],
                'links' => [['Transaksi Kasir', 'features.show', 'transaksi-kasir'], ['Mulai pakai Yoka POS', 'guides.show', 'mulai-pakai-yoka-pos']],
            ],

            'cara-mengelola-stok-bahan-baku-cafe' => [
                'title' => 'Cara Mengelola Stok Bahan Baku Cafe agar Tidak Bocor',
                'description' => 'Langkah mengelola stok bahan baku cafe: daftar bahan, stok minimum, resep per menu, stok opname, dan cara membaca selisih.',
                'keyword' => 'cara mengelola stok bahan baku cafe',
                'category' => 'Operasional',
                'image' => 'images/features/kitchen-display.webp',
                'minutes' => 7,
                'published' => '2026-11-19',
                'intro' => 'Stok yang tidak tercatat adalah uang yang tidak terlihat. Susu yang basi, kopi yang "kurang", atau bahan yang habis di jam ramai, semuanya langsung menggerus laba.',
                'sections' => [
                    ['h' => '1. Buat daftar bahan dengan satuan yang konsisten', 'p' => [
                        'Catat setiap bahan dengan satuan pakai, bukan satuan beli. Susu dicatat dalam ml, kopi dalam gram, cup dalam pcs. Ini membuat hitungan resep dan stok tidak membingungkan.',
                    ]],
                    ['h' => '2. Tentukan stok minimum', 'p' => [
                        'Stok minimum adalah batas aman sebelum kamu wajib belanja lagi. Hitung dari pemakaian rata-rata harian dikali waktu tunggu pemasok, lalu tambah cadangan.',
                    ]],
                    ['h' => '3. Susun resep untuk setiap menu', 'p' => [
                        'Resep menghubungkan menu dengan bahan: satu es kopi susu memakai sekian gram kopi dan sekian ml susu. Dengan resep, setiap penjualan otomatis mengurangi stok bahan.',
                    ]],
                    ['h' => '4. Lakukan stok opname rutin', 'p' => [
                        'Hitung stok fisik secara berkala, misalnya mingguan untuk bahan utama. Bandingkan dengan stok di sistem.',
                    ], 'list' => [
                        'Selisih kecil dan konsisten: kemungkinan takaran di dapur lebih banyak dari resep.',
                        'Selisih besar mendadak: cek bahan rusak, salah input pembelian, atau kehilangan.',
                        'Catat alasan setiap penyesuaian agar polanya terlihat.',
                    ]],
                    ['h' => '5. Pakai peringatan stok menipis', 'p' => [
                        'Daripada mengecek gudang setiap pagi, biarkan sistem yang mengingatkan. Di Yoka POS, bahan yang menyentuh stok minimum bisa dikirim sebagai peringatan lewat Bot Telegram ke HP pemilik.',
                    ]],
                ],
                'faq' => [
                    ['Seberapa sering stok opname dilakukan?', 'Bahan cepat habis atau mahal sebaiknya mingguan; bahan lain cukup bulanan.'],
                    ['Apa itu FIFO?', 'First in, first out: bahan yang masuk lebih dulu dipakai lebih dulu, supaya tidak kedaluwarsa.'],
                ],
                'links' => [['Stok & Resep', 'features.show', 'stok-resep'], ['Panduan stok bahan dan resep', 'guides.show', 'stok-bahan-dan-resep']],
            ],

            'cara-mencegah-selisih-kas-kasir' => [
                'title' => 'Cara Mencegah Selisih Kas Kasir di Cafe dan Toko',
                'description' => 'Penyebab selisih uang kasir dan cara mencegahnya: modal awal, pemisahan non-tunai, tutup shift, hak akses, dan kebiasaan rekap harian.',
                'keyword' => 'selisih kas kasir',
                'category' => 'Operasional',
                'image' => 'images/features/shift-karyawan.webp',
                'minutes' => 6,
                'published' => '2026-11-19',
                'intro' => 'Uang di laci kurang Rp 50.000 setiap beberapa hari terlihat kecil, tetapi dalam setahun bisa jutaan rupiah. Kebanyakan selisih bukan karena niat buruk, melainkan karena alur kas yang tidak jelas.',
                'sections' => [
                    ['h' => 'Penyebab selisih kas yang paling umum', 'list' => [
                        'Modal awal tidak dicatat, sehingga tidak ada titik awal hitungan.',
                        'Pembayaran QRIS atau transfer ikut dihitung sebagai uang di laci.',
                        'Salah kembalian di jam ramai.',
                        'Transaksi dibatalkan atau diubah tanpa jejak.',
                        'Satu laci dipakai bergantian oleh beberapa kasir tanpa pergantian shift.',
                    ]],
                    ['h' => 'Gunakan sistem shift', 'p' => [
                        'Setiap kasir membuka shift dengan mencatat modal awal, lalu menutup shift dengan menghitung uang fisik. Sistem menghitung uang yang seharusnya ada: modal awal ditambah penjualan tunai. Selisihnya langsung terlihat per shift dan per kasir.',
                    ]],
                    ['h' => 'Pisahkan tunai dan non-tunai', 'p' => [
                        'QRIS, transfer, dan kartu tidak masuk laci. Cocokkan pembayaran non-tunai dengan mutasi di aplikasi bank atau e-wallet, bukan dengan uang di laci.',
                    ]],
                    ['h' => 'Batasi siapa yang bisa membatalkan transaksi', 'p' => [
                        'Void dan refund sebaiknya hanya bisa dilakukan peran tertentu, dan setiap kejadian tercatat. Pemilik juga sebaiknya mendapat notifikasi saat ada void atau refund.',
                    ]],
                    ['h' => 'Biasakan rekap di akhir hari', 'ol' => true, 'list' => [
                        'Tutup semua shift.',
                        'Cek selisih per kasir.',
                        'Cocokkan total non-tunai dengan mutasi.',
                        'Catat penyebab selisih bila ada, lalu bahas dengan tim keesokan harinya.',
                    ]],
                ],
                'faq' => [
                    ['Berapa selisih yang masih wajar?', 'Idealnya nol. Tetapkan batas toleransi kecil dan selidiki setiap selisih di atasnya.'],
                    ['Perlukah kasir menghitung uang di depan supervisor?', 'Sangat disarankan saat tutup shift, terutama untuk toko dengan banyak kasir.'],
                ],
                'links' => [['Shift & Karyawan', 'features.show', 'shift-karyawan'], ['Panduan buka dan tutup shift', 'guides.show', 'buka-tutup-shift-kasir']],
            ],

            'laporan-keuangan-sederhana-cafe' => [
                'title' => 'Laporan Keuangan Sederhana untuk Cafe: Yang Wajib Dicek Tiap Hari',
                'description' => 'Laporan keuangan sederhana untuk pemilik cafe dan resto: omzet, metode bayar, menu terlaris, margin, biaya, dan laba rugi bulanan.',
                'keyword' => 'laporan keuangan sederhana cafe',
                'category' => 'Keuangan',
                'image' => 'images/about/dashboard-tim.webp',
                'minutes' => 6,
                'published' => '2026-11-19',
                'intro' => 'Pemilik cafe tidak perlu jadi akuntan untuk tahu kondisi bisnisnya. Cukup kebiasaan membaca beberapa angka yang tepat, di waktu yang tepat.',
                'sections' => [
                    ['h' => 'Dicek setiap hari (5 menit)', 'list' => [
                        'Omzet hari ini dibanding hari yang sama minggu lalu.',
                        'Jumlah transaksi dan rata-rata nilai per transaksi.',
                        'Komposisi metode bayar: tunai, QRIS, transfer.',
                        'Selisih kas dari tutup shift.',
                    ]],
                    ['h' => 'Dicek setiap minggu', 'list' => [
                        'Menu terlaris dan menu yang jarang laku.',
                        'Margin per menu: menu laris dengan margin tipis perlu dievaluasi harganya.',
                        'Bahan yang paling sering habis atau terbuang.',
                    ]],
                    ['h' => 'Dicek setiap bulan', 'p' => [
                        'Susun laba rugi sederhana: pendapatan dikurangi HPP menjadi laba kotor, lalu dikurangi biaya operasional (sewa, gaji, listrik, internet, pemasaran) menjadi laba bersih.',
                        'Bedakan omzet dengan laba. Omzet besar belum tentu untung kalau HPP dan biaya operasionalnya juga besar.',
                    ]],
                    ['h' => 'Biar tidak repot', 'p' => [
                        'Catat semua transaksi di aplikasi kasir, catat biaya di tempat yang sama, dan biarkan laporan tersusun otomatis. Di Yoka POS tersedia laporan penjualan, produk terlaris, estimasi profit, dan laba rugi yang bisa diekspor ke Excel atau PDF. Ringkasan harian juga bisa dikirim ke Telegram setiap malam.',
                    ]],
                ],
                'faq' => [
                    ['Apa beda omzet dan laba?', 'Omzet adalah total penjualan. Laba adalah sisa setelah dikurangi HPP dan biaya operasional.'],
                    ['Perlukah memisahkan rekening pribadi dan usaha?', 'Sangat disarankan, supaya arus kas usaha terbaca jelas.'],
                ],
                'links' => [['Laporan Bisnis', 'features.show', 'laporan'], ['Panduan membaca laporan penjualan', 'guides.show', 'membaca-laporan-penjualan']],
            ],
        ];
    }
}
