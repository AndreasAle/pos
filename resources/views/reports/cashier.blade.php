@extends('layouts.app')
@section('title','Laporan Kasir')
@section('page-title','Laporan Per Kasir')
@section('content')
@include('reports._nav')
@include('reports._filter', ['outlets' => $outlets, 'f' => $f])

<x-card title="Performa Kasir" :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">#</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Nama Kasir</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Jumlah Transaksi</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Total Revenue</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Rata-rata / Transaksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($cashiers as $i => $c)
            <tr class="hover:bg-gray-50/50">
                <td class="px-5 py-3 text-gray-400 text-xs">{{ $i + 1 }}</td>
                <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-bold text-emerald-700">{{ substr($c->cashier_name,0,1) }}</span>
                        </div>
                        <span class="font-medium text-gray-900">{{ $c->cashier_name }}</span>
                    </div>
                </td>
                <td class="px-5 py-3 text-right font-semibold text-gray-900">{{ number_format($c->total_orders) }}</td>
                <td class="px-5 py-3 text-right font-bold text-emerald-700">Rp {{ number_format($c->total_revenue,0,',','.') }}</td>
                <td class="px-5 py-3 text-right text-gray-600">
                    Rp {{ $c->total_orders > 0 ? number_format($c->total_revenue / $c->total_orders,0,',','.') : '0' }}
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-gray-400">Tidak ada data</td></tr>
            @endforelse
        </tbody>
        @if(count($cashiers))
        <tfoot>
            <tr class="bg-gray-50 border-t border-gray-200 font-semibold">
                <td colspan="2" class="px-5 py-3 text-sm">Total</td>
                <td class="px-5 py-3 text-right text-sm">{{ number_format($cashiers->sum('total_orders')) }}</td>
                <td class="px-5 py-3 text-right text-sm text-emerald-700">Rp {{ number_format($cashiers->sum('total_revenue'),0,',','.') }}</td>
                <td class="px-5 py-3"></td>
            </tr>
        </tfoot>
        @endif
    </table>
</x-card>
@endsection
