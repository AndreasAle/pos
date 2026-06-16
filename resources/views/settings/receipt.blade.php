@extends('layouts.app')
@section('title','Pengaturan Struk & Pajak')
@section('page-title','Pengaturan Struk & Pajak')
@section('content')
@include('settings._nav')

<div class="max-w-2xl">
    @php $s = $business->settings ?? []; @endphp
    <form method="POST" action="{{ route('settings.receipt.update') }}" class="space-y-5">
        @csrf

        {{-- Struk --}}
        <x-card title="Pengaturan Struk">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Header Struk</label>
                    <input type="text" name="receipt_header" value="{{ old('receipt_header', $s['receipt_header'] ?? $business->name) }}"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                           placeholder="Nama bisnis yang tampil di atas struk">
                    <p class="text-xs text-gray-400 mt-0.5">Tampil di baris pertama struk</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Footer Struk</label>
                    <textarea name="receipt_footer" rows="2"
                              class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                              placeholder="Terima kasih atas kunjungan Anda!">{{ old('receipt_footer', $s['receipt_footer'] ?? '') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ukuran Kertas Printer</label>
                    <div class="flex gap-3">
                        @foreach(['58mm'=>'Thermal 58mm (kecil)','80mm'=>'Thermal 80mm (standar)'] as $val => $label)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="receipt_size" value="{{ $val }}"
                                   class="text-emerald-600"
                                   {{ old('receipt_size', $s['receipt_size'] ?? '80mm') === $val ? 'checked' : '' }}>
                            <span class="text-sm text-gray-700">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Pajak & Service --}}
        <x-card title="Pajak & Service Charge">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4 p-4 bg-gray-50 rounded-xl">
                    <div>
                        <label class="flex items-center gap-2 mb-2 cursor-pointer">
                            <input type="hidden" name="enable_tax" value="0">
                            <input type="checkbox" name="enable_tax" value="1" id="et" class="h-4 w-4 text-emerald-600 rounded"
                                   {{ old('enable_tax', $s['enable_tax'] ?? false) ? 'checked' : '' }}>
                            <span class="text-sm font-medium text-gray-700">Aktifkan Pajak (PPN)</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="tax_percent" value="{{ old('tax_percent', $s['tax_percent'] ?? 10) }}"
                                   min="0" max="100" step="0.5"
                                   class="w-24 px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <span class="text-sm text-gray-500">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 mb-2 cursor-pointer">
                            <input type="hidden" name="enable_service" value="0">
                            <input type="checkbox" name="enable_service" value="1" id="es" class="h-4 w-4 text-emerald-600 rounded"
                                   {{ old('enable_service', $s['enable_service'] ?? false) ? 'checked' : '' }}>
                            <span class="text-sm font-medium text-gray-700">Aktifkan Service Charge</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="service_percent" value="{{ old('service_percent', $s['service_percent'] ?? 5) }}"
                                   min="0" max="100" step="0.5"
                                   class="w-24 px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <span class="text-sm text-gray-500">%</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Inventory & Loyalty --}}
        <x-card title="Inventory & Loyalty">
            <div class="space-y-4">
                <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl">
                    <input type="hidden" name="allow_negative_stock" value="0">
                    <input type="checkbox" name="allow_negative_stock" value="1" id="ans" class="h-4 w-4 text-emerald-600 rounded mt-0.5"
                           {{ old('allow_negative_stock', $s['allow_negative_stock'] ?? false) ? 'checked' : '' }}>
                    <div>
                        <label for="ans" class="text-sm font-medium text-gray-700 cursor-pointer">Izinkan Stok Negatif</label>
                        <p class="text-xs text-gray-400 mt-0.5">Jika diaktifkan, transaksi tetap diproses meski stok bahan minus. Berguna saat penginputan stok tertunda.</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Poin per Rp 1 (Earn)</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="points_per_rupiah" value="{{ old('points_per_rupiah', $s['points_per_rupiah'] ?? 0) }}"
                               min="0" step="0.001"
                               class="w-32 px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <span class="text-sm text-gray-500">poin per Rp 1 belanja</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">Masukkan 0 untuk menonaktifkan. Contoh: 0.001 berarti Rp 1.000 = 1 poin.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nilai 1 Poin (Redeem)</label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">Rp</span>
                        <input type="number" name="point_value_rupiah" value="{{ old('point_value_rupiah', $s['point_value_rupiah'] ?? 1) }}"
                               min="0" step="1"
                               class="w-32 px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <span class="text-sm text-gray-500">per 1 poin</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">Nilai tukar saat pelanggan memakai poin di kasir. Contoh: 100 berarti 1 poin = Rp 100.</p>
                </div>
            </div>
        </x-card>

        {{-- WhatsApp Receipt --}}
        <x-card title="WhatsApp Receipt (Fonnte)">
            <div class="space-y-4">
                <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl">
                    <input type="hidden" name="enable_wa_receipt" value="0">
                    <input type="checkbox" name="enable_wa_receipt" value="1" id="ewr" class="h-4 w-4 text-emerald-600 rounded mt-0.5"
                           {{ old('enable_wa_receipt', $s['enable_wa_receipt'] ?? false) ? 'checked' : '' }}>
                    <div>
                        <label for="ewr" class="text-sm font-medium text-gray-700 cursor-pointer">Kirim Struk via WhatsApp Otomatis</label>
                        <p class="text-xs text-gray-400 mt-0.5">Setelah transaksi selesai, struk dikirim ke nomor WA pelanggan.</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Token API Fonnte</label>
                    <input type="text" name="fonnte_token" value="{{ old('fonnte_token', $s['fonnte_token'] ?? '') }}"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono"
                           placeholder="Masukkan token dari dashboard.fonnte.com">
                    <p class="text-xs text-gray-400 mt-0.5">Daftar di <a href="https://fonnte.com" target="_blank" class="text-emerald-600 underline">fonnte.com</a> untuk mendapatkan token. Hubungkan nomor WhatsApp bisnis Anda.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nomor WA Default (fallback)</label>
                    <input type="text" name="wa_default_phone" value="{{ old('wa_default_phone', $s['wa_default_phone'] ?? '') }}"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                           placeholder="628123456789 (gunakan format internasional)">
                    <p class="text-xs text-gray-400 mt-0.5">Opsional. Dipakai jika pelanggan tidak punya nomor WA terdaftar.</p>
                </div>
            </div>
        </x-card>

        <button type="submit"
                class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors">
            Simpan Semua Pengaturan
        </button>
    </form>
</div>
@endsection
