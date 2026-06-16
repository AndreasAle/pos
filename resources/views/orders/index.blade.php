@extends('layouts.app')
@section('title','Riwayat Order')
@section('page-title','Riwayat Order')

@section('content')
{{-- Filter --}}
<form method="GET" class="flex flex-wrap items-center gap-3 mb-5">
    <select name="status" onchange="this.form.submit()" class="text-sm border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
        <option value="">Semua Status</option>
        @foreach(['paid'=>'Lunas','draft'=>'Draft','cancelled'=>'Dibatalkan','refunded'=>'Refund'] as $v => $l)
        <option value="{{ $v }}" {{ request('status')===$v?'selected':'' }}>{{ $l }}</option>
        @endforeach
    </select>
    <input type="date" name="date_from" value="{{ request('date_from', today()->format('Y-m-d')) }}"
           onchange="this.form.submit()"
           class="text-sm border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
    <span class="text-gray-400">s/d</span>
    <input type="date" name="date_to" value="{{ request('date_to', today()->format('Y-m-d')) }}"
           onchange="this.form.submit()"
           class="text-sm border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
    <a href="{{ route('orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700 px-3 py-2 rounded-xl hover:bg-gray-100">Reset</a>
    <span class="ml-auto text-sm text-gray-500">{{ $orders->total() }} order</span>
</form>

<x-card :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">No. Order</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Kasir</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Pembayaran</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Total</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Waktu</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($orders as $order)
            @php
            $statusColor = ['paid'=>'green','draft'=>'yellow','cancelled'=>'red','refunded'=>'blue'];
            $statusLabel = ['paid'=>'Lunas','draft'=>'Draft','cancelled'=>'Batal','refunded'=>'Refund'];
            @endphp
            <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="px-5 py-3">
                    <a href="{{ route('orders.show', $order) }}" class="font-mono text-xs text-emerald-700 hover:underline font-semibold">
                        {{ $order->order_number }}
                    </a>
                </td>
                <td class="px-5 py-3 text-gray-700">{{ $order->user->name }}</td>
                <td class="px-5 py-3 capitalize text-gray-600">{{ $order->payment_method }}</td>
                <td class="px-5 py-3">
                    <x-badge :color="$statusColor[$order->status] ?? 'gray'">
                        {{ $statusLabel[$order->status] ?? ucfirst($order->status) }}
                    </x-badge>
                </td>
                <td class="px-5 py-3 text-right font-bold text-gray-900">
                    Rp {{ number_format($order->grand_total,0,',','.') }}
                </td>
                <td class="px-5 py-3 text-right text-gray-500 text-xs">
                    {{ $order->created_at->format('d/m H:i') }}
                </td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('orders.show', $order) }}" class="text-xs text-emerald-600 hover:underline">Detail</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-gray-400">Tidak ada order</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
