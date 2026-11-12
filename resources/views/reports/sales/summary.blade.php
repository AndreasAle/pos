@extends('reports.sales.layout')

@section('report')
@php
    $m = $summary['metrics'];
    $empty = $m['transactions'] === 0;
    $drillKey = $f->grouping === 'hour' ? 'hour' : 'day';
    $hasHours = count($hours['points']) > 0;
    $maxPay = collect($summary['payments'])->max('amount') ?: 1;
    $card = 'rounded-lg border border-gray-200 bg-white';
@endphp

@if($empty)
<div class="{{ $card }} px-6 py-14 text-center">
    <p class="text-base font-semibold text-gray-900">Belum ada transaksi pada filter ini</p>
    <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">
        Periode {{ $f->start->isoFormat('D MMM Y') }} – {{ $f->end->subSecond()->isoFormat('D MMM Y') }}
        @if(count($f->outletIds) !== count($f->allowedOutletIds)) untuk outlet terpilih @endif.
        Grafik tidak ditampilkan karena tidak ada data.
    </p>
    <div class="mt-5 flex justify-center gap-2">
        <a href="{{ route('reports.sales') }}" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-700 hover:border-[#0C7856]">Reset filter</a>
        <a href="{{ route('pos.index') }}" class="inline-flex h-10 items-center rounded-lg bg-[#0C7856] px-4 text-sm font-semibold text-white hover:bg-[#0a6a4c]">Buka Kasir</a>
    </div>
</div>
@else

{{-- KPI strip --}}
<div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
    <div class="{{ $card }} p-4" title="{{ $definitions['gross_item_sales']['definition'] }}">
        <p class="text-xs font-semibold text-gray-500">Penjualan kotor item</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-[#102C35]">{{ \App\Support\Money::rp($m['gross_item_sales']) }}</p>
        <p class="mt-1 text-xs text-gray-500">Sebelum diskon</p>
    </div>
    <div class="{{ $card }} border-[#0C7856]/40 p-4" title="{{ $definitions['net_sales']['definition'] }}">
        <p class="text-xs font-semibold text-[#0C7856]">Penjualan bersih</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-[#102C35]">{{ \App\Support\Money::rp($m['net_sales']) }}</p>
        <p class="mt-1 text-xs text-gray-500">Diskon {{ \App\Support\Money::rp($m['discounts']) }}</p>
    </div>
    <div class="{{ $card }} p-4" title="{{ $definitions['payment_collected']['definition'] }}">
        <p class="text-xs font-semibold text-gray-500">Pembayaran tercatat</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-[#102C35]">{{ \App\Support\Money::rp($m['payment_collected']) }}</p>
        <p class="mt-1 text-xs text-gray-500">Termasuk pajak {{ \App\Support\Money::rp($m['tax_amount']) }}</p>
    </div>
    <a href="{{ route('reports.sales.orders', $queryString) }}" class="{{ $card }} block p-4 hover:border-[#0C7856] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0C7856]" title="Buka Riwayat Order">
        <p class="text-xs font-semibold text-gray-500">Transaksi</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-[#102C35]">{{ \App\Support\Money::num($m['transactions']) }}</p>
        <p class="mt-1 text-xs text-gray-500">Lihat daftar order →</p>
    </a>
    <div class="{{ $card }} p-4" title="{{ $definitions['aov']['definition'] }}">
        <p class="text-xs font-semibold text-gray-500">Rata-rata transaksi</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-[#102C35]">{{ \App\Support\Money::rp($m['aov']) }}</p>
        <p class="mt-1 text-xs text-gray-500">Penjualan bersih / transaksi</p>
    </div>
</div>

{{-- Warnings, shown above the charts so they cannot be missed --}}
@if($summary['warnings'])
<div role="status" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900">
    <p class="font-semibold">Perhatikan cakupan data</p>
    <ul class="mt-1 list-disc space-y-0.5 pl-5">@foreach($summary['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
</div>
@endif

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <section class="{{ $card }} p-4 xl:col-span-2" aria-labelledby="trend-h">
        <div class="mb-2 flex items-center justify-between">
            <h2 id="trend-h" class="text-sm font-semibold text-gray-900">Penjualan bersih per {{ ['day' => 'hari', 'weekday' => 'hari dalam minggu', 'hour' => 'jam', 'week' => 'minggu', 'month' => 'bulan'][$f->grouping] }}</h2>
            <span class="text-xs text-gray-500">{{ in_array($f->grouping, ['day','hour']) ? 'Klik batang untuk melihat order' : '' }}</span>
        </div>
        @if(count($trend['points']))
        <div class="relative h-72"><canvas id="trendChart" role="img" aria-label="Grafik penjualan bersih per {{ $f->grouping }}"></canvas></div>
        @else
        <p class="py-16 text-center text-sm text-gray-400">Tidak ada data untuk grafik.</p>
        @endif
    </section>

    <section class="{{ $card }} p-4" aria-labelledby="pay-h">
        <h2 id="pay-h" class="text-sm font-semibold text-gray-900">Metode pembayaran</h2>
        <p class="text-xs text-gray-500">Pembayaran tercatat di POS. Order split dirinci per baris pembayaran.</p>
        <ul class="mt-3 space-y-3">
            @foreach($summary['payments'] as $p)
            <li>
                <a href="{{ route('reports.sales.orders', array_merge($queryString, ['payment_methods' => [$p['method']]])) }}" class="block rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0C7856]">
                    <div class="flex items-baseline justify-between text-sm"><span class="font-medium">{{ \App\Support\Money::method($p['method']) }}</span><span class="tabular-nums">{{ \App\Support\Money::rp($p['amount']) }}</span></div>
                    <div class="mt-1 h-2 rounded bg-gray-100" role="presentation"><div class="h-2 rounded bg-[#0C7856]" style="width: {{ round($p['amount'] / $maxPay * 100) }}%"></div></div>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $p['orders'] }} order · {{ \App\Support\Money::num($p['share_pct'], 1) }}%</p>
                </a>
            </li>
            @endforeach
        </ul>
    </section>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <section class="{{ $card }} p-4 xl:col-span-2" aria-labelledby="hour-h">
        <h2 id="hour-h" class="text-sm font-semibold text-gray-900">Penjualan per jam (waktu {{ $f->timezone }})</h2>
        @if($hasHours)
        <div class="relative mt-2 h-60"><canvas id="hourChart" role="img" aria-label="Grafik penjualan bersih per jam"></canvas></div>
        @else
        <p class="py-12 text-center text-sm text-gray-400">Tidak ada data.</p>
        @endif
    </section>

    <section class="{{ $card }} p-4" aria-labelledby="cat-h">
        <h2 id="cat-h" class="text-sm font-semibold text-gray-900">Kategori teratas</h2>
        <p class="text-xs text-gray-500">Diskon order dialokasikan proporsional ke item.</p>
        <table class="mt-2 w-full text-sm">
            <caption class="sr-only">Kategori dengan penjualan bersih tertinggi</caption>
            <tbody class="divide-y divide-gray-100">
            @forelse($categories as $c)
                <tr><td class="py-2 pr-2">{{ $c['name'] }}</td><td class="py-2 text-right tabular-nums">{{ \App\Support\Money::rp($c['net_sales']) }}</td></tr>
            @empty
                <tr><td class="py-6 text-center text-gray-400">Tidak ada data</td></tr>
            @endforelse
            </tbody>
        </table>
        <a href="{{ route('reports.sales.breakdown', $queryString) }}" class="mt-2 inline-block text-xs font-semibold text-[#0C7856] hover:underline">Breakdown lengkap →</a>
    </section>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <section class="{{ $card }} overflow-hidden xl:col-span-2" aria-labelledby="recent-h">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <h2 id="recent-h" class="text-sm font-semibold text-gray-900">Order terbaru</h2>
            <a href="{{ route('reports.sales.orders', $queryString) }}" class="text-xs font-semibold text-[#0C7856] hover:underline">Semua order →</a>
        </div>
        @include('reports.sales._orders_table', ['orders' => $recent, 'compact' => true])
    </section>

    <section class="{{ $card }} p-4" aria-labelledby="hpp-h">
        <h2 id="hpp-h" class="text-sm font-semibold text-gray-900">Estimasi laba kotor</h2>
        <p class="mt-2 text-2xl font-bold tabular-nums text-[#102C35]">{{ \App\Support\Money::rp($m['gross_profit_estimate']) }}</p>
        <p class="mt-1 text-xs text-gray-500">
            @if($m['cost_coverage_pct'] === null) HPP tidak tersedia pada transaksi ini.
            @else Cakupan HPP {{ \App\Support\Money::num($m['cost_coverage_pct'], 1) }}% dari nilai item. Bukan laba bersih; biaya operasional tidak termasuk.
            @endif
        </p>
        <dl class="mt-4 space-y-1.5 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Order refund</dt><dd class="tabular-nums">{{ $summary['excluded']['refunded']['orders'] }} · {{ \App\Support\Money::rp($summary['excluded']['refunded']['amount']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Order batal</dt><dd class="tabular-nums">{{ $summary['excluded']['cancelled']['orders'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Service + ongkir</dt><dd class="tabular-nums">{{ \App\Support\Money::rp($m['service_amount'] + $m['delivery_fees']) }}</dd></div>
        </dl>
        <a href="{{ route('reports.sales.products', $queryString) }}" class="mt-3 inline-block text-xs font-semibold text-[#0C7856] hover:underline">Product Mix →</a>
    </section>
</div>

@include('reports.sales._evidence', ['evidence' => $summary['evidence'], 'notes' => $summary['notes']])

@push('scripts')
<script>
(function () {
    if (!window.Chart) return;
    const trend = @json($trend['points']);
    const hours = @json($hours['points']);
    const drillKey = @json($drillKey);
    const base = @json(route('reports.sales.orders'));
    const qs = @json($queryString);
    const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
    const grid = { color: '#EEF1F0' };
    const bar = (id, pts, onClick) => {
        const el = document.getElementById(id); if (!el || !pts.length) return;
        new Chart(el, {
            type: 'bar',
            data: { labels: pts.map(p => p.label), datasets: [{ label: 'Penjualan bersih', data: pts.map(p => p.net_sales), backgroundColor: '#0C7856', borderRadius: 3, maxBarThickness: 36 }] },
            options: {
                maintainAspectRatio: false, onClick: onClick,
                onHover: (e, a) => { e.native.target.style.cursor = onClick && a.length ? 'pointer' : 'default'; },
                plugins: { legend: { display: false }, tooltip: { callbacks: { title: i => i[0].label, label: c => rp(c.parsed.y) + ' · ' + pts[c.dataIndex].transactions + ' transaksi' } } },
                scales: { y: { beginAtZero: true, grid: grid, ticks: { callback: v => v >= 1e6 ? (v / 1e6) + ' jt' : v >= 1e3 ? (v / 1e3) + ' rb' : v } }, x: { grid: { display: false } } }
            }
        });
    };
    const params = extra => base + '?' + new URLSearchParams(Object.assign({}, flat(qs), extra)).toString();
    function flat(o) { const r = {}; Object.entries(o).forEach(([k, v]) => { if (!Array.isArray(v)) r[k] = v; }); return r; }
    bar('trendChart', trend, ['day', 'hour'].includes(drillKey) && ['day', 'hour'].includes(@json($f->grouping)) ? (e, a, chart) => {
        if (!a.length) return; const p = trend[a[0].index]; window.location = params(@json($f->grouping) === 'hour' ? { hour: p.key } : { day: p.key });
    } : null);
    bar('hourChart', hours, (e, a) => { if (!a.length) return; window.location = params({ hour: hours[a[0].index].key }); });
})();
</script>
@endpush
@endif
@endsection
