@extends('layouts.app')
@section('title','Pengaturan QRIS')
@section('page-title','Pengaturan QRIS')
@section('content')
@include('settings._nav')

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-3xl">

    {{-- Form Upload --}}
    <x-card title="Konfigurasi QRIS">
        <form method="POST" action="{{ route('settings.qris.update') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            {{-- Upload QR --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Upload Gambar QR Code QRIS <span class="text-red-500">*</span>
                </label>

                {{-- Preview QR saat ini --}}
                @if($business->qris_image)
                <div class="flex items-center gap-4 mb-3 p-3 bg-emerald-50 border border-emerald-200 rounded-xl">
                    <img src="{{ asset('storage/'.$business->qris_image) }}"
                         alt="QR Code QRIS"
                         class="w-24 h-24 object-contain border border-gray-200 rounded-xl bg-white p-1">
                    <div>
                        <p class="text-sm font-semibold text-emerald-800">QR aktif ✓</p>
                        <p class="text-xs text-emerald-600 mt-0.5">QR ini yang tampil saat kasir pilih QRIS</p>
                        <p class="text-xs text-gray-400 mt-1">Upload baru untuk mengganti</p>
                    </div>
                </div>
                @else
                <div class="flex items-center gap-3 mb-3 p-3 bg-orange-50 border border-orange-200 rounded-xl">
                    <span class="text-2xl">⚠️</span>
                    <div>
                        <p class="text-sm font-semibold text-orange-800">QR belum dikonfigurasi</p>
                        <p class="text-xs text-orange-600 mt-0.5">Upload QR QRIS Anda agar kasir bisa terima pembayaran QRIS</p>
                    </div>
                </div>
                @endif

                <input type="file" name="qris_image" accept="image/png,image/jpeg,image/jpg"
                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0
                              file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100
                              cursor-pointer">
                <p class="text-xs text-gray-400 mt-1.5">
                    Format: PNG atau JPG. Ukuran maks 2MB.<br>
                    Tips: Screenshot QR dari aplikasi DANA/GoPay/OVO/BRI/BNI/dll atau dari PJSP Anda.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Merchant QRIS</label>
                <input type="text" name="qris_merchant_name"
                       value="{{ old('qris_merchant_name', $business->qris_merchant_name ?? $business->name) }}"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                       placeholder="Contoh: CAFE NUSANTARA">
                <p class="text-xs text-gray-400 mt-1">Nama yang tampil di bawah QR code di layar kasir</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">NMID / Kode Merchant</label>
                <input type="text" name="qris_nmid"
                       value="{{ old('qris_nmid', $business->qris_nmid) }}"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500"
                       placeholder="Contoh: ID1023456789">
                <p class="text-xs text-gray-400 mt-1">Opsional. Tampil di struk sebagai referensi pembayaran</p>
            </div>

            <button type="submit"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                Simpan Pengaturan QRIS
            </button>
        </form>
    </x-card>

    {{-- Info & Preview --}}
    <div class="space-y-4">
        {{-- Preview tampilan di POS --}}
        <x-card title="Preview di POS">
            <p class="text-xs text-gray-500 mb-4">Begini tampilan saat kasir memilih QRIS:</p>
            <div class="bg-gray-900 rounded-2xl p-5 text-center">
                <div class="bg-white rounded-2xl p-4 mx-auto inline-block shadow-lg">
                    @if($business->qris_image)
                    <img src="{{ asset('storage/'.$business->qris_image) }}"
                         alt="QRIS Preview"
                         class="w-40 h-40 object-contain">
                    @else
                    <div class="w-40 h-40 bg-gray-100 rounded-xl flex flex-col items-center justify-center">
                        <span class="text-5xl mb-2">🔳</span>
                        <p class="text-xs text-gray-400">QR belum diupload</p>
                    </div>
                    @endif
                </div>
                <p class="text-white font-bold mt-3 text-sm">
                    {{ $business->qris_merchant_name ?? $business->name }}
                </p>
                <p class="text-gray-400 text-xs mt-1">Scan untuk membayar</p>
                <div class="mt-3 bg-emerald-600 text-white text-xs font-bold py-2 px-6 rounded-xl inline-block">
                    ✓ Konfirmasi Sudah Dibayar
                </div>
            </div>
        </x-card>

        {{-- Cara kerja --}}
        <x-card title="Cara Kerja QRIS Static">
            <ol class="space-y-3 text-sm text-gray-600">
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center flex-shrink-0">1</span>
                    Kasir tambah item ke cart & klik <strong>Bayar</strong>
                </li>
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center flex-shrink-0">2</span>
                    Pilih metode <strong>QRIS</strong>
                </li>
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center flex-shrink-0">3</span>
                    QR Code otomatis muncul + nominal yang harus dibayar
                </li>
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center flex-shrink-0">4</span>
                    Customer scan QR & transfer nominal
                </li>
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center flex-shrink-0">5</span>
                    Kasir klik <strong>"Konfirmasi Sudah Dibayar"</strong> → Order selesai
                </li>
            </ol>
            <div class="mt-4 bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-700">
                💡 <strong>Tip:</strong> Dapatkan QR QRIS statis dari bank/e-wallet Anda (BRI, BNI, Mandiri, DANA, GoPay, dll).
                Satu QR berlaku untuk semua app pembayaran.
            </div>
        </x-card>
    </div>
</div>
@endsection
