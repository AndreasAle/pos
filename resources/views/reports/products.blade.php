@extends('layouts.app')
@section('title','Produk Terlaris')
@section('page-title','Produk Terlaris')
@section('page-subtitle','Menu dan barang yang paling laku, lengkap dengan omzet, harga pokok, dan estimasi labanya.')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@section('content')
@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $pageRev  = (float) $products->sum('total_revenue');
    $pageCost = (float) $products->sum('total_cost');
    $pageQty  = (float) $products->sum('total_qty');
    $first    = $products->first();
    $maxQty   = (float) ($products->max('total_qty') ?: 1);
@endphp
<div class="y-report">
@include('reports._nav')
@include('reports._filter', ['outlets' => $outlets, 'f' => $f, 'exports' => [
    ['label' => 'Excel', 'url' => route('reports.products.export', request()->query())],
    ['label' => 'PDF',   'url' => route('reports.products.pdf', request()->query())],
]])

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Produk terjual" :value="number_format($products->total())" sub="Jenis produk pada periode ini"/>
    <x-kpi label="Terlaris" :value="$first ? $first->product_name : '-'" :sub="$first ? number_format($first->total_qty, 0, ',', '.').' terjual' : 'Belum ada data'" tone="brand"/>
    <x-kpi label="Omzet halaman ini" :value="$rp($pageRev)" :sub="number_format($pageQty, 0, ',', '.').' item'"/>
    <x-kpi label="Estimasi laba halaman ini" :value="$rp($pageRev - $pageCost)" :sub="'HPP '.$rp($pageCost)" :tone="($pageRev - $pageCost) < 0 ? 'danger' : 'default'"/>
</div>

@if($products->count())
<x-panel class="mt-4" title="10 produk terlaris" sub="Batang hijau = jumlah terjual, ungu = omzet.">
    <div class="relative h-80 w-full"><canvas id="productChart"></canvas></div>
</x-panel>
@endif

<x-panel class="mt-4" flush title="Detail produk" :sub="number_format($products->total()).' produk'">
    @if($products->count())
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-sm">
            <thead><tr><th class="px-5 py-3 text-left">#</th><th class="px-5 py-3 text-left">Produk</th><th class="px-5 py-3 text-left">Terjual</th><th class="px-5 py-3 text-right">Omzet</th><th class="px-5 py-3 text-right">HPP</th><th class="px-5 py-3 text-right">Est. laba</th></tr></thead>
            <tbody class="divide-y divide-[#E5E7EB]">
            @foreach($products as $i => $row)
                @php $profit = $row->total_revenue - $row->total_cost; $rank = $products->firstItem() + $i; @endphp
                <tr>
                    <td class="px-5 py-3.5"><span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold {{ $rank === 1 ? 'bg-amber-100 text-amber-700' : ($rank === 2 ? 'bg-slate-100 text-slate-600' : ($rank === 3 ? 'bg-orange-100 text-orange-700' : 'text-[#94A3B8]')) }}">{{ $rank }}</span></td>
                    <td class="px-5 py-3.5 font-semibold">{{ $row->product_name }}</td>
                    <td class="px-5 py-3.5"><div class="flex items-center gap-2.5"><span class="w-10 font-semibold tabular-nums">{{ number_format($row->total_qty, 0, ',', '.') }}</span><div class="h-1.5 w-24 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-[#16A34A]" style="width: {{ max(3, round($row->total_qty / $maxQty * 100)) }}%"></div></div></div></td>
                    <td class="px-5 py-3.5 text-right font-semibold tabular-nums">{{ $rp($row->total_revenue) }}</td>
                    <td class="px-5 py-3.5 text-right tabular-nums text-[#475569]">{{ $rp($row->total_cost) }}</td>
                    <td class="px-5 py-3.5 text-right font-bold tabular-nums {{ $profit > 0 ? 'text-[#16A34A]' : 'text-red-600' }}">{{ $rp($profit) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr class="border-t-2 border-[#16A34A]/30 bg-[#ECFDF3]/50 font-semibold"><td colspan="2" class="px-5 py-3">Total halaman ini</td><td class="px-5 py-3 tabular-nums">{{ number_format($pageQty, 0, ',', '.') }}</td><td class="px-5 py-3 text-right tabular-nums">{{ $rp($pageRev) }}</td><td class="px-5 py-3 text-right tabular-nums">{{ $rp($pageCost) }}</td><td class="px-5 py-3 text-right tabular-nums">{{ $rp($pageRev - $pageCost) }}</td></tr></tfoot>
        </table>
    </div>
    <div class="border-t border-[#E5E7EB] px-5 py-3">{{ $products->links() }}</div>
    @else
        <x-empty title="Belum ada produk terjual" text="Ubah rentang tanggal atau outlet. Daftar ini terisi setelah ada transaksi lunas." icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
    @endif
</x-panel>

<script type="application/json" id="prod-data">{!! json_encode($products->take(10)) !!}</script>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('productChart'), raw = document.getElementById('prod-data');
    if (!el || !raw || !window.Chart) return;
    const prods = JSON.parse(raw.textContent);
    const labels = prods.map(p => p.product_name.length > 24 ? p.product_name.substring(0, 24) + '…' : p.product_name);
    const qtys = prods.map(p => parseFloat(p.total_qty) || 0);
    const revs = prods.map(p => parseFloat(p.total_revenue) || 0);
    const top = Math.max(...qtys);
    new Chart(el, {
        type: 'bar',
        data: { labels, datasets: [
            { label: 'Terjual', data: qtys, backgroundColor: qtys.map(v => v === top ? '#16A34A' : 'rgba(22,163,74,.45)'), borderRadius: { topRight: 6, bottomRight: 6 }, yAxisID: 'y' },
            { label: 'Omzet', data: revs, backgroundColor: 'rgba(99,102,241,.28)', borderRadius: { topRight: 4, bottomRight: 4 }, yAxisID: 'y1' }
        ] },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { font: { size: 12 }, usePointStyle: true, pointStyle: 'rectRounded' } },
                tooltip: { backgroundColor: '#0B1B3F', padding: 10, cornerRadius: 8, callbacks: { label: c => c.dataset.label === 'Terjual' ? '  Terjual: ' + Intl.NumberFormat('id').format(c.raw) : '  Omzet: Rp ' + Intl.NumberFormat('id').format(c.raw) } } },
            scales: { y: { grid: { display: false }, ticks: { font: { size: 12 }, color: '#334155' } }, y1: { display: false },
                x: { grid: { color: '#EEF2F6' }, ticks: { font: { size: 12 }, color: '#64748B', callback: v => Intl.NumberFormat('id').format(v) } } }
        }
    });
});
</script>
@endpush
