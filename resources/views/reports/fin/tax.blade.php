@extends('layouts.app')
@section('title', 'Laporan Pajak')
@section('page-title', 'Laporan Pajak')

@section('content')
@php $card = 'min-w-0 rounded-lg border border-gray-200 bg-white'; $rp = fn ($v) => \App\Support\Money::rp($v); @endphp
<div class="text-[14px] text-gray-800">
@include('reports.fin._period', ['exports' => ['Excel' => 'xlsx']])

<div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
    <div class="{{ $card }} p-4"><p class="text-xs font-semibold text-gray-500">Dasar pengenaan pajak (DPP)</p><p class="mt-1 text-2xl font-bold tabular-nums text-[#102C35]">{{ $rp($r['taxable_base']) }}</p><p class="mt-1 text-xs text-gray-500">Penjualan bersih order kena pajak</p></div>
    <div class="{{ $card }} border-[#0C7856]/40 p-4"><p class="text-xs font-semibold text-[#0C7856]">Pajak dipungut</p><p class="mt-1 text-2xl font-bold tabular-nums text-[#102C35]">{{ $rp($r['tax_collected']) }}</p><p class="mt-1 text-xs text-gray-500">{{ $r['taxed_orders'] }} dari {{ $r['orders'] }} order</p></div>
    <div class="{{ $card }} p-4"><p class="text-xs font-semibold text-gray-500">Tarif efektif</p><p class="mt-1 text-2xl font-bold tabular-nums text-[#102C35]">{{ $r['effective_rate_pct'] === null ? 'N/A' : \App\Support\Money::num($r['effective_rate_pct'], 2).'%' }}</p><p class="mt-1 text-xs text-gray-500">Pengaturan: {{ $r['configured_rate_pct'] === null ? 'pajak nonaktif' : $r['configured_rate_pct'].'%' }}</p></div>
    <div class="{{ $card }} p-4"><p class="text-xs font-semibold text-gray-500">Setoran tercatat</p><p class="mt-1 text-2xl font-bold tabular-nums text-[#102C35]">{{ $rp($r['tax_remitted']) }}</p><p class="mt-1 text-xs text-gray-500">Biaya kategori Setoran Pajak</p></div>
    <div class="{{ $card }} p-4"><p class="text-xs font-semibold text-gray-500">Saldo belum disetor</p><p class="mt-1 text-2xl font-bold tabular-nums {{ $r['tax_balance'] > 0 ? 'text-amber-700' : 'text-[#102C35]' }}">{{ $rp($r['tax_balance']) }}</p><p class="mt-1 text-xs text-gray-500">Dipungut − setoran periode ini</p></div>
</div>

@foreach($r['warnings'] as $w)<div role="status" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900">{{ $w }}</div>@endforeach

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <section class="{{ $card }} overflow-hidden xl:col-span-2" aria-labelledby="td">
        <h2 id="td" class="border-b border-gray-100 px-4 py-3 text-sm font-semibold">Rincian per hari</h2>
        <div class="overflow-x-auto"><table class="w-full min-w-[520px] text-sm">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-500"><tr><th scope="col" class="px-4 py-2.5 text-left">Tanggal</th><th scope="col" class="px-4 py-2.5 text-right">Order</th><th scope="col" class="px-4 py-2.5 text-right">DPP</th><th scope="col" class="px-4 py-2.5 text-right">Pajak</th><th scope="col" class="px-4 py-2.5 text-right">Service</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($r['by_day'] as $d)
                <tr><td class="px-4 py-2.5">{{ \Carbon\Carbon::parse($d['date'])->locale('id')->isoFormat('ddd, D MMM Y') }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $d['orders'] }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp($d['base']) }}</td><td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ $rp($d['tax']) }}</td><td class="px-4 py-2.5 text-right tabular-nums text-gray-500">{{ $rp($d['service']) }}</td></tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">Belum ada transaksi paid pada filter ini.</td></tr>
            @endforelse
            </tbody>
            @if(count($r['by_day']))<tfoot class="border-t-2 border-[#0C7856]/30 bg-emerald-50/50 font-semibold"><tr><th scope="row" class="px-4 py-2.5 text-left">Total</th><td class="px-4 py-2.5 text-right tabular-nums">{{ $r['orders'] }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp($r['taxable_base']) }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp($r['tax_collected']) }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp($r['service_collected']) }}</td></tr></tfoot>@endif
        </table></div>
    </section>
    <div class="space-y-4">
        <section class="{{ $card }} p-4" aria-labelledby="to"><h2 id="to" class="text-sm font-semibold">Per outlet</h2>
            <ul class="mt-2 divide-y divide-gray-100 text-sm">@forelse($r['by_outlet'] as $o)<li class="flex justify-between py-2"><span>{{ $o['name'] }}</span><span class="tabular-nums">{{ $rp($o['tax']) }}</span></li>@empty<li class="py-4 text-center text-gray-400">Tidak ada data</li>@endforelse</ul></section>
        <section class="{{ $card }} p-4" aria-labelledby="tr"><h2 id="tr" class="text-sm font-semibold">Pajak atas order refund/batal</h2>
            <p class="mt-2 text-xl font-bold tabular-nums">{{ $rp($r['tax_reversed']['amount']) }}</p><p class="text-xs text-gray-500">{{ $r['tax_reversed']['orders'] }} order; tidak termasuk pajak dipungut di atas.</p></section>
    </div>
</div>
@include('reports.sales._evidence', ['evidence' => $r['evidence'], 'notes' => ['Laporan ini adalah rekap pajak yang dipungut sistem POS, bukan SPT atau bukti setor. Setoran hanya yang dicatat sebagai biaya kategori Setoran Pajak.']])
</div>
@endsection
