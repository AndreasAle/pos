@extends('layouts.app')
@section('title','Tutup Shift')
@section('page-title','Tutup Shift')
@section('content')
<div class="max-w-md mx-auto mt-4">
    <x-card title="Tutup Shift Kasir">
        {{-- Every method, so QRIS/transfer can be checked against the bank app too. --}}
        <div class="mb-4 bg-gray-50 rounded-xl p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                Penjualan per Metode Bayar · {{ $totalOrders }} transaksi
            </p>
            <div class="space-y-2">
                @foreach($byMethod as $method => $amount)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">{{ \App\Support\ReceiptOptions::paymentLabel($method) }}</span>
                    <span class="font-medium {{ $method === 'cash' ? 'text-emerald-700' : 'text-gray-900' }}">Rp {{ number_format($amount,0,',','.') }}</span>
                </div>
                @endforeach
                <div class="flex justify-between text-sm border-t border-gray-200 pt-2">
                    <span class="font-semibold text-gray-700">Total Penjualan</span>
                    <span class="font-bold text-gray-900">Rp {{ number_format(array_sum($byMethod),0,',','.') }}</span>
                </div>
            </div>
        </div>

        <div class="space-y-3 mb-5 bg-emerald-50 rounded-xl p-4">
            <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide">Uang di Laci</p>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Modal Awal</span>
                <span class="font-medium">Rp {{ number_format($shift->opening_cash,0,',','.') }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">+ Penjualan Tunai</span>
                <span class="font-medium text-emerald-700">Rp {{ number_format($byMethod['cash'],0,',','.') }}</span>
            </div>
            <div class="flex justify-between text-sm border-t border-emerald-200 pt-2">
                <span class="font-semibold text-gray-700">Seharusnya di Laci</span>
                <span class="font-bold text-gray-900">Rp {{ number_format($expectedCash,0,',','.') }}</span>
            </div>
            <p class="text-xs text-gray-500">QRIS, transfer, dan metode non-tunai tidak masuk laci.</p>
        </div>
        <form method="POST" action="{{ route('shifts.close.store', $shift) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cash Aktual di Laci <span class="text-red-500">*</span></label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">Rp</span>
                    <input type="number" name="closing_cash_actual" value="{{ $expectedCash }}" min="0" step="1" required
                           class="pl-10 w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                <textarea name="notes" rows="2"
                          class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                          placeholder="Catatan penutupan shift (opsional)"></textarea>
            </div>
            <button type="submit"
                    class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors">
                Tutup Shift Sekarang
            </button>
        </form>
    </x-card>
</div>
@endsection
