@extends('layouts.app')
@section('title','Laporan Kasir')
@section('page-title','Laporan Kasir')
@section('page-subtitle','Performa tiap kasir pada periode yang dipilih: jumlah transaksi, omzet, dan rata-rata per transaksi.')
@section('content')
@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $totalOrders  = $cashiers->sum('total_orders');
    $totalRevenue = (float) $cashiers->sum('total_revenue');
    $top = $cashiers->sortByDesc('total_revenue')->first();
@endphp
<div class="y-report">
@include('reports._nav')
@include('reports._filter', ['outlets' => $outlets, 'f' => $f])

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Kasir aktif" :value="count($cashiers)" sub="Punya transaksi pada periode ini"/>
    <x-kpi label="Total transaksi" :value="number_format($totalOrders)" sub="Semua kasir"/>
    <x-kpi label="Total omzet" :value="$rp($totalRevenue)" :sub="$totalOrders > 0 ? 'Rata-rata '.$rp($totalRevenue / $totalOrders).' / transaksi' : 'Belum ada transaksi'" tone="brand"/>
    <x-kpi label="Kasir terbaik" :value="$top ? $top->cashier_name : '-'" :sub="$top ? $rp($top->total_revenue).' · '.($totalRevenue > 0 ? round($top->total_revenue / $totalRevenue * 100) : 0).'% dari omzet' : 'Belum ada data'"/>
</div>

<x-panel class="mt-4" flush title="Performa kasir" sub="Diurutkan dari omzet tertinggi.">
    @if(count($cashiers))
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
            <thead><tr><th class="px-5 py-3 text-left">#</th><th class="px-5 py-3 text-left">Kasir</th><th class="px-5 py-3 text-right">Transaksi</th><th class="px-5 py-3 text-right">Omzet</th><th class="px-5 py-3 text-right">Rata-rata / transaksi</th><th class="px-5 py-3 text-left">Kontribusi</th></tr></thead>
            <tbody class="divide-y divide-[#E5E7EB]">
            @foreach($cashiers as $i => $c)
                @php $share = $totalRevenue > 0 ? $c->total_revenue / $totalRevenue * 100 : 0; @endphp
                <tr>
                    <td class="px-5 py-3.5 text-xs text-[#94A3B8]">{{ $i + 1 }}</td>
                    <td class="px-5 py-3.5"><span class="flex items-center gap-3"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#ECFDF3] text-xs font-bold text-[#16A34A]">{{ strtoupper(mb_substr($c->cashier_name, 0, 1)) }}</span><span class="font-semibold">{{ $c->cashier_name }}</span></span></td>
                    <td class="px-5 py-3.5 text-right font-semibold tabular-nums">{{ number_format($c->total_orders) }}</td>
                    <td class="px-5 py-3.5 text-right font-bold tabular-nums">{{ $rp($c->total_revenue) }}</td>
                    <td class="px-5 py-3.5 text-right tabular-nums text-[#475569]">{{ $c->total_orders > 0 ? $rp($c->total_revenue / $c->total_orders) : $rp(0) }}</td>
                    <td class="px-5 py-3.5"><div class="flex items-center gap-2"><div class="h-1.5 w-24 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-[#16A34A]" style="width: {{ round($share) }}%"></div></div><span class="text-xs tabular-nums text-[#64748B]">{{ round($share) }}%</span></div></td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr class="border-t-2 border-[#16A34A]/30 bg-[#ECFDF3]/50 font-semibold"><td colspan="2" class="px-5 py-3">Total</td><td class="px-5 py-3 text-right tabular-nums">{{ number_format($totalOrders) }}</td><td class="px-5 py-3 text-right tabular-nums">{{ $rp($totalRevenue) }}</td><td colspan="2"></td></tr></tfoot>
        </table>
    </div>
    @else
        <x-empty title="Belum ada transaksi pada periode ini" text="Ubah rentang tanggal atau outlet untuk melihat performa kasir." icon="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
    @endif
</x-panel>
</div>
@endsection
