@extends('layouts.app')
@section('title','Pengaturan Struk & Pajak')
@section('page-title','Pengaturan Struk & Pajak')

@section('content')
@php
    $s      = $business->settings ?? [];
    $outlet = $business->outlets()->orderBy('id')->first();
    $showToggles = [
        'receipt_show_logo'       => 'Logo',
        'receipt_show_address'    => 'Alamat',
        'receipt_show_phone'      => 'No. Telp',
        'receipt_show_cashier'    => 'Nama kasir',
        'receipt_show_customer'   => 'Nama pelanggan',
        'receipt_show_order_type' => 'Dine In / Takeaway',
        'receipt_show_total_qty'  => 'Total QTY',
    ];
@endphp

<div class="page text-[#0B1B3F] pt-2">
    @include('settings._nav')

    <form method="POST" action="{{ route('settings.receipt.update') }}" enctype="multipart/form-data" class="max-w-4xl space-y-4">
        @csrf

        {{-- Struk --}}
        <x-panel title="Tampilan Struk" sub="Isi dan tata letak struk yang dicetak untuk pelanggan." class="fade-up" style="--i:1">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-field label="Header Struk" name="receipt_header" hint="Tampil di baris pertama struk." class="sm:col-span-2">
                    <x-input name="receipt_header" :value="old('receipt_header', $s['receipt_header'] ?? $business->name)" placeholder="Nama bisnis yang tampil di atas struk" />
                </x-field>
                <x-field label="Baris di bawah nama" name="receipt_subtitle" hint="Opsional." class="sm:col-span-2">
                    <x-input name="receipt_subtitle" :value="old('receipt_subtitle', $s['receipt_subtitle'] ?? '')" placeholder="Contoh: Coffee & Snack, IG @kopi_mertuaku" />
                </x-field>
                <x-field label="Alamat di Struk" name="receipt_address" hint="Kosongkan untuk memakai alamat outlet.">
                    <x-textarea name="receipt_address" :rows="2" :placeholder="$outlet?->address ?: 'Jl. Contoh No. 1, Kota'">{{ old('receipt_address', $s['receipt_address'] ?? '') }}</x-textarea>
                </x-field>
                <x-field label="No. Telp di Struk" name="receipt_phone" hint="Kosongkan untuk memakai nomor outlet.">
                    <x-input name="receipt_phone" type="tel" :value="old('receipt_phone', $s['receipt_phone'] ?? '')" :placeholder="$outlet?->phone ?: '0812-3456-7890'" />
                </x-field>
                <x-field label="Footer Struk" name="receipt_footer" class="sm:col-span-2">
                    <x-textarea name="receipt_footer" :rows="2" placeholder="Terima kasih atas kunjungan Anda!">{{ old('receipt_footer', $s['receipt_footer'] ?? '') }}</x-textarea>
                </x-field>

                <x-field label="Logo Struk" name="receipt_logo" hint="Hitam-putih paling bagus di printer thermal. Logo tidak tercetak lewat RawBT." class="sm:col-span-2">
                    <div class="flex items-center gap-4 rounded-lg border border-[#E5E7EB] bg-slate-50 p-3">
                        @if($business->logo)
                        <img src="{{ asset('storage/'.$business->logo) }}" alt="" class="h-14 w-14 shrink-0 rounded-lg border border-[#E5E7EB] bg-white object-contain p-1" style="filter:grayscale(1)">
                        @endif
                        <input type="file" id="receipt_logo" name="receipt_logo" accept="image/png,image/jpeg,image/webp"
                               class="min-w-0 flex-1 text-sm text-[#64748B] file:mr-3 file:h-9 file:cursor-pointer file:rounded-lg file:border-0 file:bg-white file:px-3.5 file:text-[13px] file:font-semibold file:text-[#0B1B3F] file:ring-1 file:ring-[#E5E7EB] hover:file:bg-slate-50">
                    </div>
                </x-field>
            </div>

            <div class="mt-6 border-t border-[#E5E7EB] pt-5">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-[#64748B]">Tampilkan di struk</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($showToggles as $key => $label)
                    <x-switch :name="$key" :label="$label" :checked="(bool) old($key, $s[$key] ?? \App\Support\ReceiptOptions::TOGGLES[$key])" />
                    @endforeach
                </div>
            </div>
        </x-panel>

        {{-- Printer --}}
        <x-panel title="Printer" sub="Ukuran kertas dan cara cetak bawaan." class="fade-up" style="--i:2">
            <p class="mb-3 text-sm font-semibold">Ukuran kertas</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach(['58mm' => 'Thermal 58mm (kecil)', '80mm' => 'Thermal 80mm (standar)'] as $v => $l)
                <x-radio name="receipt_size" :value="$v" :label="$l" :checked="old('receipt_size', $s['receipt_size'] ?? '80mm') === $v" />
                @endforeach
            </div>

            <p class="mb-3 mt-6 text-sm font-semibold">Cara cetak (bawaan)</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <x-radio name="print_method" value="browser" label="Dialog print browser" desc="Untuk printer USB atau PC." :checked="old('print_method', $s['print_method'] ?? 'browser') === 'browser'" />
                <x-radio name="print_method" value="rawbt" label="Bluetooth lewat RawBT" desc="Untuk tablet atau HP Android." :checked="old('print_method', $s['print_method'] ?? 'browser') === 'rawbt'" />
            </div>
            <p class="mt-3 text-xs text-[#64748B]">Ini hanya bawaan. Tiap laptop atau HP bisa memilih sendiri lewat pilihan printer di pojok kanan atas halaman Kasir. RawBT: pasang dari Play Store, pair printer Bluetooth, lalu pilih printernya di RawBT.</p>
        </x-panel>

        {{-- Pajak & Service --}}
        <x-panel title="Pajak dan Service Charge" sub="Dihitung otomatis dari subtotal di kasir." class="fade-up" style="--i:3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-3 rounded-lg border border-[#E5E7EB] bg-slate-50/60 p-4">
                    <x-switch name="enable_tax" label="Aktifkan pajak (PPN)" :checked="(bool) old('enable_tax', $s['enable_tax'] ?? false)" />
                    <x-field label="Persentase pajak" name="tax_percent">
                        <x-input name="tax_percent" type="number" suffix="%" :value="old('tax_percent', $s['tax_percent'] ?? 10)" min="0" max="100" step="0.5" class="h-10" />
                    </x-field>
                </div>
                <div class="space-y-3 rounded-lg border border-[#E5E7EB] bg-slate-50/60 p-4">
                    <x-switch name="enable_service" label="Aktifkan service charge" :checked="(bool) old('enable_service', $s['enable_service'] ?? false)" />
                    <x-field label="Persentase service" name="service_percent">
                        <x-input name="service_percent" type="number" suffix="%" :value="old('service_percent', $s['service_percent'] ?? 5)" min="0" max="100" step="0.5" class="h-10" />
                    </x-field>
                </div>
            </div>
        </x-panel>

        {{-- Inventory & Loyalty --}}
        <x-panel title="Inventori dan Loyalitas" class="fade-up" style="--i:4">
            <div class="space-y-5">
                <x-switch name="allow_negative_stock" label="Izinkan stok negatif" desc="Transaksi tetap diproses meski stok bahan minus. Berguna saat input stok tertunda." :checked="(bool) old('allow_negative_stock', $s['allow_negative_stock'] ?? false)" />
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Poin per Rp 1 (earn)" name="points_per_rupiah" hint="Isi 0 untuk menonaktifkan. Contoh: 0.001 berarti Rp 1.000 = 1 poin.">
                        <x-input name="points_per_rupiah" type="number" suffix="poin" :value="old('points_per_rupiah', $s['points_per_rupiah'] ?? 0)" min="0" step="0.001" />
                    </x-field>
                    <x-field label="Nilai 1 poin (redeem)" name="point_value_rupiah" hint="Nilai tukar saat pelanggan memakai poin. Contoh: 100 berarti 1 poin = Rp 100.">
                        <x-input name="point_value_rupiah" type="number" prefix="Rp" :value="old('point_value_rupiah', $s['point_value_rupiah'] ?? 1)" min="0" step="1" />
                    </x-field>
                </div>
            </div>
        </x-panel>

        {{-- WhatsApp --}}
        <x-panel title="Struk via WhatsApp (Fonnte)" sub="Kirim struk otomatis ke nomor WhatsApp pelanggan." class="fade-up" style="--i:5">
            <div class="space-y-5">
                <x-switch name="enable_wa_receipt" label="Kirim struk via WhatsApp otomatis" desc="Setelah transaksi selesai, struk dikirim ke nomor WA pelanggan." :checked="(bool) old('enable_wa_receipt', $s['enable_wa_receipt'] ?? false)" />
                <x-field label="Token API Fonnte" name="fonnte_token">
                    <x-input name="fonnte_token" :value="old('fonnte_token', $s['fonnte_token'] ?? '')" class="font-mono" placeholder="Token dari dashboard.fonnte.com" />
                    <x-slot:aside><a href="https://fonnte.com" target="_blank" rel="noopener" class="font-semibold text-[#16A34A] hover:underline">Ambil token</a></x-slot:aside>
                </x-field>
                <x-field label="Nomor WA default" name="wa_default_phone" hint="Opsional. Dipakai jika pelanggan tidak punya nomor WA terdaftar.">
                    <x-input name="wa_default_phone" type="tel" :value="old('wa_default_phone', $s['wa_default_phone'] ?? '')" placeholder="628123456789 (format internasional)" />
                </x-field>
            </div>
        </x-panel>

        <div class="fade-up sticky bottom-4 z-10 flex items-center justify-between gap-3 rounded-xl border border-[#E5E7EB] bg-white/95 p-3 shadow-lg backdrop-blur" style="--i:6">
            <p class="hidden pl-2 text-sm text-[#64748B] sm:block">Perubahan berlaku untuk semua kasir setelah disimpan.</p>
            <x-btn type="submit" size="lg" class="w-full sm:w-auto">Simpan Semua Pengaturan</x-btn>
        </div>
    </form>
</div>
@endsection
