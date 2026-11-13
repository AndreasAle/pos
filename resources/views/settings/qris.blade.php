@extends('layouts.app')
@section('title','Pengaturan QRIS')
@section('page-title','Pengaturan QRIS')

@section('content')
@php
    $merchant = $business->qris_merchant_name ?? $business->name;
    $hasImage = (bool) $business->qris_image;
    $steps = [
        'Kasir menambah item ke keranjang lalu menekan Bayar.',
        'Kasir memilih metode QRIS.',
        'QR Code muncul bersama nominal yang harus dibayar.',
        'Pelanggan scan QR dan membayar sesuai nominal.',
        'Kasir menekan "Konfirmasi Sudah Dibayar", order selesai.',
    ];
@endphp

<div class="page text-[#0B1B3F] pt-2">
    @include('settings._nav')

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_380px] gap-4 items-start max-w-5xl">

        {{-- Form --}}
        <form method="POST" action="{{ route('settings.qris.update') }}" enctype="multipart/form-data"
              x-data="{ preview: @js($hasImage ? asset('storage/'.$business->qris_image) : null), pick(e) { const f = e.target.files[0]; if (f) this.preview = URL.createObjectURL(f) } }"
              class="space-y-4 min-w-0">
            @csrf

            <x-panel title="QR Code QRIS" sub="Gambar yang ditampilkan saat kasir memilih QRIS." class="fade-up" style="--i:1">
                @if($hasImage)
                <div class="mb-4 flex items-center gap-2.5 rounded-lg border border-[#BBF7D0] bg-[#ECFDF3] px-3.5 py-2.5 text-sm font-semibold text-[#15803D]">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    QR aktif dan dipakai di kasir. Upload baru untuk mengganti.
                </div>
                @else
                <div class="mb-4 flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3 text-sm text-amber-900">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                    <span><span class="font-semibold">QR belum dikonfigurasi.</span> Upload QR QRIS agar kasir bisa menerima pembayaran QRIS.</span>
                </div>
                @endif

                <label class="flex cursor-pointer items-center gap-4 rounded-lg border-2 border-dashed border-[#E5E7EB] bg-slate-50 p-4 transition-colors hover:border-[#16A34A]/60">
                    <span class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#E5E7EB] bg-white">
                        <template x-if="preview"><img :src="preview" alt="" class="h-full w-full object-contain p-1"></template>
                        <svg x-show="!preview" class="h-8 w-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 2h2m2 0h2m-6 4h2m2-4v4"/></svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold">Pilih gambar QR QRIS</span>
                        <span class="mt-0.5 block text-xs text-[#64748B]">PNG atau JPG, maks 2 MB. Screenshot QR dari aplikasi DANA, GoPay, OVO, BRI, BNI, atau dari PJSP Anda.</span>
                    </span>
                    <input type="file" name="qris_image" accept="image/png,image/jpeg,image/jpg" class="sr-only" @change="pick($event)">
                </label>
                @error('qris_image')<p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </x-panel>

            <x-panel title="Data Merchant" class="fade-up" style="--i:2">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Nama Merchant QRIS" name="qris_merchant_name" hint="Tampil di bawah QR code di layar kasir.">
                        <x-input name="qris_merchant_name" :value="old('qris_merchant_name', $merchant)" placeholder="Contoh: CAFE NUSANTARA" />
                    </x-field>
                    <x-field label="NMID / Kode Merchant" name="qris_nmid" hint="Opsional. Tampil di struk sebagai referensi.">
                        <x-input name="qris_nmid" :value="old('qris_nmid', $business->qris_nmid)" class="font-mono" placeholder="Contoh: ID1023456789" />
                    </x-field>
                </div>
            </x-panel>

            <x-panel title="QRIS Dinamis" sub="Opsional. Nominal terkunci otomatis di setiap transaksi." class="fade-up" style="--i:3">
                <x-field label="Kode QRIS Statis" name="qris_payload">
                    <x-textarea name="qris_payload" :rows="4" class="font-mono text-xs" placeholder="00020101021126570011ID.DANA.WWW...">{{ old('qris_payload', $business->settings['qris_payload'] ?? '') }}</x-textarea>
                </x-field>
                <div class="mt-3 rounded-lg border border-[#BBF7D0] bg-[#ECFDF3] p-4 text-xs leading-relaxed text-[#15803D]">
                    <p class="font-semibold">Kalau diisi, kasir tidak perlu QR gambar lagi.</p>
                    <p class="mt-1">Sistem membuat QR baru tiap transaksi dengan <strong>nominal sudah terkunci</strong>, sehingga pelanggan tidak bisa salah ketik jumlah. Uang tetap masuk langsung ke rekening merchant Anda, tanpa payment gateway dan tanpa potongan tambahan.</p>
                    <p class="mt-2 text-[#64748B]">Cara mendapatkannya: scan QR statis Anda dengan aplikasi pembaca QR biasa, lalu salin <em>seluruh</em> teks hasilnya ke sini. Kode diperiksa otomatis saat disimpan.</p>
                </div>
            </x-panel>

            <div class="fade-up" style="--i:4">
                <x-btn type="submit" size="lg" class="w-full sm:w-auto">Simpan Pengaturan QRIS</x-btn>
            </div>
        </form>

        {{-- Preview & panduan --}}
        <div class="space-y-4 lg:sticky lg:top-20">
            <x-panel title="Preview di Kasir" sub="Tampilan saat kasir memilih QRIS." class="fade-up" style="--i:2">
                <div class="rounded-xl bg-[#0B1B3F] p-5 text-center">
                    <div class="mx-auto inline-block rounded-xl bg-white p-3 shadow-lg">
                        @if($hasImage)
                        <img src="{{ asset('storage/'.$business->qris_image) }}" alt="Preview QRIS" class="h-40 w-40 object-contain">
                        @else
                        <div class="flex h-40 w-40 flex-col items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                            <svg class="mb-2 h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 2h2m2 0h2m-6 4h2m2-4v4"/></svg>
                            <p class="text-xs">QR belum diupload</p>
                        </div>
                        @endif
                    </div>
                    <p class="mt-3 text-sm font-bold text-white">{{ $merchant }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">Scan untuk membayar</p>
                    <span class="mt-3 inline-flex h-9 items-center rounded-lg bg-[#16A34A] px-5 text-xs font-semibold text-white">Konfirmasi Sudah Dibayar</span>
                </div>
            </x-panel>

            <x-panel title="Cara Kerja QRIS" class="fade-up" style="--i:3">
                <ol class="space-y-3.5 text-sm">
                    @foreach($steps as $i => $step)
                    <li class="flex gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#ECFDF3] text-xs font-bold text-[#16A34A]">{{ $i + 1 }}</span>
                        <span class="text-[#64748B]">{{ $step }}</span>
                    </li>
                    @endforeach
                </ol>
                <div class="mt-5 rounded-lg border border-sky-200 bg-sky-50 p-3.5 text-xs leading-relaxed text-sky-800">
                    <span class="font-semibold">Tips:</span> Dapatkan QR QRIS statis dari bank atau e-wallet Anda (BRI, BNI, Mandiri, DANA, GoPay, dan lainnya). Satu QR berlaku untuk semua aplikasi pembayaran.
                </div>
            </x-panel>
        </div>
    </div>
</div>
@endsection
