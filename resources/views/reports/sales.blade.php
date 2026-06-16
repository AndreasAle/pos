@extends('layouts.app')
@section('title','Laporan Penjualan')
@section('page-title','Laporan Penjualan')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@section('content')
@include('reports._nav')

{{-- Export buttons --}}
<div class="flex items-center justify-end gap-2 mb-4">
    <a href="{{ route('reports.sales.export', request()->query()) }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-4 py-2 rounded-xl transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Export Excel
    </a>
    <a href="{{ route('reports.sales.pdf', request()->query()) }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 px-4 py-2 rounded-xl transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Download PDF
    </a>
</div>

@include('reports._filter', ['outlets' => $outlets, 'f' => $f])

{{-- Summary Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <x-stat-card label="Total Omzet" value="Rp {{ number_format($summary['total_revenue'],0,',','.') }}" color="emerald"/>
    <x-stat-card label="Total Transaksi" value="{{ number_format($summary['total_orders']) }}" color="blue"/>
    <x-stat-card label="Rata-rata Order" value="Rp {{ number_format($summary['avg_order'],0,',','.') }}" color="purple"/>
    <x-stat-card label="Total Diskon" value="Rp {{ number_format($summary['total_discount'],0,',','.') }}" color="orange"/>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
    {{-- Daily chart --}}
    <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Grafik Penjualan Harian</h3>
        <div style="position:relative;height:280px;width:100%;"><canvas id="salesChart"></canvas></div>
    </div>

    {{-- Payment breakdown --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Metode Pembayaran</h3>
        @if(count($paymentBreakdown))
        <div style="position:relative;height:180px;width:100%;"><canvas id="payChart"></canvas></div>
        <div class="mt-4 space-y-2">
            @foreach($paymentBreakdown as $p)
            <div class="flex items-center justify-between text-sm">
                <span class="capitalize text-gray-600">{{ $p->payment_method }}</span>
                <span class="font-semibold text-gray-900">{{ $p->count }}x Â· Rp {{ number_format($p->total,0,',','.') }}</span>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-gray-400 text-center py-10">Tidak ada data</p>
        @endif
    </div>
</div>

{{-- Daily table --}}
<x-card title="Ringkasan Harian" :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Tanggal</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Transaksi</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Omzet</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($daily as $row)
            <tr class="hover:bg-gray-50/50">
                <td class="px-5 py-3 font-medium text-gray-900">
                    {{ \Carbon\Carbon::parse($row->date)->isoFormat('dddd, D MMMM Y') }}
                </td>
                <td class="px-5 py-3 text-right text-gray-700">{{ number_format($row->orders) }}</td>
                <td class="px-5 py-3 text-right font-semibold text-emerald-700">Rp {{ number_format($row->revenue,0,',','.') }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="px-5 py-10 text-center text-sm text-gray-400">Tidak ada data pada periode ini</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>

{{-- JSON data for charts --}}
<script type="application/json" id="daily-data">{!! json_encode($daily) !!}</script>
@if(count($paymentBreakdown))
<script type="application/json" id="pay-data">{!! json_encode($paymentBreakdown) !!}</script>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Sales Chart ─────────────────────────────────────────────────────────
    const salesEl  = document.getElementById('salesChart');
    const dailyRaw = document.getElementById('daily-data');
    if (salesEl && dailyRaw) {
        const daily   = JSON.parse(dailyRaw.textContent);
        const labels  = daily.map(d => new Date(d.date).toLocaleDateString('id-ID', {day:'2-digit',month:'short'}));
        const revenue = daily.map(d => parseFloat(d.revenue) || 0);
        const orders  = daily.map(d => parseInt(d.orders)   || 0);
        const maxRev  = Math.max(...revenue);
        new Chart(salesEl, {
            data: { labels, datasets: [
                { type:'bar', label:'Omzet', data: revenue,
                  backgroundColor: revenue.map(v => v===maxRev ? 'rgba(16,185,129,0.85)' : 'rgba(16,185,129,0.35)'),
                  borderColor: 'rgba(16,185,129,0.8)', borderWidth:1.5, borderRadius:7, borderSkipped:false, order:2 },
                { type:'line', label:'Transaksi', data: orders,
                  borderColor: 'rgb(99,102,241)', backgroundColor: 'rgba(99,102,241,0.06)',
                  borderWidth:2.5, pointRadius:5, pointBackgroundColor:'rgb(99,102,241)',
                  pointBorderColor:'#fff', pointBorderWidth:2, pointHoverRadius:7,
                  fill:true, tension:0.4, order:1, yAxisID:'y1' }
            ]},
            options: {
                responsive:true, maintainAspectRatio:false,
                interaction: { mode:'index', intersect:false },
                plugins: { legend:{display:false},
                    tooltip:{ backgroundColor:'rgba(17,24,39,0.92)', padding:10,
                        callbacks:{ label(c){ return c.dataset.label==='Omzet'
                            ? '  Omzet: Rp '+Intl.NumberFormat('id').format(c.raw)
                            : '  Transaksi: '+c.raw+'x'; }}}},
                scales: {
                    y:  { position:'left',  beginAtZero:true, grid:{color:'#f3f4f6'},
                          ticks:{ font:{size:10}, maxTicksLimit:6, callback(v){
                              if(v>=1000000) return 'Rp '+(v/1000000).toFixed(1)+'jt';
                              if(v>=1000)    return 'Rp '+(v/1000).toFixed(0)+'rb';
                              return v===0?'0':'Rp '+v; }}},
                    y1: { position:'right', beginAtZero:true, grid:{drawOnChartArea:false},
                          ticks:{font:{size:10}, stepSize:1, callback:v=>Number.isInteger(v)?v+'x':''} },
                    x:  { grid:{display:false}, ticks:{font:{size:10}, maxRotation:0} }
                }
            }
        });
    }
    // ── Payment Donut ────────────────────────────────────────────────────────
    const payEl  = document.getElementById('payChart');
    const payRaw = document.getElementById('pay-data');
    if (payEl && payRaw) {
        const payData = JSON.parse(payRaw.textContent);
        const colors  = ['#10b981','#3b82f6','#8b5cf6','#f59e0b','#ef4444','#6b7280'];
        new Chart(payEl, {
            type:'doughnut',
            data:{ labels: payData.map(p=>p.payment_method.toUpperCase()),
                   datasets:[{ data: payData.map(p=>parseFloat(p.total)),
                               backgroundColor: colors.slice(0,payData.length),
                               borderWidth:3, borderColor:'#fff', hoverOffset:8 }]},
            options:{ responsive:true, maintainAspectRatio:false, cutout:'68%',
                plugins:{ legend:{display:false},
                    tooltip:{ callbacks:{ label(c){
                        const t=c.dataset.data.reduce((a,b)=>a+b,0);
                        const pct=t>0?(c.raw/t*100).toFixed(1):0;
                        return '  Rp '+Intl.NumberFormat('id').format(c.raw)+' ('+pct+'%)';
                    }}}}}
        });
    }
});
</script>
@endpush



