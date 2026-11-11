@extends('layouts.app')
@section('title','Riwayat Order')
@section('page-title','Riwayat Order')

@section('content')
<x-page-header eyebrow="Transaksi" title="Riwayat Order" subtitle="{{ number_format($orders->total()) }} order tercatat. Filter berdasarkan status dan tanggal."/>
{{-- Filter --}}
<form method="GET" class="grid grid-cols-2 sm:flex sm:flex-wrap items-center gap-3 mb-5 bg-white rounded-2xl border border-gray-200 shadow-sm p-3">
    <select name="status" onchange="this.form.submit()" class="col-span-2 sm:col-span-1 text-sm border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
        <option value="">Semua Status</option>
        @foreach(['paid'=>'Lunas','draft'=>'Draft','cancelled'=>'Dibatalkan','refunded'=>'Refund'] as $v => $l)
        <option value="{{ $v }}" {{ request('status')===$v?'selected':'' }}>{{ $l }}</option>
        @endforeach
    </select>
    <input type="date" name="date_from" value="{{ request('date_from') }}"
           onchange="this.form.submit()"
           class="w-full sm:w-auto min-w-0 text-sm border border-gray-300 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500">
    <span class="hidden sm:inline text-gray-400">s/d</span>
    <input type="date" name="date_to" value="{{ request('date_to') }}"
           onchange="this.form.submit()"
           class="w-full sm:w-auto min-w-0 text-sm border border-gray-300 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500">
    <a href="{{ route('orders.index') }}" class="text-center text-sm text-gray-500 hover:text-gray-700 px-3 py-2 rounded-xl hover:bg-gray-100">Reset</a>
    <span class="sm:ml-auto text-right text-sm text-gray-500">{{ $orders->total() }} order</span>
</form>

<x-card :padding="false">
    <div class="overflow-x-auto">
    <table class="w-full text-sm whitespace-nowrap">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">No. Order</th>
                <th class="text-left px-5 py-3.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Kasir</th>
                <th class="text-left px-5 py-3.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Pembayaran</th>
                <th class="text-left px-5 py-3.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th class="text-right px-5 py-3.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                <th class="text-right px-5 py-3.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Waktu</th>
                <th class="px-5 py-3.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($orders as $order)
            @php
            $statusColor = ['paid'=>'green','draft'=>'yellow','cancelled'=>'red','refunded'=>'blue'];
            $statusLabel = ['paid'=>'Lunas','draft'=>'Draft','cancelled'=>'Batal','refunded'=>'Refund'];
            @endphp
            <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="px-5 py-3.5">
                    <a href="{{ route('orders.show', $order) }}" class="font-mono text-xs text-emerald-700 hover:underline font-semibold">
                        {{ $order->order_number }}
                    </a>
                </td>
                <td class="px-5 py-3.5 text-gray-700">{{ $order->user->name }}</td>
                <td class="px-5 py-3.5 capitalize text-gray-600">{{ $order->payment_method }}</td>
                <td class="px-5 py-3.5">
                    <x-badge :color="$statusColor[$order->status] ?? 'gray'">
                        {{ $statusLabel[$order->status] ?? ucfirst($order->status) }}
                    </x-badge>
                </td>
                <td class="px-5 py-3.5 text-right font-bold text-gray-900">
                    Rp {{ number_format($order->grand_total,0,',','.') }}
                </td>
                <td class="px-5 py-3.5 text-right text-gray-500 text-xs">
                    {{ $order->created_at->format('d/m H:i') }}
                </td>
                <td class="px-5 py-3.5 text-right">
                    <a href="{{ route('orders.show', $order) }}" class="text-xs text-emerald-600 hover:underline">Detail</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-5 py-16 text-center"><div class="mx-auto w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center mb-3"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v14l-3-2-2 2-2-2-2 2-2-2-3 2V6a2 2 0 012-2z"/></svg></div><p class="text-sm font-medium text-gray-700">Tidak ada order</p><p class="text-xs text-gray-400 mt-0.5">Coba ubah filter tanggal atau status.</p></td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</x-card>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
