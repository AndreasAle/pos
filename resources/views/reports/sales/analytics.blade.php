@extends('reports.sales.layout')

@section('report')
@php
    $card = 'min-w-0 rounded-xl border border-[#E5E7EB] bg-white';
    $rows = [
        ['gross_item_sales', 'Penjualan kotor item', 'rp'], ['discounts', 'Diskon', 'rp'], ['net_sales', 'Penjualan bersih', 'rp'],
        ['payment_collected', 'Pembayaran tercatat', 'rp'], ['transactions', 'Transaksi', 'num'], ['aov', 'Rata-rata transaksi', 'rp'], ['tax_amount', 'Pajak', 'rp'],
    ];
    $fmt = fn ($v, $t) => $t === 'rp' ? \App\Support\Money::rp($v) : \App\Support\Money::num($v);
@endphp

@if($summary['metrics']['transactions'] === 0 && (! $compare || $compare['prior']['transactions'] === 0))
<div class="{{ $card }} px-6 py-14 text-center">
    <p class="text-base font-semibold text-[#0B1B3F]">Tidak ada transaksi untuk filter ini</p>
    <p class="mt-1 text-sm text-[#64748B]">Ubah periode atau outlet, atau reset filter.</p>
    <a href="{{ route('reports.sales.analytics') }}" class="mt-4 inline-flex h-10 items-center rounded-lg border border-[#E5E7EB] px-4 text-sm font-semibold text-[#334155] hover:border-[#16A34A] hover:text-[#15803D]">Reset filter</a>
</div>
@else

@if(! $compare)
<div class="{{ $card }} mb-4 px-4 py-3 text-sm text-[#475569]">Pilih mode <strong>Bandingkan</strong> di atas untuk membandingkan dengan periode sebelumnya, rentang tanggal lain, atau outlet lain. Tanpa pembanding, tabel ini hanya menampilkan periode terpilih.</div>
@endif

@if($compare && $compare['partial_note'])
<div role="status" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900">{{ $compare['partial_note'] }}</div>
@endif

<section class="{{ $card }} overflow-hidden" aria-labelledby="cmp-h">
    <div class="border-b border-[#E5E7EB] px-4 py-3">
        <h2 id="cmp-h" class="text-base font-semibold text-[#0B1B3F]">Ringkasan{{ $compare ? ' dan perbandingan' : '' }}</h2>
        @if($compare)<p class="text-xs text-[#64748B]">Pembanding: {{ $compare['prior_label'] }}. Perubahan = (sekarang − pembanding) ÷ |pembanding|; N/A bila pembanding 0.</p>@endif
    </div>
    <div class="overflow-x-auto">
    <table class="w-full min-w-[560px] text-sm">
        <thead class="bg-gray-50 text-left text-xs font-semibold text-[#64748B]">
            <tr><th scope="col" class="px-4 py-2.5">Metrik</th><th scope="col" class="px-4 py-2.5 text-right">{{ $compare ? 'Sekarang' : 'Nilai' }}</th>
            @if($compare)<th scope="col" class="px-4 py-2.5 text-right">Pembanding</th><th scope="col" class="px-4 py-2.5 text-right">Selisih</th><th scope="col" class="px-4 py-2.5 text-right">Perubahan</th>@endif</tr>
        </thead>
        <tbody class="divide-y divide-[#E5E7EB]">
        @foreach($rows as [$k, $label, $t])
            @php $d = $compare['deltas'][$k] ?? null; $m = $summary['metrics'][$k]; @endphp
            <tr title="{{ $definitions[$k]['definition'] ?? '' }}">
                <th scope="row" class="px-4 py-2.5 text-left font-medium text-[#0B1B3F]">{{ $label }}</th>
                <td class="px-4 py-2.5 text-right tabular-nums">{{ $fmt($m, $t) }}</td>
                @if($compare)
                <td class="px-4 py-2.5 text-right tabular-nums text-[#475569]">{{ $fmt($d['prior'], $t) }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums">{{ $d['abs'] === null ? 'N/A' : ($d['abs'] > 0 ? '+' : '').$fmt($d['abs'], $t) }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums font-semibold {{ $d['pct'] === null ? 'text-[#94A3B8]' : ($d['pct'] >= 0 ? 'text-emerald-700' : 'text-red-600') }}">{{ \App\Support\Money::pct($d['pct']) }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</section>

@if(count($trend['points']))
<section class="{{ $card }} mt-4 p-4" aria-labelledby="atrend-h">
    <h2 id="atrend-h" class="text-base font-semibold text-[#0B1B3F]">Tren penjualan bersih{{ $compare ? ' (garis putus-putus = pembanding)' : '' }}</h2>
    <div class="relative mt-2 h-72"><canvas id="cmpChart" role="img" aria-label="Tren penjualan bersih dibanding pembanding"></canvas></div>
</section>
@endif

@include('reports.sales._evidence', ['evidence' => $summary['evidence'], 'notes' => $summary['notes']])

@push('scripts')
<script>
(function () {
    const el = document.getElementById('cmpChart'); if (!el || !window.Chart) return;
    const cur = @json($trend['points']);
    const prior = @json($compare['prior_trend']['points'] ?? []);
    const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
    const ds = [{ label: 'Periode ini', data: cur.map(p => p.net_sales), borderColor: '#16A34A', backgroundColor: '#16A34A', tension: .25, pointRadius: 2 }];
    if (prior.length) ds.push({ label: 'Pembanding', data: prior.map(p => p.net_sales), borderColor: '#8A9AA1', backgroundColor: '#8A9AA1', borderDash: [5, 4], tension: .25, pointRadius: 2 });
    new Chart(el, { type: 'line', data: { labels: cur.map(p => p.label), datasets: ds }, options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + rp(c.parsed.y) } } },
        scales: { y: { beginAtZero: true, grid: { color: '#EEF1F0' } }, x: { grid: { display: false } } } } });
})();
</script>
@endpush
@endif
@endsection
