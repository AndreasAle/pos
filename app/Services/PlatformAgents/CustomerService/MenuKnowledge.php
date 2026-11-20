<?php

namespace App\Services\PlatformAgents\CustomerService;

use Illuminate\Support\Str;

/**
 * Domain 2 (lanjutan): pengetahuan Adit tentang SETIAP menu aplikasi Yoka POS sampai isi di dalamnya.
 *
 * Sumber: menu samping (resources/views/layouts/app.blade.php), routes/web.php, dan label asli di
 * tiap halaman (resources/views/**). Diaudit 2026-11-19. Kalau label di aplikasi berubah, ubah juga di sini;
 * tes AditMenuKnowledgeTest memastikan setiap menu samping punya entri.
 *
 * Ke prompt: peta() selalu dikirim (satu baris per menu, supaya Adit tahu semua menu ada),
 * detail() hanya untuk maksimal 2 menu yang paling cocok dengan pertanyaan.
 */
final class MenuKnowledge
{
    /**
     * [kunci => [nama, lokasi, siapa, kata_kunci, fungsi, isi, aturan]]
     *
     * @var array<string, array{0:string,1:string,2:string,3:string,4:string,5:string,6:string}>
     */
    public const MENUS = [
        'dashboard' => ['Dashboard', 'menu paling atas', 'semua role', 'dashboard beranda ringkasan hari ini omzet hari ini',
            'Ringkasan toko hari ini.',
            'Total Penjualan Hari Ini, Total Transaksi, Transaksi Terbaru, Performa Outlet Hari Ini (bisa pilih Semua Outlet), grafik 7 hari dibanding hari sebelumnya, Metode Pembayaran hari ini, Produk Terlaris.',
            'Grafik dan produk terlaris baru muncul setelah ada transaksi.'],
        'tanya_yoka' => ['Tanya Yoka', 'menu atas (bertanda AI)', 'pemilik dan admin', 'tanya yoka ai asisten analisis chat ai insight lampiran',
            'Asisten AI untuk bertanya soal penjualan dan operasional toko sendiri.',
            'Riwayat percakapan, Insight aktif (anomali penting), lampiran dokumen (PDF, Excel, CSV, gambar), kolom "Tanya apa saja soal bisnismu...". Bisa mengusulkan perubahan yang baru diterapkan setelah ditekan "Setujui & jalankan" dan tercatat di audit.',
            'Jawaban AI bisa keliru; cocokkan keputusan penting dengan laporan utama.'],
        'kasir' => ['Kasir / POS', 'menu atas', 'pemilik, admin, kasir', 'kasir pos transaksi jual bayar tunai cash qris split order hold simpan pesanan hold dulu varian add-on topping catatan pelanggan poin ongkir delivery take away bawa pulang dine in makan di tempat struk ganti kasir',
            'Layar jualan.',
            'Cari produk, tambah ke Pesanan, pilih Varian dan Add-on / Topping, Catatan per item. Tipe order: makan di tempat, bawa pulang, atau delivery (No. order platform, Ongkir, Alamat pengiriman). Pilih pelanggan (cari nama/no. HP) dan Tukar Poin. Pakai promo / voucher, Diskon Manual, Pajak dan Service otomatis. Bayar Cash atau QRIS; Bayar terpisah (sebagian cash, sebagian QRIS) dengan hitung kembalian. Simpan ke Hold untuk pesanan yang belum dibayar. Tombol Ganti Kasir, Tutup Shift, dan Cara cetak struk.',
            'Kasir harus membuka shift dulu. QRIS butuh QR diupload di Pengaturan QRIS; kalau diisi Kode QRIS Statis, kasir dapat QRIS dinamis dengan nominal terkunci.'],
        'riwayat_order' => ['Riwayat Order', 'menu atas', 'pemilik, admin, kasir (void/refund hanya pemilik dan admin)', 'riwayat order transaksi detail order void refund batalkan order struk ulang cetak ulang',
            'Semua transaksi toko dengan status dan metode bayar.',
            'Filter tanggal dan tab Semua, Lunas, Draft, Dibatalkan, Refund. Detail order: kasir, tipe order, pembayaran (termasuk split), outlet, pelanggan, item, diskon, pajak, service, ongkir, kembalian. Tombol Struk untuk cetak ulang, Batalkan Order dan Refund Order.',
            'Batalkan/Refund hanya untuk order lunas, alasan wajib, stok dikembalikan saat dibatalkan, tercatat di Retur Transaksi dan dikirim ke Bot Telegram.'],
        'shift' => ['Shift Kasir', 'menu atas', 'pemilik, admin, kasir', 'shift buka shift tutup shift modal awal laci kas selisih setoran',
            'Buka dan tutup shift kasir serta mencocokkan uang laci.',
            'Buka Shift: pilih Outlet dan isi Modal Awal (uang tunai). Tutup Shift: lihat Penjualan per Metode Bayar, Uang di Laci (modal awal + penjualan tunai = seharusnya di laci), isi Cash Aktual di Laci, sistem menghitung Selisih kas, Catatan opsional. Riwayat Shift dan detail shift.',
            'QRIS dan transfer tidak masuk laci; cocokkan dengan mutasi bank/e-wallet.'],
        'pesanan_qr' => ['Pesanan QR', 'menu atas (hanya jenis bisnis Makanan & Minuman)', 'pemilik, admin, kasir', 'pesanan qr pesanan meja terima pesanan tolak self order',
            'Pesanan yang dibuat pelanggan lewat QR meja.',
            'Lihat item tiap meja, Tolak atau Terima pesanan, Buka QR pembayaran / Tampilkan QRIS, pilih Tunai, QRIS di kasir, atau Debit / EDC, lalu Konfirmasi bayar.',
            'Stok baru berkurang dan tiket masuk dapur setelah pesanan diterima.'],
        'produk' => ['Produk / Menu', 'Master Data', 'pemilik dan admin', 'produk menu tambah menu harga jual harga modal margin foto sku varian add-on topping barcode label stok produk nonaktif',
            'Kelola menu yang tampil di kasir.',
            'Form: Nama Produk, Kategori, SKU (opsional), Outlet (kosong = semua outlet), Deskripsi, Harga Jual, Harga Modal (margin dihitung otomatis), Foto (JPEG/PNG/WebP maks 10 MB), Produk aktif, Pantau stok via resep. Halaman detail: Varian Produk (nama + selisih harga, contoh Ukuran L), Add-on / Topping berbayar (contoh Extra Keju), Resep. Ada Cetak Label Barcode (pilih produk dan jumlah label). Filter kategori dan status Aktif/Nonaktif.',
            'Produk tidak muncul di kasir biasanya karena nonaktif atau outlet-nya tidak sesuai.'],
        'bundling' => ['Bundling Produk', 'Master Data', 'pemilik dan admin', 'bundling bundle paket hemat paket combo',
            'Gabungkan beberapa produk jadi satu paket harga khusus.',
            'Nama Bundle, Deskripsi, Isi Paket (pilih produk dan qty), Harga Bundle; sistem menampilkan total harga normal dan berapa persen pelanggan hemat.',
            'Bundle tampil di kasir sebagai satu item.'],
        'kategori' => ['Kategori', 'Master Data', 'pemilik dan admin', 'kategori warna kategori kirim ke dapur kelompok menu',
            'Kelompokkan menu.',
            'Nama Kategori, Warna Label di kasir, opsi Kirim ke dapur (item kategori itu dicetak di tiket dapur), ubah nama dan warna langsung dari baris.',
            'Kategori baru langsung tampil di kasir.'],
        'pelanggan' => ['Pelanggan', 'Master Data', 'pemilik dan admin', 'pelanggan member poin loyalitas data pelanggan riwayat belanja',
            'Data pelanggan, riwayat belanja, dan poin loyalitas.',
            'Nama Lengkap (wajib), No. HP, Email, Pelanggan aktif. Detail: Total Transaksi, Total Belanja, Poin Loyalitas, Riwayat Poin, Riwayat Transaksi. Cari nama atau no. HP.',
            'Aturan poin (Poin per Rp 1 dan Nilai 1 poin) diatur di Pengaturan Struk.'],
        'promo' => ['Promo & Diskon', 'Master Data', 'pemilik dan admin', 'promo diskon voucher kode promo happy hour beli gratis buy get potongan untuk pembeli',
            'Promo untuk PEMBELI di toko (bukan diskon langganan Yoka).',
            'Nama Promo, Kode Promo opsional (kasir bisa mengetik kode), Jenis Diskon (persen, nominal, atau beli X gratis Y untuk kategori tertentu), Nilai Diskon, Minimum Order, Tanggal Mulai dan Selesai, Berlaku di Outlet, Status.',
            'Promo aktif muncul di kasir lewat "Pakai promo / voucher".'],
        'dapur' => ['Dapur (KDS)', 'Operasional (hanya Makanan & Minuman)', 'pemilik, admin, dapur', 'dapur kds kitchen layar dapur tiket dapur pesanan dapur masak',
            'Layar dapur untuk pesanan dari kasir.',
            'Pesanan urut dari yang paling lama dengan status Menunggu, Sedang dimasak, Siap diantar; menampilkan varian, add-on, dan catatan.',
            'Opsi "Kirim ke dapur" di Kategori menentukan item mana yang dicetak di tiket dapur.'],
        'qr_meja' => ['QR Meja', 'Operasional (hanya Makanan & Minuman)', 'pemilik dan admin', 'qr meja area meja cetak qr sesi meja ganti url',
            'Buat QR untuk pelanggan memesan dari meja.',
            'Tambah area (Indoor, Teras), Tambah meja (contoh A01) lalu Buat meja dan QR, Cetak, Ganti URL bila QR disalahgunakan, Tutup sesi meja. Ringkasan Total meja, Sedang digunakan, Pesanan aktif.',
            'QR tetap; tiap kunjungan dibuat sebagai sesi baru.'],
        'bahan_baku' => ['Bahan Baku', 'Operasional', 'pemilik, admin, gudang', 'bahan baku stok bahan stok minimum stok masuk stok opname penyesuaian stok satuan',
            'Daftar bahan dan stoknya.',
            'Nama Bahan, SKU, Satuan, Stok Minimum (peringatan stok menipis), Harga Rata-rata per satuan. Detail: Stok Masuk (catat penambahan), Penyesuaian Stok (samakan dengan hitung fisik/stok opname, wajib alasan), Riwayat Pergerakan Stok.',
            'Harga rata-rata dipakai untuk menghitung modal produk lewat resep.'],
        'resep' => ['Resep', 'Operasional', 'pemilik, admin, gudang', 'resep takaran bahan per porsi hpp modal porsi',
            'Bahan yang dipakai untuk satu porsi produk.',
            'Pilih bahan dan jumlah per porsi; tampil Estimasi Modal: harga jual, modal bahan per porsi, margin.',
            'Produk harus mencentang "Pantau stok via resep" agar stok bahan berkurang otomatis saat terjual.'],
        'pergerakan_stok' => ['Pergerakan Stok', 'Operasional', 'pemilik, admin, gudang', 'pergerakan stok mutasi stok riwayat stok',
            'Riwayat keluar masuk stok bahan.',
            'Bahan, Tipe, Qty, Sebelum, Sesudah, User, Catatan, Waktu.',
            ''],
        'laporan_penjualan' => ['Laporan Penjualan', 'Laporan', 'pemilik dan admin', 'laporan penjualan omzet penjualan bersih grafik per jam metode pembayaran product mix breakdown export excel pdf bandingkan periode',
            'Analisis penjualan lengkap.',
            'Filter Periode, Outlet, Kasir, Metode bayar, Kategori, Tren per, Bandingkan dengan periode/outlet lain. Ringkasan: Penjualan kotor, Penjualan bersih, Pembayaran tercatat, Transaksi, Rata-rata transaksi, grafik per jam, Metode pembayaran, Kategori teratas, Estimasi laba kotor, Order refund/batal. Halaman Breakdown (matriks), Product Mix, Riwayat order. Tombol Analisis dengan Tanya Yoka dan Ekspor ke Excel (.xlsx) atau PDF.',
            'Order refund dan batal dikecualikan dari penjualan.'],
        'produk_terlaris' => ['Produk Terlaris', 'Laporan', 'pemilik dan admin', 'produk terlaris best seller paling laku',
            'Produk paling laku pada periode.',
            '10 produk terlaris (grafik jumlah dan omzet), tabel Terjual, Omzet, HPP, Est. laba. Bisa ekspor.',
            ''],
        'laporan_kasir' => ['Laporan Kasir', 'Laporan', 'pemilik dan admin', 'laporan kasir performa kasir kasir terbaik',
            'Performa tiap kasir.',
            'Kasir aktif, Total transaksi, Total omzet, Kasir terbaik, tabel Transaksi, Omzet, Rata-rata per transaksi, Kontribusi.',
            ''],
        'laporan_shift' => ['Laporan Shift', 'Laporan', 'pemilik dan admin', 'laporan shift selisih kas riwayat shift',
            'Riwayat shift dan selisih kas.',
            'Total shift, Sedang berjalan, Selisih kas, Shift dengan selisih; tabel Modal awal, Kas seharusnya, Kas aktual, Selisih. Bisa ekspor Excel.',
            ''],
        'laporan_inventory' => ['Laporan Inventory', 'Laporan', 'pemilik dan admin', 'laporan inventory nilai persediaan stok menipis',
            'Kondisi stok dan nilainya.',
            'Bahan baku aktif, Stok menipis (perlu segera dibeli), Nilai persediaan (stok x harga rata-rata), pergerakan stok per periode.',
            ''],
        'estimasi_profit' => ['Estimasi Profit', 'Laporan', 'pemilik dan admin', 'estimasi profit untung hpp margin kotor',
            'Omzet dikurangi HPP per hari.',
            'Omzet (penjualan lunas), HPP, Estimasi profit, Margin kotor, grafik harian dan rincian. Bisa ekspor PDF.',
            'Akurat bila Harga Modal produk atau resep diisi. Belum termasuk biaya operasional (lihat Laba Rugi).'],
        'laba_rugi' => ['Laba Rugi', 'Laporan', 'pemilik dan admin', 'laba rugi profit loss laba bersih laba kotor laba operasional beban',
            'Laporan laba rugi.',
            'Penjualan bersih, Laba kotor, Margin, Beban operasional (dari menu Biaya), Laba operasional, Laba sebelum pajak penghasilan, bisa dibandingkan dengan periode lain. Memo di luar laba rugi: pajak dipungut, setoran pajak, service, ongkir, pembelian persediaan.',
            'Beban memakai basis kas (tanggal biaya).'],
        'laporan_pajak' => ['Laporan Pajak', 'Laporan', 'pemilik dan admin', 'laporan pajak ppn pb1 dpp setoran pajak',
            'Pajak yang dipungut dari penjualan.',
            'Dasar pengenaan pajak (DPP), Pajak dipungut, Tarif efektif, Setoran tercatat (dari Biaya kategori Setoran Pajak), Saldo belum disetor, rincian per hari dan per outlet.',
            'Tarif diatur di Pengaturan Struk (Aktifkan pajak). Yoka tidak menyetorkan pajak untuk toko.'],
        'retur' => ['Retur Transaksi', 'Laporan', 'pemilik dan admin', 'retur transaksi refund batal void tingkat retur',
            'Ringkasan order refund dan batal.',
            'Order refund, Order batal (void), Tingkat retur, daftar order retur dengan alasan dan kasir, Alasan terbanyak, Per kasir.',
            ''],
        'biaya' => ['Biaya', 'Keuangan', 'pemilik dan admin', 'biaya pengeluaran operasional sewa listrik gaji akun biaya kategori biaya',
            'Catat pengeluaran operasional.',
            'Catat biaya: Kategori (akun), Tanggal biaya, Jumlah, Metode bayar, Dibayar kepada, No. referensi, Outlet atau Umum, Catatan. Ringkasan Total biaya, Rata-rata per hari, Kategori terbesar. Daftar akun biaya bisa ditambah (kode 4 angka). Biaya bisa Dibatalkan.',
            'Biaya masuk ke Laba Rugi.'],
        'pembelian' => ['Pembelian', 'Keuangan', 'pemilik dan admin', 'pembelian belanja supplier faktur stok masuk beli bahan',
            'Catat belanja ke supplier.',
            'Supplier, Tanggal pembelian, Jatuh tempo, No. faktur supplier, Outlet penerima, baris barang (bahan baku opsional, deskripsi, qty, satuan, harga), Diskon, PPN masukan, Ongkos kirim, Dibayar sekarang. Sisa otomatis jadi hutang. Detail: Bayar hutang, Riwayat pembayaran, Batalkan pembelian.',
            'Bila baris dihubungkan ke bahan baku, stok bertambah. Pembelian hanya bisa dibatalkan bila belum ada pembayaran; stok ditarik kembali.'],
        'supplier' => ['Supplier', 'Keuangan', 'pemilik dan admin', 'supplier pemasok vendor',
            'Daftar pemasok.',
            'Nama, Nama kontak, Telepon, Tempo (hari), Email, NPWP, Alamat; total pembelian dan hutang berjalan per supplier.',
            ''],
        'hutang' => ['Hutang', 'Keuangan', 'pemilik dan admin', 'hutang utang tagihan supplier jatuh tempo bayar hutang',
            'Tagihan pembelian yang belum lunas.',
            'No. pembelian, Supplier, Jatuh tempo, Total, Dibayar, Sisa, status lewat jatuh tempo, tombol Bayar; ringkasan umur hutang.',
            'Hutang muncul otomatis dari Pembelian yang belum dibayar penuh.'],
        'piutang' => ['Piutang', 'Keuangan', 'pemilik dan admin', 'piutang tagihan pelanggan kasbon uang belum diterima',
            'Uang yang belum diterima dari pihak lain.',
            'Catat piutang: Nama pihak, Pelanggan terdaftar opsional, Tanggal terbit, Jatuh tempo, Jumlah, Outlet, Keterangan. Detail: Terima pembayaran, Riwayat penerimaan, Batalkan piutang.',
            ''],
        'outlet' => ['Outlet', 'Pengaturan', 'pemilik dan admin', 'outlet cabang tambah outlet kode outlet',
            'Kelola cabang.',
            'Nama Outlet, Kode Outlet (dipakai untuk login PIN kasir), No. Telepon, Alamat (tampil di struk dan laporan), Outlet aktif.',
            'Jumlah outlet mengikuti paket langganan.'],
        'user' => ['Manajemen User', 'Pengaturan', 'pemilik dan admin', 'user staf karyawan role hak akses kasir baru pin kasir admin gudang dapur nonaktifkan',
            'Akun staf dan hak aksesnya.',
            'Nama Lengkap, Email, No. HP, Role (Admin, Kasir, Dapur, Gudang), Outlet, Password minimal 8 karakter, PIN Kasir 4 sampai 8 angka untuk masuk cepat di tablet, User aktif. Hapus PIN = kasir tidak bisa masuk lewat tablet.',
            'Kasir: kasir, riwayat order, shift, pesanan QR. Dapur: layar dapur. Gudang: bahan baku, resep, pergerakan stok. Admin: hampir semua menu kecuali urusan langganan.'],
        'profil_bisnis' => ['Profil Bisnis', 'Pengaturan', 'pemilik dan admin', 'profil bisnis nama bisnis logo jenis bisnis retail makanan minuman',
            'Data usaha.',
            'Nama Bisnis, No. Telepon, Email, Alamat, Jenis Bisnis (Makanan & Minuman atau Toko / Retail), Logo Bisnis (tampil di struk, maks 2 MB).',
            'Jenis Bisnis menentukan menu yang tampil: Toko / Retail menyembunyikan Pesanan QR dan menu Operasional dapur.'],
        'pengaturan_struk' => ['Pengaturan Struk', 'Pengaturan', 'pemilik dan admin', 'pengaturan struk printer ukuran kertas header footer logo struk pajak ppn service charge stok negatif poin loyalitas struk whatsapp fonnte rawbt',
            'Atur struk, printer, pajak, poin, dan struk WhatsApp.',
            'Header, baris bawah nama, alamat dan no. telp struk, Footer, Logo Struk, pilihan info yang tampil. Printer: Ukuran kertas (58/80 mm) dan Cara cetak (Dialog print browser atau Bluetooth lewat RawBT). Aktifkan pajak (PPN) dan persentasenya, Aktifkan service charge. Izinkan stok negatif. Poin per Rp 1 dan Nilai 1 poin. Struk via WhatsApp (Fonnte) dengan token Fonnte milik toko.',
            'Logo tidak tercetak lewat RawBT. Perubahan berlaku untuk semua kasir setelah disimpan.'],
        'qris' => ['Pengaturan QRIS', 'dibuka dari layar pembayaran QRIS di kasir (halaman pengaturan QRIS)', 'pemilik dan admin', 'qris qr pembayaran nmid merchant qris dinamis qris statis upload qr',
            'Atur pembayaran QRIS toko.',
            'Upload gambar QR QRIS, Nama Merchant QRIS, NMID. QRIS Dinamis: tempel Kode QRIS Statis (hasil scan QR toko dengan aplikasi pembaca QR), lalu sistem membuat QR baru tiap transaksi dengan nominal terkunci. Preview tampilan di kasir.',
            'Uang QRIS masuk ke rekening/e-wallet merchant toko sendiri, bukan lewat Yoka.'],
        'bot_telegram' => ['Bot Telegram', 'Pengaturan', 'pemilik dan admin', 'bot telegram notifikasi ringkasan harian laporan telegram',
            'Laporan dan notifikasi toko di Telegram.',
            'Hubungkan lewat tautan (berlaku 15 menit) lalu tekan Start, kirim pesan tes, pilih notifikasi yang dikirim, Putuskan. Bisa bertanya bebas seperti "omzet hari ini berapa?".',
            'Putuskan dari Telegram dengan /putus.'],
        'integrasi' => ['Integrasi', 'Pengaturan', 'pemilik dan admin', 'integrasi google sheets gofood shopeefood pesan antar online',
            'Sambungan ke layanan lain.',
            'Google Sheets: salin rumus ke sel A1 di Sheets baru untuk feed laporan penjualan, bisa Buat ulang tautan. Pesan antar online (GoFood, ShopeeFood): kerangka ada, belum aktif.',
            'Jangan menjanjikan GoFood/ShopeeFood aktif.'],
        'audit_log' => ['Audit Log', 'Pengaturan', 'pemilik dan admin', 'audit log jejak aktivitas siapa mengubah riwayat aktivitas',
            'Jejak aktivitas staf: siapa melakukan apa dan kapan.',
            'Filter tanggal, user, aksi; tampil data sebelum dan sesudah, IP.',
            ''],
        'paket_langganan' => ['Paket Langganan', 'Langganan & Saldo', 'pemilik', 'paket langganan upgrade bayar langganan perpanjang midtrans harga paket starter business enterprise',
            'Lihat paket aktif, riwayat pembayaran, dan upgrade atau perpanjang langganan.',
            'Pilih paket, klik "Bayar dengan Midtrans", selesaikan pembayaran di halaman Midtrans (QRIS, VA, e-wallet, kartu sesuai yang tersedia). Langganan aktif otomatis setelah pembayaran terkonfirmasi.',
            'Pembayaran belum aktif: tunggu beberapa menit lalu muat ulang; kalau tetap belum, eskalasi ke tim (topik pembayaran).'],
        'saldo_penarikan' => ['Saldo & Penarikan', 'Langganan & Saldo', 'pemilik', 'saldo penarikan tarik dana withdraw pencairan',
            'Saldo pembayaran online toko dan pengajuan penarikan ke rekening. Menu ini hanya muncul bila fitur saldo diaktifkan untuk akun tersebut.',
            'Lihat saldo, ajukan penarikan ke rekening terdaftar, pantau status pengajuan.',
            'Menu tidak terlihat: fitur saldo belum aktif untuk akun itu. Pertanyaan dana/penarikan adalah topik sensitif: eskalasi ke tim.'],
        'profil_saya' => ['Profil Saya', 'menu foto profil di kanan atas', 'semua role', 'profil saya ganti password ganti foto google akun saya',
            'Akun pribadi.',
            'Ganti foto profil, hubungkan Google, Nama lengkap, No. HP, Email. Ganti password lewat tautan yang dikirim ke email.',
            'Lupa password sebelum masuk: pakai "Lupa password?" di halaman masuk.'],
    ];

    /** Satu baris per menu: supaya Adit tahu SEMUA menu yang ada. */
    public static function peta(): string
    {
        $lines = [];
        foreach (self::MENUS as $m) {
            $lines[] = "{$m[0]} ({$m[1]}): {$m[4]}";
        }

        return "PETA MENU APLIKASI (semua menu yang ada; jangan sebut menu lain):\n".implode("\n", $lines);
    }

    /** @return array<int, string> kunci menu yang paling cocok */
    public static function match(string $text, int $limit = 2): array
    {
        $text = ' '.Str::lower($text).' ';
        $scores = [];
        foreach (self::MENUS as $key => $m) {
            $score = str_contains($text, Str::lower($m[0])) ? 5 : 0;
            foreach (explode(' ', $m[3]) as $word) {
                if (mb_strlen($word) >= 3 && str_contains($text, $word)) {
                    $score += 1;
                }
            }
            // Frasa kunci dua kata lebih kuat.
            foreach (self::phrases($m[3]) as $phrase) {
                if (str_contains($text, $phrase)) {
                    $score += 2;
                }
            }
            if ($score >= 2) {
                $scores[$key] = $score;
            }
        }
        arsort($scores);

        return array_slice(array_keys($scores), 0, $limit);
    }

    public static function detail(string $text): string
    {
        $blocks = [];
        foreach (self::match($text) as $key) {
            [$name, $where, $who, , $what, $inside, $rules] = self::MENUS[$key];
            $blocks[] = "[{$name}] letak: {$where} | akses: {$who} | fungsi: {$what} | isi: {$inside}".($rules !== '' ? " | aturan: {$rules}" : '');
        }

        return $blocks === [] ? '' : "DETAIL MENU TERKAIT (pakai nama tombol dan kolom persis seperti ini):\n".implode("\n", $blocks);
    }

    /** @return array<int, string> */
    private static function phrases(string $keywords): array
    {
        $w = explode(' ', $keywords);
        $out = [];
        for ($i = 0; $i < count($w) - 1; $i++) {
            $out[] = $w[$i].' '.$w[$i + 1];
        }

        return $out;
    }
}
