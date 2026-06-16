@extends('layouts.app')
@section('title','Tambah Promo')
@section('page-title','Tambah Promo')
@section('content')
<div class="max-w-xl">
    <x-card title="Data Promo Baru">
        <form method="POST" action="{{ route('promotions.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Promo <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                       placeholder="Contoh: Diskon Weekend, Promo Grand Opening...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kode Promo</label>
                <input type="text" name="code" value="{{ old('code') }}"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500 uppercase"
                       placeholder="PROMO10 (opsional)">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Diskon <span class="text-red-500">*</span></label>
                    <select name="type" required class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="percent" {{ old('type')==='percent'?'selected':'' }}>Persen (%)</option>
                        <option value="nominal" {{ old('type')==='nominal'?'selected':'' }}>Nominal (Rp)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nilai Diskon <span class="text-red-500">*</span></label>
                    <input type="number" name="value" value="{{ old('value',0) }}" min="0" step="0.01" required
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Order (Rp)</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">Rp</span>
                    <input type="number" name="min_order" value="{{ old('min_order',0) }}" min="0" step="1000"
                           class="pl-9 w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <p class="text-xs text-gray-400 mt-0.5">0 = berlaku untuk semua nilai order</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                    <input type="date" name="starts_at" value="{{ old('starts_at') }}"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Selesai</label>
                    <input type="date" name="ends_at" value="{{ old('ends_at') }}"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Berlaku di Outlet</label>
                <select name="outlet_id" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="">-- Semua Outlet --</option>
                    @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" {{ old('outlet_id')==$outlet->id?'selected':'' }}>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-3">
                <input type="hidden" name="is_active" value="1">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" class="h-4 w-4 text-emerald-600 rounded" checked>
                    <span class="text-sm text-gray-700">Aktifkan promo langsung</span>
                </label>
            </div>
            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">Simpan Promo</button>
                <a href="{{ route('promotions.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
