@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@section('content')

{{-- Trial countdown banner --}}
@php
    $sub = $currentBusiness->activeSubscription;
@endphp
{{-- Trial banner belongs to the subscription module; with it off there is no
     upgrade page to link to and route() would throw. --}}
@if(config('pos.features.subscription') && $sub && $sub->status === 'trial' && $sub->ends_at)
    <div x-data="{
            endsAt: new Date('{{ $sub->ends_at->copy()->endOfDay()->toIso8601String() }}').getTime(),
            remaining: '',
            tick() {
                const diff = this.endsAt - Date.now();
                if (diff <= 0) { this.remaining = 'Masa trial telah berakhir'; return; }
                const d = Math.floor(diff / 86400000);
                const h = Math.floor((diff % 86400000) / 3600000);
                const m = Math.floor((diff % 3600000) / 60000);
                const s = Math.floor((diff % 60000) / 1000);
                this.remaining = d + 'h ' + h + 'j ' + m + 'm ' + s + 'd';
            }
         }"
         x-init="tick(); setInterval(() => tick(), 1000)"
         class="mb-6 rounded-2xl bg-gradient-to-r from-emerald-600 to-emerald-700 text-white px-5 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-lg shadow-emerald-100">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center text-xl shrink-0">🎁</span>
            <div>
                <p class="font-semibold text-sm">Anda sedang menikmati Trial Gratis — semua fitur terbuka penuh</p>
                <p class="text-xs text-emerald-50/90">Sisa waktu trial: <span class="font-mono font-semibold" x-text="remaining"></span> &middot; berakhir {{ $sub->ends_at->format('d M Y') }}</p>
            </div>
        </div>
        @if(auth()->user()->isOwner())
        <a href="{{ route('saas.current') }}" class="shrink-0 px-4 py-2 rounded-xl bg-white text-emerald-700 text-sm font-semibold hover:bg-emerald-50 transition-colors">
            Upgrade Sekarang
        </a>
        @endif
    </div>
@endif

{{-- Outlet filter --}}
@if(count($outlets) > 1)
<div class="mb-5 flex items-center gap-3">
    <span class="text-sm text-gray-500 font-medium">Filter Outlet:</span>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('dashboard') }}"
           class="px-3 py-1.5 rounded-lg text-sm font-medium border transition-colors
                  {{ !$outletId ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-300 hover:border-emerald-400' }}">
            Semua Outlet
        </a>
        @foreach($outlets as $outlet)
        <a href="{{ route('dashboard', ['outlet_id' => $outlet->id]) }}"
           class="px-3 py-1.5 rounded-lg text-sm font-medium border transition-colors
                  {{ $outletId == $outlet->id ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-300 hover:border-emerald-400' }}">
            {{ $outlet->name }}
        </a>
        @endforeach
    </div>
</div>
@endif

{{-- Stats --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <x-stat-card
        label="Omzet Hari Ini"
        value="Rp {{ number_format($summary['todayRevenue'], 0, ',', '.') }}"
        color="emerald"
        :sub="$summary['todayOrders'] . ' transaksi'"
        icon='<svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>'
    />
    <x-stat-card
        label="Total Transaksi"
        value="{{ number_format($summary['todayOrders']) }}"
        color="blue"
        sub="hari ini"
        icon='<svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>'
    />
    <x-stat-card
        label="Estimasi Profit"
        value="Rp {{ number_format($summary['todayProfit'], 0, ',', '.') }}"
        color="purple"
        sub="berdasarkan harga modal"
        icon='<svg class="w-6 h-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>'
    />
    <x-stat-card
        label="Rata-rata Order"
        value="Rp {{ number_format($summary['avgOrder'], 0, ',', '.') }}"
        color="orange"
        sub="per transaksi hari ini"
        icon='<svg class="w-6 h-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>'
    />
</div>

{{-- Charts Row --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
    {{-- Sales Chart --}}
    <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-900">Grafik Penjualan 7 Hari Terakhir</h3>
            <div class="flex items-center gap-4 text-xs text-gray-400">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-emerald-400 inline-block"></span>Omzet</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-1 bg-indigo-400 inline-block rounded"></span>Transaksi</span>
            </div>
        </div>
        {{-- Wrapper dengan fixed height agar Chart.js bisa kalkulasi ukuran dengan benar --}}
        <div style="position:relative; height:260px;">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    {{-- Payment Breakdown --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Metode Pembayaran Hari Ini</h3>
        @if(count($paymentBreakdown) > 0)
        <div style="position:relative; height:180px;">
            <canvas id="paymentChart"></canvas>
        </div>
        <div class="mt-4 space-y-2">
            @foreach($paymentBreakdown as $p)
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600 capitalize">{{ $p['payment_method'] }}</span>
                <span class="font-medium text-gray-900">{{ $p['count'] }}x — Rp {{ number_format($p['total'], 0, ',', '.') }}</span>
            </div>
            @endforeach
        </div>
        @else
        <div class="flex flex-col items-center justify-center h-40 text-gray-400">
            <svg class="w-10 h-10 mb-2 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <p class="text-sm">Belum ada transaksi hari ini</p>
        </div>
        @endif
    </div>
</div>

{{-- Bottom Row --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    {{-- Top Products --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Menu Terlaris Hari Ini</h3>
        @forelse($topProducts as $i => $p)
        <div class="flex items-center gap-3 py-2 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                         {{ $i === 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500' }}">
                {{ $i + 1 }}
            </span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">{{ $p['product_name'] }}</p>
                <p class="text-xs text-gray-500">Terjual: {{ number_format($p['total_qty'], 0, ',', '.') }} pcs</p>
            </div>
            <span class="text-sm font-semibold text-emerald-700">
                Rp {{ number_format($p['total_revenue'], 0, ',', '.') }}
            </span>
        </div>
        @empty
        <p class="text-sm text-gray-400 text-center py-8">Belum ada data penjualan</p>
        @endforelse
    </div>

    {{-- Recent Orders --}}
    <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Transaksi Terbaru</h3>
            <a href="{{ route('orders.index') }}" class="text-xs text-emerald-600 hover:underline">Lihat semua</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-5 py-3 font-medium text-gray-500 text-xs uppercase">No. Order</th>
                        <th class="text-left px-5 py-3 font-medium text-gray-500 text-xs uppercase">Kasir</th>
                        <th class="text-left px-5 py-3 font-medium text-gray-500 text-xs uppercase">Status</th>
                        <th class="text-right px-5 py-3 font-medium text-gray-500 text-xs uppercase">Total</th>
                        <th class="text-right px-5 py-3 font-medium text-gray-500 text-xs uppercase">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($recentOrders as $order)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-5 py-3">
                            <a href="{{ route('orders.show', $order['id']) }}" class="font-mono text-xs text-emerald-700 hover:underline">
                                {{ $order['order_number'] }}
                            </a>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $order['user']['name'] ?? '-' }}</td>
                        <td class="px-5 py-3">
                            @php
                            $statusColors = [
                                'paid' => 'green', 'draft' => 'yellow',
                                'cancelled' => 'red', 'refunded' => 'blue'
                            ];
                            $labels = ['paid' => 'Lunas', 'draft' => 'Draft', 'cancelled' => 'Batal', 'refunded' => 'Refund'];
                            @endphp
                            <x-badge :color="$statusColors[$order['status']] ?? 'gray'">
                                {{ $labels[$order['status']] ?? $order['status'] }}
                            </x-badge>
                        </td>
                        <td class="px-5 py-3 text-right font-semibold text-gray-900">
                            Rp {{ number_format($order['grand_total'], 0, ',', '.') }}
                        </td>
                        <td class="px-5 py-3 text-right text-gray-500 text-xs">
                            {{ \Carbon\Carbon::parse($order['created_at'])->diffForHumans() }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-400">Belum ada transaksi</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Low Stock Alert --}}
@if(count($lowStock) > 0)
<div class="mt-4 bg-orange-50 border border-orange-200 rounded-2xl p-5">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div class="flex-1">
            <p class="text-sm font-semibold text-orange-800 mb-2">⚠ Stok Bahan Baku Menipis</p>
            <div class="flex flex-wrap gap-2">
                @foreach($lowStock as $item)
                <span class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs font-medium px-2.5 py-1 rounded-full border border-orange-200">
                    {{ $item['name'] }}
                    <span class="text-orange-500">({{ number_format($item['current_stock'], 1) }} {{ $item['unit'] }})</span>
                </span>
                @endforeach
            </div>
        </div>
        <a href="{{ route('ingredients.index') }}" class="text-xs text-orange-600 font-medium hover:underline flex-shrink-0">Kelola →</a>
    </div>
</div>
@endif

@if(count($outletPerformance) > 1)
<div class="mt-4 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <h3 class="text-sm font-semibold text-gray-900 mb-4">Performa Outlet Hari Ini</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($outletPerformance as $perf)
        <div class="bg-gray-50 rounded-xl p-4">
            <p class="text-sm font-medium text-gray-900">{{ $perf['outlet_name'] }}</p>
            <p class="text-xl font-bold text-emerald-700 mt-1">Rp {{ number_format($perf['revenue'], 0, ',', '.') }}</p>
            <p class="text-xs text-gray-500">{{ $perf['orders'] }} transaksi</p>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
const salesData = @json($salesChart);
const salesCtx = document.getElementById('salesChart');
if (salesCtx) {
    new Chart(salesCtx, {
        data: {
            labels: salesData.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Omzet (Rp)',
                    data: salesData.revenue,
                    backgroundColor: 'rgba(16, 185, 129, 0.25)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Transaksi',
                    data: salesData.orders,
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.08)',
                    borderWidth: 2.5,
                    pointRadius: 5,
                    pointBackgroundColor: 'rgb(99, 102, 241)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    order: 1,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            if (ctx.dataset.label === 'Omzet (Rp)') {
                                return ' Omzet: Rp ' + Intl.NumberFormat('id').format(ctx.raw);
                            }
                            return ' Transaksi: ' + ctx.raw;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    grid: { color: '#f3f4f6' },
                    ticks: {
                        font: { size: 10 },
                        callback: v => {
                            if (v >= 1000000) return 'Rp ' + (v/1000000).toFixed(1) + 'jt';
                            if (v >= 1000)    return 'Rp ' + (v/1000).toFixed(0) + 'rb';
                            return 'Rp ' + v;
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    ticks: {
                        font: { size: 10 },
                        stepSize: 1,
                        callback: v => Number.isInteger(v) ? v + 'x' : '',
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                }
            }
        }
    });
}

@if(count($paymentBreakdown) > 0)
const payCtx = document.getElementById('paymentChart');
if (payCtx) {
    const payData = @json($paymentBreakdown);
    new Chart(payCtx, {
        type: 'doughnut',
        data: {
            labels: payData.map(p => p.payment_method.toUpperCase()),
            datasets: [{
                data: payData.map(p => p.total),
                backgroundColor: ['#10b981','#3b82f6','#8b5cf6','#f59e0b','#ef4444','#6b7280'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            cutout: '65%'
        }
    });
}
@endif
</script>
@endpush
