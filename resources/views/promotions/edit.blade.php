@extends('layouts.app')
@section('title','Edit Promo')
@section('page-title','Edit Promo: ' . $promotion->name)
@section('content')
<div class="max-w-xl">
    <x-card title="Edit Promo">
        <form method="POST" action="{{ route('promotions.update', $promotion) }}" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Promo <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $promotion->name) }}" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kode Promo</label>
                <input type="text" name="code" value="{{ old('code', $promotion->code) }}"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Diskon <span class="text-red-500">*</span></label>
                    <select name="type" required class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="percent" {{ old('type',$promotion->type)==='percent'?'selected':'' }}>Persen (%)</option>
                        <option value="nominal" {{ old('type',$promotion->type)==='nominal'?'selected':'' }}>Nominal (Rp)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nilai</label>
                    <input type="number" name="value" value="{{ old('value', $promotion->value) }}" min="0" step="0.01" required
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Order</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">Rp</span>
                    <input type="number" name="min_order" value="{{ old('min_order', $promotion->min_order) }}" min="0" step="1000"
                           class="pl-9 w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                    <input type="date" name="starts_at" value="{{ old('starts_at', $promotion->starts_at?->format('Y-m-d')) }}"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Selesai</label>
                    <input type="date" name="ends_at" value="{{ old('ends_at', $promotion->ends_at?->format('Y-m-d')) }}"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
                <select name="outlet_id" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="">-- Semua Outlet --</option>
                    @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" {{ old('outlet_id',$promotion->outlet_id)==$outlet->id?'selected':'' }}>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-3">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" id="ia" class="h-4 w-4 text-emerald-600 rounded"
                       {{ old('is_active', $promotion->is_active) ? 'checked' : '' }}>
                <label for="ia" class="text-sm text-gray-700">Promo Aktif</label>
            </div>
            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">Simpan</button>
                <a href="{{ route('promotions.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
