@extends('layouts.app')
@section('title','Laporan Produk Terlaris')
@section('page-title','Laporan Produk Terlaris')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@section('content')
@include('reports._nav')

<div class="flex items-center justify-end gap-2 mb-4">
    <a href="{{ route('reports.products.export', request()->query()) }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-4 py-2 rounded-xl transition-colors">
        📊 Export Excel
    </a>
    <a href="{{ route('reports.products.pdf', request()->query()) }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 px-4 py-2 rounded-xl transition-colors">
        📄 Download PDF
    </a>
</div>

@include('reports._filter', ['outlets' => $outlets, 'f' => $f])

{{-- Top product bar chart --}}
@if($products->count())
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
    <h3 class="text-sm font-semibold text-gray-900 mb-4">Top 10 Produk Terlaris</h3>
    <div style="position:relative;height:320px;width:100%;"><canvas id="productChart"></canvas></div>
</div>
@endif

<x-card title="Detail Produk ({{ $products->total() }} produk)" :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">#</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Nama Produk</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Qty Terjual</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Total Revenue</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Total HPP</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Est. Profit</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($products as $i => $row)
            <tr class="hover:bg-gray-50/50">
                <td class="px-5 py-3">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                                 {{ $i === 0 ? 'bg-yellow-100 text-yellow-700' : ($i === 1 ? 'bg-gray-100 text-gray-500' : ($i === 2 ? 'bg-orange-100 text-orange-600' : 'bg-gray-50 text-gray-400')) }}">
                        {{ $products->firstItem() + $i }}
                    </span>
                </td>
                <td class="px-5 py-3 font-medium text-gray-900">{{ $row->product_name }}</td>
                <td class="px-5 py-3 text-right font-semibold text-gray-900">{{ number_format($row->total_qty,0) }}</td>
                <td class="px-5 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format($row->total_revenue,0,',','.') }}</td>
                <td class="px-5 py-3 text-right text-gray-600">Rp {{ number_format($row->total_cost,0,',','.') }}</td>
                <td class="px-5 py-3 text-right font-bold {{ ($row->total_revenue - $row->total_cost) > 0 ? 'text-emerald-700' : 'text-red-600' }}">
                    Rp {{ number_format($row->total_revenue - $row->total_cost,0,',','.') }}
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400">Tidak ada data</td></tr>
            @endforelse
        </tbody>
        @if($products->count())
        <tfoot>
            <tr class="bg-gray-50 border-t border-gray-200 font-semibold">
                <td colspan="2" class="px-5 py-3 text-sm text-gray-700">Total Halaman Ini</td>
                <td class="px-5 py-3 text-right text-sm">{{ number_format($products->sum('total_qty'),0) }}</td>
                <td class="px-5 py-3 text-right text-sm text-emerald-700">Rp {{ number_format($products->sum('total_revenue'),0,',','.') }}</td>
                <td class="px-5 py-3 text-right text-sm">Rp {{ number_format($products->sum('total_cost'),0,',','.') }}</td>
                <td class="px-5 py-3 text-right text-sm text-emerald-700">Rp {{ number_format($products->sum('total_revenue') - $products->sum('total_cost'),0,',','.') }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</x-card>
<div class="mt-4">{{ $products->links() }}</div>

{{-- JSON for chart --}}
<script type="application/json" id="prod-data">{!! json_encode($products->take(10)) !!}</script>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const prodEl  = document.getElementById('productChart');
    const prodRaw = document.getElementById('prod-data');
    if (!prodEl || !prodRaw) return;

    const prods  = JSON.parse(prodRaw.textContent);
    const labels = prods.map(p => p.product_name.length > 22 ? p.product_name.substring(0,22)+'...' : p.product_name);
    const qtys   = prods.map(p => parseFloat(p.total_qty) || 0);
    const revs   = prods.map(p => parseFloat(p.total_revenue) || 0);
    const maxQty = Math.max(...qtys);

    new Chart(prodEl, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Qty Terjual', data: qtys,
                  backgroundColor: qtys.map(v => v===maxQty ? 'rgba(16,185,129,0.85)' : 'rgba(16,185,129,0.4)'),
                  borderColor: 'rgba(16,185,129,0.8)', borderWidth:1.5,
                  borderRadius: { topRight:6, bottomRight:6 },
                  yAxisID: 'y' },
                { label: 'Revenue', data: revs,
                  backgroundColor: 'rgba(99,102,241,0.25)',
                  borderColor: 'rgba(99,102,241,0.7)', borderWidth:1.5,
                  borderRadius: { topRight:4, bottomRight:4 },
                  yAxisID: 'y1' }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { font:{size:11}, usePointStyle:true, pointStyle:'rectRounded' }},
                tooltip: {
                    backgroundColor: 'rgba(17,24,39,0.92)', padding:10,
                    callbacks: {
                        label(c) {
                            if (c.dataset.label === 'Qty Terjual') return '  Qty: '+Intl.NumberFormat('id').format(c.raw)+' pcs';
                            return '  Revenue: Rp '+Intl.NumberFormat('id').format(c.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    grid: { display: false },
                    ticks: { font:{size:11}, color:'#374151' }
                },
                y1: { display: false },
                x: {
                    grid: { color: '#f3f4f6' },
                    ticks: { font:{size:10}, callback: v => Intl.NumberFormat('id').format(v) }
                }
            }
        }
    });
});
</script>
@endpush

