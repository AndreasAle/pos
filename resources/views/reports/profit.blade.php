@extends('layouts.app')
@section('title','Laporan Estimasi Profit')
@section('page-title','Laporan Estimasi Profit')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@section('content')
@include('reports._nav')

<div class="flex items-center justify-end gap-2 mb-4">
    <a href="{{ route('reports.profit.pdf', request()->query()) }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 px-4 py-2 rounded-xl transition-colors">
        📄 Download PDF
    </a>
</div>

@include('reports._filter', ['outlets' => $outlets, 'f' => $f])

{{-- Summary --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <x-stat-card label="Total Revenue" value="Rp {{ number_format($totalRevenue,0,',','.') }}" color="emerald"/>
    <x-stat-card label="Total HPP (COGS)" value="Rp {{ number_format($totalCogs,0,',','.') }}" color="orange"/>
    <x-stat-card label="Estimasi Profit" value="Rp {{ number_format($totalProfit,0,',','.') }}" color="blue"/>
    <x-stat-card label="Margin Kotor" value="{{ number_format($margin,1) }}%" color="purple"/>
</div>

<div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 mb-5 text-sm text-blue-700">
    <strong>Catatan:</strong> Profit dihitung berdasarkan <em>harga modal</em> yang diinput pada produk. Untuk akurasi lebih baik, gunakan fitur <strong>Resep + Bahan Baku</strong>.
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    {{-- Chart --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Grafik Revenue vs COGS Harian</h3>
        @if(count($rows))
        <div style="position:relative;height:300px;width:100%;"><canvas id="profitChart"></canvas></div>
        @else
        <p class="text-sm text-gray-400 text-center py-16">Tidak ada data</p>
        @endif
    </div>

    {{-- Daily breakdown table --}}
    <x-card title="Breakdown Harian" :padding="false">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Tanggal</th>
                    <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Revenue</th>
                    <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase">HPP</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Profit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($rows as $row)
                @php $profit = $row->revenue - $row->cogs; @endphp
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3 text-gray-700">{{ \Carbon\Carbon::parse($row->date)->format('d M Y') }}</td>
                    <td class="px-3 py-3 text-right text-emerald-700 font-semibold">Rp {{ number_format($row->revenue,0,',','.') }}</td>
                    <td class="px-3 py-3 text-right text-orange-600">Rp {{ number_format($row->cogs,0,',','.') }}</td>
                    <td class="px-5 py-3 text-right font-bold {{ $profit >= 0 ? 'text-blue-700' : 'text-red-600' }}">
                        Rp {{ number_format($profit,0,',','.') }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-400">Tidak ada data</td></tr>
                @endforelse
            </tbody>
            @if(count($rows))
            <tfoot>
                <tr class="bg-gray-50 border-t border-gray-200 font-bold">
                    <td class="px-5 py-3 text-sm">TOTAL</td>
                    <td class="px-3 py-3 text-right text-sm text-emerald-700">Rp {{ number_format($totalRevenue,0,',','.') }}</td>
                    <td class="px-3 py-3 text-right text-sm text-orange-600">Rp {{ number_format($totalCogs,0,',','.') }}</td>
                    <td class="px-5 py-3 text-right text-sm text-blue-700">Rp {{ number_format($totalProfit,0,',','.') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </x-card>
</div>

<script type="application/json" id="profit-rows">{!! json_encode($rows) !!}</script>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const profitEl  = document.getElementById('profitChart');
    const profitRaw = document.getElementById('profit-rows');
    if (!profitEl || !profitRaw) return;

    const rows = JSON.parse(profitRaw.textContent);
    if (!rows.length) return;

    const labels  = rows.map(r => new Date(r.date).toLocaleDateString('id-ID', {day:'2-digit', month:'short'}));
    const revenue = rows.map(r => parseFloat(r.revenue) || 0);
    const cogs    = rows.map(r => parseFloat(r.cogs)    || 0);
    const profit  = rows.map((r,i) => revenue[i] - cogs[i]);

    new Chart(profitEl, {
        data: {
            labels,
            datasets: [
                { type:'bar', label:'Revenue', data: revenue,
                  backgroundColor: 'rgba(16,185,129,0.4)',
                  borderColor: 'rgb(16,185,129)', borderWidth:1.5,
                  borderRadius:6, borderSkipped:false, order:2 },
                { type:'bar', label:'HPP (COGS)', data: cogs,
                  backgroundColor: 'rgba(245,158,11,0.35)',
                  borderColor: 'rgb(245,158,11)', borderWidth:1.5,
                  borderRadius:6, borderSkipped:false, order:3 },
                { type:'line', label:'Profit', data: profit,
                  borderColor: 'rgb(99,102,241)',
                  backgroundColor: 'rgba(99,102,241,0.08)',
                  borderWidth: 2.5,
                  pointRadius: 5,
                  pointBackgroundColor: profit.map(v => v >= 0 ? 'rgb(99,102,241)' : '#ef4444'),
                  pointBorderColor: '#fff',
                  pointBorderWidth: 2,
                  fill: false,
                  tension: 0.4,
                  order: 1 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode:'index', intersect:false },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font:{size:11}, usePointStyle:true, pointStyle:'rectRounded' }
                },
                tooltip: {
                    backgroundColor: 'rgba(17,24,39,0.92)', padding:10,
                    callbacks: {
                        label(c) {
                            const val = 'Rp ' + Intl.NumberFormat('id').format(Math.abs(c.raw));
                            if (c.dataset.label === 'Profit') {
                                return '  ' + c.dataset.label + ': ' + (c.raw < 0 ? '-' : '') + val;
                            }
                            return '  ' + c.dataset.label + ': ' + val;
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero:true, grid:{color:'#f3f4f6'},
                     ticks:{ font:{size:10}, maxTicksLimit:6, callback(v){
                         if(v>=1000000) return 'Rp '+(v/1000000).toFixed(1)+'jt';
                         if(v>=1000)    return 'Rp '+(v/1000).toFixed(0)+'rb';
                         return v===0?'0':'Rp '+v;
                     }}},
                x: { grid:{display:false}, ticks:{font:{size:10}, maxRotation:0} }
            }
        }
    });
});
</script>
@endpush

