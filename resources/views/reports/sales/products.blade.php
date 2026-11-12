@extends('reports.sales.layout')

@section('report')
@php
    $card = 'min-w-0 rounded-xl border border-[#E5E7EB] bg-white';
    $m = $summary['metrics'];
    $link = fn ($key) => route('reports.sales.products', array_merge($queryString, ['sort' => $key, 'dir' => $sort === $key && $dir === 'desc' ? 'asc' : 'desc']));
    $heads = ['name' => ['Produk', 'left'], 'quantity' => ['Qty', 'right'], 'gross_item_sales' => ['Kotor', 'right'], 'net_sales' => ['Bersih', 'right'], 'cogs' => ['HPP', 'right']];
@endphp

@if($products->total() === 0)
<div class="{{ $card }} px-6 py-12 text-center">
    <p class="text-base font-semibold text-[#0B1B3F]">Tidak ada penjualan produk untuk filter ini</p>
    <a href="{{ route('reports.sales.products') }}" class="mt-3 inline-flex h-10 items-center rounded-lg border border-[#E5E7EB] px-4 text-sm font-semibold text-[#334155] hover:border-[#16A34A] hover:text-[#15803D]">Reset filter</a>
</div>
@else
@if($m['cost_coverage_pct'] !== null && $m['cost_coverage_pct'] < 100)
<div role="status" class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900">Snapshot HPP hanya tersedia untuk {{ \App\Support\Money::num($m['cost_coverage_pct'], 1) }}% nilai item. Margin per produk hanya ditampilkan bila HPP-nya lengkap.</div>
@endif

<section class="{{ $card }} overflow-hidden" aria-labelledby="pm-h">
    <div class="border-b border-[#E5E7EB] px-4 py-3">
        <h2 id="pm-h" class="text-base font-semibold text-[#0B1B3F]">Product Mix</h2>
        <p class="text-xs text-[#64748B]">Penjualan bersih = nilai item − diskon order yang dialokasikan proporsional. Item lama tanpa ID produk ditandai dan dikelompokkan menurut nama.</p>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full min-w-[760px] text-sm">
        <thead class="text-xs">
            <tr>
                @foreach($heads as $k => [$label, $align])
                <th scope="col" class="px-4 py-2.5 text-{{ $align }}" aria-sort="{{ $sort === $k ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                    <a href="{{ $link($k) }}" class="hover:text-[#0B1B3F] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16A34A]">{{ $label }}{{ $sort === $k ? ($dir === 'asc' ? ' ▲' : ' ▼') : '' }}</a>
                </th>
                @endforeach
                <th scope="col" class="px-4 py-2.5 text-right">Diskon alokasi</th>
                <th scope="col" class="px-4 py-2.5 text-right">Cakupan HPP</th>
                <th scope="col" class="px-4 py-2.5 text-right">Margin kotor est.</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[#E5E7EB]">
        @foreach($products as $p)
            <tr class="hover:bg-gray-50">
                <th scope="row" class="px-4 py-2.5 text-left font-medium">{{ $p['name'] }}@if($p['variant']) <span class="font-normal text-[#64748B]">· {{ $p['variant'] }}</span>@endif
                    @unless($p['matched'])<span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[12px] font-semibold text-[#475569]" title="Produk sudah dihapus atau item lama tanpa ID produk">tanpa ID</span>@endunless</th>
                <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::num($p['quantity'], 1) }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::rp($p['gross_item_sales']) }}</td>
                <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ \App\Support\Money::rp($p['net_sales']) }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums text-[#475569]">{{ \App\Support\Money::rp($p['cogs']) }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums text-[#64748B]">{{ \App\Support\Money::rp($p['discount_allocated']) }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums {{ ($p['cost_coverage_pct'] ?? 0) < 100 ? 'text-amber-700' : 'text-[#475569]' }}">{{ $p['cost_coverage_pct'] === null ? 'N/A' : \App\Support\Money::num($p['cost_coverage_pct'], 0).'%' }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::rp($p['gross_margin_estimate']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <div class="border-t border-[#E5E7EB] px-4 py-3">{{ $products->links() }}</div>
</section>
@include('reports.sales._evidence', ['evidence' => $summary['evidence'], 'notes' => $summary['notes']])
@endif
@endsection
