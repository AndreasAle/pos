@extends('layouts.app')
@section('title','Tambah Bahan Baku')
@section('page-title','Tambah Bahan Baku')
@section('content')
<div class="max-w-lg">
    <x-card title="Data Bahan Baku">
        <form method="POST" action="{{ route('ingredients.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Bahan <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                       placeholder="Contoh: Kopi Arabika, Susu UHT...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">SKU / Kode</label>
                <input type="text" name="sku" value="{{ old('sku') }}"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Satuan <span class="text-red-500">*</span></label>
                <select name="unit" required class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    @foreach(['pcs'=>'Pcs','gram'=>'Gram','kg'=>'Kilogram','ml'=>'Mililiter','liter'=>'Liter','pack'=>'Pack','box'=>'Box','sachet'=>'Sachet'] as $v => $l)
                    <option value="{{ $v }}" {{ old('unit')===$v?'selected':'' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stok Minimum</label>
                    <input type="number" name="minimum_stock" value="{{ old('minimum_stock',0) }}" min="0" step="0.001"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <p class="text-xs text-gray-400 mt-0.5">Alert jika stok di bawah nilai ini</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Harga Rata-rata</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">Rp</span>
                        <input type="number" name="average_cost" value="{{ old('average_cost',0) }}" min="0" step="100"
                               class="pl-9 w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                    Simpan Bahan
                </button>
                <a href="{{ route('ingredients.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
