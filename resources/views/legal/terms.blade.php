@extends('legal.layout')

@section('title', 'Syarat Layanan')
@section('updated', '13 November 2026')

@section('content')
<p>Dengan membuat akun atau memakai Yoka POS, Anda menyetujui syarat berikut. Jika tidak setuju, mohon berhenti menggunakan layanan.</p>

<h2>1. Layanan</h2>
<p>Yoka POS adalah perangkat lunak kasir dan manajemen bisnis (penjualan, stok, laporan) yang tersedia sebagai aplikasi web dan aplikasi Android. Fitur dapat bertambah atau berubah dari waktu ke waktu.</p>

<h2>2. Akun</h2>
<ul>
    <li>Anda bertanggung jawab atas keakuratan data pendaftaran dan atas semua aktivitas di akun Anda, termasuk akun karyawan yang Anda buat.</li>
    <li>Jaga kerahasiaan kata sandi dan PIN. Segera beri tahu kami jika ada akses yang tidak sah.</li>
    <li>Satu akun bisnis dipakai untuk bisnis Anda sendiri; dilarang menjual kembali akses tanpa persetujuan tertulis.</li>
</ul>

<h2>3. Masa percobaan dan paket berbayar</h2>
<p>Kami dapat menyediakan masa percobaan gratis. Paket, harga, dan fitur tiap paket ditampilkan di akun web Anda. Pembayaran dan pengelolaan langganan dilakukan melalui akun web, bukan di dalam aplikasi Android.</p>

<h2>4. Data Anda</h2>
<p>Data bisnis yang Anda masukkan tetap milik Anda. Anda memberi kami izin memprosesnya hanya untuk menjalankan layanan. Rincian ada di <a href="{{ route('legal.privacy') }}">Kebijakan Privasi</a>.</p>

<h2>5. Penggunaan yang dilarang</h2>
<ul>
    <li>Menggunakan layanan untuk kegiatan melanggar hukum.</li>
    <li>Mencoba menembus keamanan, mengganggu server, atau mengakses data akun lain.</li>
    <li>Menyalin atau membongkar perangkat lunak di luar yang diizinkan hukum.</li>
</ul>

<h2>6. Ketersediaan</h2>
<p>Kami berupaya menjaga layanan tetap berjalan, tetapi tidak menjamin layanan bebas gangguan. Kami menyarankan Anda menyiapkan cara pencatatan cadangan saat jaringan terputus. Pemeliharaan terjadwal akan diinformasikan bila memungkinkan.</p>

<h2>7. Batasan tanggung jawab</h2>
<p>Sejauh diizinkan hukum, Yoka POS tidak bertanggung jawab atas kerugian tidak langsung, kehilangan keuntungan, atau kerugian akibat kesalahan input data, gangguan jaringan, atau perangkat pihak ketiga (misalnya printer) yang berada di luar kendali kami.</p>

<h2>8. Penghentian</h2>
<p>Anda dapat berhenti kapan saja dan meminta penghapusan akun di halaman <a href="{{ route('legal.delete') }}">Hapus Akun</a>. Kami dapat menangguhkan akun yang melanggar syarat ini.</p>

<h2>9. Perubahan syarat</h2>
<p>Syarat dapat diperbarui. Penggunaan layanan setelah pembaruan berarti Anda menyetujui versi terbaru.</p>

<h2>10. Kontak</h2>
<p><a href="mailto:conwebid@gmail.com">conwebid@gmail.com</a></p>
@endsection
