@extends('legal.layout')

@section('title', 'Kebijakan Privasi')
@section('updated', '18 November 2026')

@section('content')
<p>Kebijakan ini menjelaskan data apa yang dikumpulkan Yoka POS (aplikasi web di yokapos.com dan aplikasi Android Yoka POS), untuk apa dipakai, dan bagaimana Anda dapat mengendalikannya.</p>

<h2>Data yang kami kumpulkan</h2>
<ul>
    <li><strong>Data akun:</strong> nama, nomor telepon, alamat email, dan kata sandi (disimpan dalam bentuk terenkripsi/hash, tidak dapat dibaca oleh kami).</li>
    <li><strong>Data bisnis yang Anda masukkan:</strong> nama usaha, outlet, produk, harga, stok, resep, pelanggan, karyawan, serta transaksi penjualan, shift, dan laporan.</li>
    <li><strong>Data teknis:</strong> alamat IP, jenis perangkat dan peramban, serta catatan kesalahan, untuk keamanan dan perbaikan layanan.</li>
    <li><strong>Data TikTok yang Anda izinkan:</strong> identitas dasar akun (seperti nama tampilan dan foto profil), daftar video publik milik akun Anda, judul atau deskripsi video, waktu publikasi, durasi, gambar sampul, tautan video, serta jumlah tayangan, suka, komentar, dan dibagikan. Data ini hanya diproses bila Anda memilih menghubungkan akun TikTok ke Yoka POS.</li>
</ul>
<p>Aplikasi Android <strong>tidak</strong> meminta akses lokasi, kontak, kamera, atau mikrofon.</p>

<h2>Izin perangkat di aplikasi Android</h2>
<ul>
    <li><strong>Internet:</strong> menghubungkan aplikasi ke server Yoka POS.</li>
    <li><strong>Bluetooth:</strong> hanya untuk mengirim struk dan tiket dapur ke printer thermal yang sudah Anda pasangkan. Aplikasi tidak memindai atau menyimpan data perangkat lain.</li>
    <li><strong>Penyimpanan/unduhan:</strong> menyimpan berkas PDF atau Excel yang Anda ekspor ke folder Download.</li>
</ul>

<h2>Untuk apa data dipakai</h2>
<ul>
    <li>Menjalankan fitur kasir, stok, laporan, dan pengelolaan akun.</li>
    <li>Mengirim email penting layanan, seperti verifikasi akun dan reset kata sandi.</li>
    <li>Menjaga keamanan dan mencegah penyalahgunaan.</li>
    <li>Jika Anda menghubungkan TikTok, menampilkan video dan metrik akun Anda, menyimpan riwayat metrik untuk perbandingan, serta memberikan analisis dan rekomendasi konten di ruang kerja internal Yoka POS.</li>
</ul>
<p>Kami tidak menjual data Anda kepada pihak ketiga.</p>

<h2>Integrasi TikTok</h2>
<p>Menghubungkan TikTok bersifat opsional. Yoka POS meminta persetujuan Anda melalui halaman otorisasi resmi TikTok dan hanya meminta izin yang diperlukan untuk membaca informasi profil dasar dan video publik milik akun yang Anda hubungkan.</p>
<ul>
    <li>Yoka POS tidak meminta atau menyimpan kata sandi TikTok Anda.</li>
    <li>Token akses dan token penyegaran disimpan di server dengan akses terbatas dan tidak ditampilkan kepada agen AI atau pengguna lain.</li>
    <li>Data TikTok digunakan untuk fitur yang Anda minta, termasuk analisis performa dan rekomendasi konten oleh agen AI internal Yoka POS. Hasil AI dapat keliru dan tetap perlu ditinjau manusia.</li>
    <li>Yoka POS tidak memublikasikan video, mengubah akun, mengirim pesan, atau membeli iklan TikTok tanpa izin terpisah dan persetujuan eksplisit Anda.</li>
    <li>Data yang tersedia mengikuti izin dan kemampuan API resmi TikTok. Metrik seperti retensi penonton atau waktu tonton tidak akan diklaim tersedia bila tidak diberikan oleh TikTok.</li>
</ul>

<h2>Pihak ketiga yang terlibat</h2>
<ul>
    <li><strong>Penyedia hosting</strong> yang menyimpan server dan basis data Yoka POS.</li>
    <li><strong>Penyedia pembayaran</strong> (misalnya Midtrans) untuk memproses pembayaran QRIS atau langganan. Data kartu atau akun pembayaran diproses oleh penyedia tersebut, bukan disimpan oleh Yoka POS.</li>
    <li><strong>Layanan email</strong> untuk pengiriman email sistem.</li>
    <li><strong>TikTok</strong>, bila Anda menghubungkan akun, untuk otorisasi akun dan penyediaan profil, video publik, serta metrik yang Anda izinkan. Penggunaan TikTok juga tunduk pada ketentuan dan kebijakan TikTok.</li>
</ul>

<h2>Penyimpanan dan keamanan</h2>
<p>Data dikirim melalui koneksi terenkripsi (HTTPS) dan disimpan di server dengan akses terbatas. Data bisnis dipisahkan per akun bisnis. Tidak ada sistem yang sepenuhnya bebas risiko, sehingga kami menyarankan Anda memakai kata sandi dan PIN yang kuat.</p>

<h2>Penyimpanan dan penghapusan</h2>
<p>Data disimpan selama akun aktif atau selama diperlukan untuk menyediakan fitur yang Anda gunakan. Anda dapat meminta penghapusan akun dan data terkait kapan saja; caranya ada di halaman <a href="{{ route('legal.delete') }}">Hapus Akun</a>. Data yang wajib kami simpan menurut hukum (misalnya catatan transaksi) dapat disimpan sesuai jangka waktu yang diwajibkan.</p>

<h2>Memutus koneksi dan menghapus data TikTok</h2>
<p>Anda dapat memutus koneksi TikTok dari halaman Data &amp; Integrasi di Yoka POS. Setelah diputus, Yoka POS menghentikan pengambilan data baru dan menghapus token TikTok yang tersimpan. Anda juga dapat mencabut akses Yoka POS dari pengaturan keamanan akun TikTok.</p>
<p>Untuk meminta penghapusan salinan profil, daftar video, dan riwayat metrik TikTok yang tersimpan di Yoka POS, gunakan proses pada halaman <a href="{{ route('legal.delete') }}">Hapus Akun</a> atau kirim permintaan dari alamat email akun Anda ke <a href="mailto:conwebid@gmail.com">conwebid@gmail.com</a>. Sebutkan bahwa permintaan hanya untuk data integrasi TikTok bila Anda tidak ingin menghapus seluruh akun Yoka POS.</p>

<h2>Hak Anda</h2>
<p>Anda berhak mengakses, memperbaiki, dan meminta penghapusan data pribadi Anda. Hubungi kami melalui email di bawah.</p>

<h2>Anak-anak</h2>
<p>Yoka POS ditujukan untuk pelaku usaha dan tidak ditujukan bagi anak di bawah 18 tahun.</p>

<h2>Perubahan kebijakan</h2>
<p>Jika kebijakan ini berubah, versi terbaru akan ditampilkan di halaman ini beserta tanggal pembaruannya.</p>

<h2>Kontak</h2>
<p>Pertanyaan tentang privasi: <a href="mailto:conwebid@gmail.com">conwebid@gmail.com</a></p>
@endsection
