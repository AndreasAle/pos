@extends('layouts.app')
@section('title','Buka Shift')
@section('page-title','Buka Shift Baru')
@section('content')
<div class="max-w-md mx-auto mt-8">
    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 mb-5 text-sm text-emerald-800">
        <p class="font-semibold mb-1">Sebelum mulai transaksi, buka shift terlebih dahulu.</p>
        <p>Masukkan jumlah uang tunai di laci kasir sebagai modal awal.</p>
    </div>
    <x-card title="Buka Shift Kasir">
        <form method="POST" action="{{ route('shifts.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Outlet <span class="text-red-500">*</span></label>
                <select name="outlet_id" required class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" {{ auth()->user()->outlet_id == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Modal Awal (Uang Tunai) <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">Rp</span>
                    <input type="number" name="opening_cash" min="0" step="1000" value="0" required
                           class="pl-10 w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <button type="submit"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors">
                Buka Shift & Mulai Transaksi
            </button>
        </form>
    </x-card>
</div>
@endsection
