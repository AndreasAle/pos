@extends('layouts.app')
@section('title','Detail Shift')
@section('page-title','Detail Shift')
@section('content')
<div class="flex items-center gap-4 mb-5">
    <a href="{{ route('shifts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Riwayat Shift</a>
    @if($shift->status === 'open' && $shift->user_id === auth()->id())
    <a href="{{ route('shifts.close', $shift) }}"
       class="ml-auto inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-xl">
        Tutup Shift
    </a>
    @endif
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-stat-card label="Total Penjualan" value="Rp {{ number_format($totalSales,0,',','.') }}" color="emerald"/>
    <x-stat-card label="Total Transaksi" value="{{ $totalOrders }}" color="blue"/>
    <x-stat-card label="Total Cash" value="Rp {{ number_format($totalCash,0,',','.') }}" color="purple"/>
    <x-stat-card label="Modal Awal" value="Rp {{ number_format($shift->opening_cash,0,',','.') }}" color="orange"/>
</div>

@if($shift->status === 'closed')
<x-card title="Rekap Penutupan" class="mb-4">
    <dl class="grid grid-cols-2 gap-3 text-sm">
        <div><dt class="text-gray-500">Cash Expected</dt><dd class="font-semibold">Rp {{ number_format($shift->closing_cash_expected,0,',','.') }}</dd></div>
        <div><dt class="text-gray-500">Cash Actual</dt><dd class="font-semibold">Rp {{ number_format($shift->closing_cash_actual,0,',','.') }}</dd></div>
        <div><dt class="text-gray-500">Selisih</dt>
            <dd class="font-semibold {{ $shift->cash_difference < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                {{ $shift->cash_difference >= 0 ? '+' : '' }}Rp {{ number_format($shift->cash_difference,0,',','.') }}
            </dd>
        </div>
        @if($shift->notes)<div class="col-span-2"><dt class="text-gray-500">Catatan</dt><dd>{{ $shift->notes }}</dd></div>@endif
    </dl>
</x-card>
@endif

<x-card title="Transaksi dalam Shift" :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">No. Order</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Pembayaran</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Total</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Waktu</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($shift->orders as $order)
            <tr>
                <td class="px-5 py-3 font-mono text-xs text-emerald-700">
                    <a href="{{ route('orders.show', $order) }}" class="hover:underline">{{ $order->order_number }}</a>
                </td>
                <td class="px-5 py-3 capitalize text-gray-600">{{ $order->payment_method }}</td>
                <td class="px-5 py-3">
                    <x-badge :color="$order->status==='paid'?'green':($order->status==='cancelled'?'red':'yellow')">
                        {{ ucfirst($order->status) }}
                    </x-badge>
                </td>
                <td class="px-5 py-3 text-right font-semibold">Rp {{ number_format($order->grand_total,0,',','.') }}</td>
                <td class="px-5 py-3 text-right text-gray-500 text-xs">{{ $order->created_at->format('H:i') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Belum ada transaksi</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
@endsection
