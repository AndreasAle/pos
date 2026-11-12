@extends('layouts.app')
@section('title','Estimasi Profit')
@section('page-title','Estimasi Profit')
@section('page-subtitle','Omzet dikurangi harga pokok penjualan (HPP) per hari, untuk melihat margin kotor dengan cepat.')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@section('content')
@php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp
<div class="y-report">
@include('reports._nav')
@include('reports._filter', ['outlets' => $outlets, 'f' => $f, 'exports' => [['label' => 'PDF', 'url' => route('reports.profit.pdf', request()->query())]]])

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Omzet" :value="$rp($totalRevenue)" sub="Penjualan lunas" tone="brand"/>
    <x-kpi label="HPP" :value="$rp($totalCogs)" sub="Harga pokok barang terjual"/>
    <x-kpi label="Estimasi profit" :value="$rp($totalProfit)" sub="Omzet dikurangi HPP" :tone="$totalProfit < 0 ? 'danger' : 'default'"/>
    <x-kpi label="Margin kotor" :value="number_format($margin, 1, ',', '.').'%'" sub="Profit / omzet"/>
</div>

<div class="mt-4 flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <p>Profit dihitung dari <b>harga modal</b> yang diisi pada produk, sehingga bukan laba bersih. Untuk akurasi lebih baik, isi resep dan bahan baku. Biaya operasional bisa dilihat di <a href="{{ route('reports.profit-loss') }}" class="font-semibold underline">Laporan Laba Rugi</a>.</p>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
    <x-panel title="Omzet, HPP, dan profit harian" sub="Titik ungu menandai profit; merah bila rugi.">
        @if(count($rows))
        <div class="relative h-80 w-full"><canvas id="profitChart"></canvas></div>
        @else
        <x-empty title="Belum ada data" text="Grafik muncul setelah ada penjualan pada periode ini." icon="M3 17l6-6 4 4 8-8M14 7h7v7"/>
        @endif
    </x-panel>

    <x-panel flush title="Rincian harian" :sub="count($rows).' hari'">
        @if(count($rows))
        <div class="max-h-[420px] overflow-auto">
            <table class="w-full min-w-[460px] text-sm">
                <thead class="sticky top-0"><tr><th class="px-5 py-3 text-left">Tanggal</th><th class="px-3 py-3 text-right">Omzet</th><th class="px-3 py-3 text-right">HPP</th><th class="px-5 py-3 text-right">Profit</th></tr></thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                @foreach($rows as $row)
                    @php $profit = $row->revenue - $row->cogs; @endphp
                    <tr>
                        <td class="px-5 py-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($row->date)->locale('id')->isoFormat('ddd, D MMM Y') }}</td>
                        <td class="px-3 py-3 text-right font-semibold tabular-nums">{{ $rp($row->revenue) }}</td>
                        <td class="px-3 py-3 text-right tabular-nums text-[#475569]">{{ $rp($row->cogs) }}</td>
                        <td class="px-5 py-3 text-right font-bold tabular-nums {{ $profit >= 0 ? 'text-[#16A34A]' : 'text-red-600' }}">{{ $rp($profit) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="sticky bottom-0"><tr class="border-t-2 border-[#16A34A]/30 bg-[#ECFDF3] font-bold"><td class="px-5 py-3">Total</td><td class="px-3 py-3 text-right tabular-nums">{{ $rp($totalRevenue) }}</td><td class="px-3 py-3 text-right tabular-nums">{{ $rp($totalCogs) }}</td><td class="px-5 py-3 text-right tabular-nums">{{ $rp($totalProfit) }}</td></tr></tfoot>
            </table>
        </div>
        @else
            <x-empty title="Belum ada data" text="Ubah rentang tanggal atau outlet untuk melihat rincian harian."/>
        @endif
    </x-panel>
</div>

<script type="application/json" id="profit-rows">{!! json_encode($rows) !!}</script>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('profitChart'), raw = document.getElementById('profit-rows');
    if (!el || !raw || !window.Chart) return;
    const rows = JSON.parse(raw.textContent);
    if (!rows.length) return;
    const labels = rows.map(r => new Date(r.date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' }));
    const revenue = rows.map(r => parseFloat(r.revenue) || 0), cogs = rows.map(r => parseFloat(r.cogs) || 0), profit = rows.map((r, i) => revenue[i] - cogs[i]);
    new Chart(el, {
        data: { labels, datasets: [
            { type: 'bar', label: 'Omzet', data: revenue, backgroundColor: 'rgba(22,163,74,.55)', borderRadius: 4, order: 2 },
            { type: 'bar', label: 'HPP', data: cogs, backgroundColor: 'rgba(245,158,11,.5)', borderRadius: 4, order: 3 },
            { type: 'line', label: 'Profit', data: profit, borderColor: '#6366F1', borderWidth: 2.5, tension: .35, fill: false, order: 1,
              pointRadius: 4, pointBackgroundColor: profit.map(v => v >= 0 ? '#6366F1' : '#EF4444'), pointBorderColor: '#fff', pointBorderWidth: 2 }
        ] },
        options: {
            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', labels: { font: { size: 12 }, usePointStyle: true, pointStyle: 'rectRounded' } },
                tooltip: { backgroundColor: '#0B1B3F', padding: 10, cornerRadius: 8, callbacks: { label: c => '  ' + c.dataset.label + ': ' + (c.raw < 0 ? '-' : '') + 'Rp ' + Intl.NumberFormat('id').format(Math.abs(c.raw)) } } },
            scales: { y: { beginAtZero: true, grid: { color: '#EEF2F6' }, ticks: { font: { size: 12 }, color: '#64748B', maxTicksLimit: 6, callback: v => v >= 1e6 ? 'Rp ' + (v / 1e6).toFixed(1) + ' jt' : v >= 1e3 ? 'Rp ' + (v / 1e3).toFixed(0) + ' rb' : (v === 0 ? '0' : 'Rp ' + v) } },
                x: { grid: { display: false }, ticks: { font: { size: 12 }, color: '#64748B', maxRotation: 0, autoSkip: true } } }
        }
    });
});
</script>
@endpush
