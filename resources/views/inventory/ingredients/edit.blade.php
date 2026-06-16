@extends('layouts.app')
@section('title','Edit Bahan')
@section('page-title','Edit: ' . $ingredient->name)
@section('content')
<div class="max-w-lg">
    <x-card title="Edit Bahan Baku">
        <form method="POST" action="{{ route('ingredients.update', $ingredient) }}" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Bahan <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $ingredient->name) }}" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
                <input type="text" name="sku" value="{{ old('sku', $ingredient->sku) }}"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Satuan</label>
                <select name="unit" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    @foreach(['pcs','gram','kg','ml','liter','pack','box','sachet'] as $u)
                    <option value="{{ $u }}" {{ old('unit',$ingredient->unit)===$u?'selected':'' }}>{{ ucfirst($u) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stok Minimum</label>
                    <input type="number" name="minimum_stock" value="{{ old('minimum_stock', $ingredient->minimum_stock) }}" min="0" step="0.001"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Harga Rata-rata</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">Rp</span>
                        <input type="number" name="average_cost" value="{{ old('average_cost', $ingredient->average_cost) }}" min="0"
                               class="pl-9 w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" id="is_active" class="h-4 w-4 text-emerald-600 rounded"
                       {{ old('is_active', $ingredient->is_active) ? 'checked' : '' }}>
                <label for="is_active" class="text-sm text-gray-700">Bahan Aktif</label>
            </div>
            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                    Simpan Perubahan
                </button>
                <a href="{{ route('ingredients.show', $ingredient) }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
