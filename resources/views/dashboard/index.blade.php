@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@section('content')
@php
    $sub   = $currentBusiness->activeSubscription;
    $user  = auth()->user();
    $role  = $user->role;
    $hour  = (int) now()->format('G');
    $greet = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
    $first = \Illuminate\Support\Str::of($user->name)->explode(' ')->first();

    $rev   = $salesChart['revenue'];
    $ord   = $salesChart['orders'];
    $n     = count($rev);
    $revY  = $n > 1 ? $rev[$n - 2] : 0;
    $ordY  = $n > 1 ? $ord[$n - 2] : 0;
    $delta = fn($now, $prev) => $prev > 0 ? round((($now - $prev) / $prev) * 100) : null;
    $revDelta = $delta($summary['todayRevenue'], $revY);
    $ordDelta = $delta($summary['todayOrders'], $ordY);
    $week     = array_sum($rev);
    $weekOrd  = array_sum($ord);
    $margin   = $summary['todayRevenue'] > 0 ? round($summary['todayProfit'] / $summary['todayRevenue'] * 100) : null;
    $rp       = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
@endphp

{{-- Hero --}}
<div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-900 text-white p-6 sm:p-8 mt-2 mb-6 shadow-xl shadow-slate-200">
    <div class="absolute -top-24 -right-16 w-80 h-80 rounded-full bg-emerald-400/20 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 left-1/3 w-72 h-72 rounded-full bg-teal-400/10 blur-3xl pointer-events-none"></div>

    <div class="relative flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
        <div>
            <p class="text-emerald-300 text-sm font-medium">{{ now()->isoFormat('dddd, D MMMM Y') }}</p>
            <h2 class="text-2xl sm:text-3xl font-extrabold mt-1">{{ $greet }}, {{ $first }}</h2>
            <p class="text-slate-300 mt-1.5 text-sm">
                Ringkasan <span class="text-white font-semibold">{{ $currentBusiness->name }}</span> hari ini.
                @if($summary['todayOrders'] > 0)
                    Sudah <span class="text-white font-semibold">{{ number_format($summary['todayOrders']) }} transaksi</span> masuk.
                @else
                    Belum ada transaksi, semangat membuka hari!
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            @if(in_array($role, ['owner','admin','cashier']))
            <a href="{{ route('pos.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold text-sm transition-colors shadow-lg shadow-emerald-900/30">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Buka Kasir
            </a>
            @endif
            @if(in_array($role, ['owner','admin']))
            <a href="{{ route('reports.sales') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 font-semibold text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Laporan
            </a>
            <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 font-semibold text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                Produk
            </a>
            @endif
        </div>
    </div>

    {{-- Week strip --}}
    <div class="relative grid grid-cols-3 gap-4 mt-7 pt-6 border-t border-white/10">
        <div>
            <p class="text-xs uppercase tracking-wider text-slate-400">Omzet 7 hari</p>
            <p class="text-lg sm:text-xl font-bold mt-1">{{ $rp($week) }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wider text-slate-400">Transaksi 7 hari</p>
            <p class="text-lg sm:text-xl font-bold mt-1">{{ number_format($weekOrd) }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wider text-slate-400">Rata-rata / hari</p>
            <p class="text-lg sm:text-xl font-bold mt-1">{{ $rp($n ? round($week / $n) : 0) }}</p>
        </div>
    </div>
</div>

{{-- Trial banner --}}
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
         class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <div>
                <p class="font-semibold text-sm text-emerald-900">Trial gratis aktif, semua fitur terbuka penuh</p>
                <p class="text-xs text-emerald-700">Sisa waktu: <span class="font-mono font-semibold" x-text="remaining"></span> &middot; berakhir {{ $sub->ends_at->format('d M Y') }}</p>
            </div>
        </div>
        @if($user->isOwner())
        <a href="{{ route('saas.current') }}" class="shrink-0 px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition-colors">Upgrade Sekarang</a>
        @endif
    </div>
@endif

{{-- Outlet filter --}}
@if(count($outlets) > 1)
<div class="mb-5 flex flex-wrap items-center gap-2">
    <span class="text-sm text-gray-500 font-medium mr-1">Outlet:</span>
    <a href="{{ route('dashboard') }}"
       class="px-3.5 py-1.5 rounded-full text-sm font-medium border transition-colors {{ !$outletId ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400' }}">
        Semua Outlet
    </a>
    @foreach($outlets as $outlet)
    <a href="{{ route('dashboard', ['outlet_id' => $outlet->id]) }}"
       class="px-3.5 py-1.5 rounded-full text-sm font-medium border transition-colors {{ $outletId == $outlet->id ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-400' }}">
        {{ $outlet->name }}
    </a>
    @endforeach
</div>
@endif

{{-- KPI cards --}}
@php
    $kpis = [
        ['label' => 'Omzet Hari Ini', 'value' => $rp($summary['todayRevenue']), 'sub' => $summary['todayOrders'] . ' transaksi', 'delta' => $revDelta, 'tone' => 'emerald',
         'path' => 'M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8V6m0 12v-2m0-10v1m0 10v1m9-6a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Transaksi', 'value' => number_format($summary['todayOrders']), 'sub' => 'kemarin ' . number_format($ordY), 'delta' => $ordDelta, 'tone' => 'sky',
         'path' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2M9 5h6m-6 7l2 2 4-4'],
        ['label' => 'Estimasi Profit', 'value' => $rp($summary['todayProfit']), 'sub' => $margin !== null ? 'margin ' . $margin . '%' : 'berdasarkan harga modal', 'delta' => null, 'tone' => 'violet',
         'path' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
        ['label' => 'Rata-rata Order', 'value' => $rp($summary['avgOrder']), 'sub' => 'per transaksi', 'delta' => null, 'tone' => 'amber',
         'path' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
    ];
    $tones = [
        'emerald' => 'from-emerald-500 to-teal-500 shadow-emerald-200',
        'sky'     => 'from-sky-500 to-blue-500 shadow-sky-200',
        'violet'  => 'from-violet-500 to-purple-500 shadow-violet-200',
        'amber'   => 'from-amber-400 to-orange-500 shadow-amber-200',
    ];
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @foreach($kpis as $k)
    <div class="group bg-white rounded-2xl border border-gray-200/80 p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br {{ $tones[$k['tone']] }} text-white flex items-center justify-center shadow-lg">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $k['path'] }}"/></svg>
            </div>
            @if($k['delta'] !== null)
                <span class="inline-flex items-center gap-0.5 text-xs font-semibold rounded-full px-2 py-1 {{ $k['delta'] >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">
                    <svg class="w-3 h-3 {{ $k['delta'] >= 0 ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                    {{ abs($k['delta']) }}%
                </span>
            @endif
        </div>
        <p class="mt-4 text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $k['label'] }}</p>
        <p class="mt-1 text-2xl font-extrabold text-gray-900 truncate tracking-tight">{{ $k['value'] }}</p>
        <p class="mt-1 text-xs text-gray-400">{{ $k['sub'] }}@if($k['delta'] !== null && $k['label'] === 'Omzet Hari Ini') &middot; vs kemarin @endif</p>
    </div>
    @endforeach
</div>

{{-- Charts --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
    <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
                <h3 class="font-bold text-gray-900">Penjualan 7 Hari Terakhir</h3>
                <p class="text-xs text-gray-400 mt-0.5">Omzet harian dan jumlah transaksi</p>
            </div>
            <div class="flex items-center gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500 inline-block"></span>Omzet</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-0.5 bg-indigo-500 inline-block rounded"></span>Transaksi</span>
            </div>
        </div>
        <div style="position:relative; height:280px;">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
        <h3 class="font-bold text-gray-900">Metode Pembayaran</h3>
        <p class="text-xs text-gray-400 mt-0.5 mb-5">Hari ini</p>
        @if(count($paymentBreakdown) > 0)
        @php $payTotal = collect($paymentBreakdown)->sum('total'); $dots = ['bg-emerald-500','bg-blue-500','bg-violet-500','bg-amber-500','bg-red-500','bg-gray-500']; @endphp
        <div class="relative" style="height:170px;">
            <canvas id="paymentChart"></canvas>
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                <span class="text-[11px] text-gray-400 uppercase tracking-wider">Total</span>
                <span class="text-base font-extrabold text-gray-900">{{ $rp($payTotal) }}</span>
            </div>
        </div>
        <div class="mt-5 space-y-3">
            @foreach($paymentBreakdown as $i => $p)
            <div class="flex items-center justify-between text-sm">
                <span class="flex items-center gap-2 text-gray-600 capitalize">
                    <span class="w-2.5 h-2.5 rounded-full {{ $dots[$i % 6] }}"></span>{{ $p['payment_method'] }}
                </span>
                <span class="text-right">
                    <span class="font-semibold text-gray-900">{{ $rp($p['total']) }}</span>
                    <span class="text-xs text-gray-400 ml-1">{{ $p['count'] }}x</span>
                </span>
            </div>
            @endforeach
        </div>
        @else
        <div class="flex flex-col items-center justify-center h-52 text-gray-400">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mb-3">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <p class="text-sm">Belum ada transaksi hari ini</p>
        </div>
        @endif
    </div>
</div>

{{-- Bottom row --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
        <h3 class="font-bold text-gray-900">Menu Terlaris</h3>
        <p class="text-xs text-gray-400 mt-0.5 mb-4">Hari ini</p>
        @php $maxRev = collect($topProducts)->max('total_revenue') ?: 1; @endphp
        @forelse($topProducts as $i => $p)
        <div class="py-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold shrink-0
                             {{ $i === 0 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500' }}">{{ $i + 1 }}</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $p['product_name'] }}</p>
                    <p class="text-xs text-gray-400">{{ number_format($p['total_qty'], 0, ',', '.') }} terjual</p>
                </div>
                <span class="text-sm font-bold text-gray-900">{{ $rp($p['total_revenue']) }}</span>
            </div>
            <div class="mt-2 ml-10 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-teal-500" style="width: {{ max(4, round($p['total_revenue'] / $maxRev * 100)) }}%"></div>
            </div>
        </div>
        @empty
        <div class="flex flex-col items-center justify-center py-12 text-gray-400">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mb-3">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <p class="text-sm">Belum ada data penjualan</p>
        </div>
        @endforelse
    </div>

    <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200/80 shadow-sm overflow-hidden">
        <div class="px-6 py-5 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="font-bold text-gray-900">Transaksi Terbaru</h3>
                <p class="text-xs text-gray-400 mt-0.5">Aktivitas penjualan terakhir</p>
            </div>
            <a href="{{ route('orders.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Lihat semua &rarr;</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/80 border-y border-gray-100">
                        <th class="text-left px-6 py-3 font-semibold text-gray-400 text-[11px] uppercase tracking-wider">No. Order</th>
                        <th class="text-left px-6 py-3 font-semibold text-gray-400 text-[11px] uppercase tracking-wider">Kasir</th>
                        <th class="text-left px-6 py-3 font-semibold text-gray-400 text-[11px] uppercase tracking-wider">Status</th>
                        <th class="text-right px-6 py-3 font-semibold text-gray-400 text-[11px] uppercase tracking-wider">Total</th>
                        <th class="text-right px-6 py-3 font-semibold text-gray-400 text-[11px] uppercase tracking-wider">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $statusColors = ['paid' => 'green', 'draft' => 'yellow', 'cancelled' => 'red', 'refunded' => 'blue'];
                        $labels = ['paid' => 'Lunas', 'draft' => 'Draft', 'cancelled' => 'Batal', 'refunded' => 'Refund'];
                    @endphp
                    @forelse($recentOrders as $order)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-6 py-3.5">
                            <a href="{{ route('orders.show', $order['id']) }}" class="font-mono text-xs font-semibold text-emerald-700 hover:underline">{{ $order['order_number'] }}</a>
                        </td>
                        <td class="px-6 py-3.5">
                            <span class="flex items-center gap-2 text-gray-700">
                                <span class="w-6 h-6 rounded-full bg-gray-100 text-gray-500 text-[11px] font-bold flex items-center justify-center">{{ strtoupper(substr($order['user']['name'] ?? '-', 0, 1)) }}</span>
                                {{ $order['user']['name'] ?? '-' }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5">
                            <x-badge :color="$statusColors[$order['status']] ?? 'gray'">{{ $labels[$order['status']] ?? $order['status'] }}</x-badge>
                        </td>
                        <td class="px-6 py-3.5 text-right font-bold text-gray-900">{{ $rp($order['grand_total']) }}</td>
                        <td class="px-6 py-3.5 text-right text-gray-400 text-xs whitespace-nowrap">{{ \Carbon\Carbon::parse($order['created_at'])->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-14 text-center text-sm text-gray-400">Belum ada transaksi</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Low stock --}}
@if(count($lowStock) > 0)
<div class="mt-4 bg-white border border-amber-200 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-start gap-4">
    <span class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    </span>
    <div class="flex-1">
        <p class="font-bold text-gray-900">Stok bahan baku menipis</p>
        <div class="flex flex-wrap gap-2 mt-2.5">
            @foreach($lowStock as $item)
            <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-800 text-xs font-medium px-2.5 py-1 rounded-full border border-amber-200">
                {{ $item['name'] }}
                <span class="text-amber-600">({{ number_format($item['current_stock'], 1) }} {{ $item['unit'] }})</span>
            </span>
            @endforeach
        </div>
    </div>
    <a href="{{ route('ingredients.index') }}" class="text-sm text-amber-700 font-semibold hover:underline shrink-0">Kelola stok &rarr;</a>
</div>
@endif

{{-- Outlet performance --}}
@if(count($outletPerformance) > 1)
<div class="mt-4 bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
    <h3 class="font-bold text-gray-900 mb-4">Performa Outlet Hari Ini</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($outletPerformance as $perf)
        <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-4">
            <p class="text-sm font-medium text-gray-600">{{ $perf['outlet_name'] }}</p>
            <p class="text-xl font-extrabold text-gray-900 mt-1">{{ $rp($perf['revenue']) }}</p>
            <p class="text-xs text-gray-400">{{ $perf['orders'] }} transaksi</p>
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
    const g = salesCtx.getContext('2d');
    const grad = g.createLinearGradient(0, 0, 0, 280);
    grad.addColorStop(0, 'rgba(16, 185, 129, 0.95)');
    grad.addColorStop(1, 'rgba(20, 184, 166, 0.55)');
    new Chart(salesCtx, {
        data: {
            labels: salesData.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Omzet (Rp)',
                    data: salesData.revenue,
                    backgroundColor: grad,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 38,
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Transaksi',
                    data: salesData.orders,
                    borderColor: 'rgb(99, 102, 241)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: 'rgb(99, 102, 241)',
                    pointBorderWidth: 2,
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
                    backgroundColor: '#0f172a',
                    padding: 12,
                    cornerRadius: 10,
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
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { size: 11 }, color: '#94a3b8',
                        callback: v => {
                            if (v >= 1000000) return (v/1000000).toFixed(1) + ' jt';
                            if (v >= 1000)    return (v/1000).toFixed(0) + ' rb';
                            return v;
                        }
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    border: { display: false },
                    grid: { drawOnChartArea: false },
                    ticks: { font: { size: 11 }, color: '#94a3b8', stepSize: 1, callback: v => Number.isInteger(v) ? v : '' }
                },
                x: {
                    border: { display: false },
                    grid: { display: false },
                    ticks: { font: { size: 11 }, color: '#94a3b8' }
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
                borderWidth: 3,
                borderColor: '#fff',
                hoverOffset: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            cutout: '72%'
        }
    });
}
@endif
</script>
@endpush
