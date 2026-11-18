@extends('legal.layout')

@section('title', 'Hapus Akun')
@section('updated', '18 November 2026')

@section('content')
<p>Anda dapat meminta penghapusan akun Yoka POS dan data terkait kapan saja, baik dari aplikasi Android maupun dari web.</p>

<h2>Cara meminta penghapusan</h2>
<ol>
    <li>Kirim email ke <a href="mailto:support@yokapos.com?subject=Permintaan%20Hapus%20Akun%20Yoka%20POS">support@yokapos.com</a> dengan subjek <strong>Permintaan Hapus Akun Yoka POS</strong>.</li>
    <li>Tulis nama, alamat email yang terdaftar, dan nama bisnis Anda. Gunakan email yang sama dengan yang terdaftar di akun supaya kami dapat memverifikasi kepemilikan.</li>
    <li>Kami akan membalas untuk konfirmasi dan memproses penghapusan dalam waktu paling lama 14 hari kerja.</li>
</ol>

<h2>Data yang dihapus</h2>
<ul>
    <li>Akun pemilik dan akun karyawan, nama, telepon, dan email.</li>
    <li>Data bisnis: outlet, produk, stok, resep, pelanggan, transaksi, dan laporan.</li>
    <li>Data integrasi pihak ketiga yang terkait dengan akun, termasuk token TikTok, profil TikTok, daftar video, dan riwayat metrik yang tersimpan di Yoka POS.</li>
</ul>

<h2>Hapus data TikTok saja</h2>
<p>Anda tidak perlu menghapus seluruh akun Yoka POS untuk menghentikan integrasi TikTok. Putuskan koneksi dari halaman Data &amp; Integrasi untuk menghentikan pengambilan data baru dan menghapus token TikTok yang tersimpan.</p>
<p>Untuk meminta penghapusan profil, daftar video, dan riwayat metrik TikTok yang sudah tersimpan tanpa menghapus data bisnis Yoka POS, kirim email dari alamat akun terdaftar ke <a href="mailto:support@yokapos.com?subject=Permintaan%20Hapus%20Data%20TikTok%20Yoka%20POS">support@yokapos.com</a> dengan subjek <strong>Permintaan Hapus Data TikTok Yoka POS</strong>. Kami akan memverifikasi kepemilikan dan memprosesnya paling lama 14 hari kerja.</p>

<h2>Data yang mungkin tetap disimpan</h2>
<p>Catatan yang wajib kami simpan menurut hukum atau untuk pencegahan penipuan (misalnya catatan pembayaran langganan) dapat disimpan sementara sesuai jangka waktu yang diwajibkan, lalu dihapus.</p>

<div class="note"><strong>Penting:</strong> penghapusan bersifat permanen dan tidak dapat dibatalkan. Unduh laporan atau ekspor data yang Anda butuhkan terlebih dahulu.</div>

<h2>Hanya ingin berhenti memakai aplikasi?</h2>
<p>Mencopot aplikasi Android tidak menghapus akun Anda. Untuk keluar dari akun, gunakan menu keluar di aplikasi.</p>
@endsection
