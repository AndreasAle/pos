@extends('reports.sales.layout')

@section('report')
@php
    $card = 'min-w-0 rounded-xl border border-[#E5E7EB] bg-white';
    $dims = \App\Services\Reporting\SalesMetricsService::DIMENSIONS;
    $metrics = \App\Services\Reporting\SalesMetricsService::METRICS;
    $isMoney = in_array($metric, ['net_sales', 'gross_item_sales', 'payment_collected']);
    $fmt = fn ($v) => $isMoney ? \App\Support\Money::rp($v) : \App\Support\Money::num($v, $metric === 'quantity' ? 1 : 0);
@endphp

<form method="GET" action="{{ url()->current() }}" class="{{ $card }} mb-4 flex flex-wrap items-end gap-3 p-3">
    @foreach($queryString as $k => $v)
        @foreach((array) $v as $vv)<input type="hidden" name="{{ $k }}{{ is_array($v) ? '[]' : '' }}" value="{{ $vv }}">@endforeach
    @endforeach
    @foreach([['row', 'Baris', $row], ['col', 'Kolom', $col]] as [$name, $label, $val])
    <label class="block"><span class="mb-1 block text-xs font-semibold text-[#64748B]">{{ $label }}</span>
        <select name="{{ $name }}" class="h-10 rounded-lg text-sm">
            @foreach($dims as $k => $l)<option value="{{ $k }}" @selected($val === $k)>{{ $l }}</option>@endforeach
        </select></label>
    @endforeach
    <label class="block"><span class="mb-1 block text-xs font-semibold text-[#64748B]">Metrik</span>
        <select name="metric" class="h-10 rounded-lg text-sm">
            @foreach($metrics as $k => $l)<option value="{{ $k }}" @selected($metric === $k)>{{ $l }}</option>@endforeach
        </select></label>
    <button class="h-10 rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white hover:bg-[#15803D]">Tampilkan matriks</button>
    <p class="basis-full text-xs text-[#64748B]">Dua dimensi dipilih per metrik. Kombinasi yang tidak dapat diatribusikan dari data (mis. metode bayar × produk) ditolak, bukan ditebak.</p>
</form>

@if($matrixError)
<div role="alert" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $matrixError }}</div>
@elseif($matrix && count($matrix['rows']) === 0)
<div class="{{ $card }} px-6 py-12 text-center">
    <p class="text-base font-semibold text-[#0B1B3F]">Tidak ada transaksi untuk filter ini</p>
    <a href="{{ route('reports.sales.breakdown') }}" class="mt-3 inline-flex h-10 items-center rounded-lg border border-[#E5E7EB] px-4 text-sm font-semibold text-[#334155] hover:border-[#16A34A] hover:text-[#15803D]">Reset filter</a>
</div>
@elseif($matrix)
<section class="{{ $card }} overflow-hidden" aria-labelledby="bd-h">
    <div class="border-b border-[#E5E7EB] px-4 py-3">
        <h2 id="bd-h" class="text-base font-semibold text-[#0B1B3F]">{{ $metrics[$metric] }} · {{ $dims[$row] }} × {{ $dims[$col] }}</h2>
        @if($metric === 'net_sales' && $matrix['source'] === 'items')<p class="text-xs text-[#64748B]">Diskon order dialokasikan proporsional ke item.</p>@endif
        @if($matrix['truncated'])<p class="mt-1 text-xs text-amber-700">Menampilkan 50 baris dan 12 kolom teratas; sisanya tidak ditampilkan.</p>@endif
    </div>
    <div class="overflow-x-auto">
    <table class="w-full min-w-[640px] text-sm">
        <thead class="text-xs">
            <tr>
                <th scope="col" class="px-4 py-2.5 text-left">{{ $dims[$row] }}</th>
                @foreach($matrix['columns'] as $c)<th scope="col" class="px-4 py-2.5 text-right">{{ $c['label'] }}</th>@endforeach
                <th scope="col" class="px-4 py-2.5 text-right">Total</th><th scope="col" class="px-4 py-2.5 text-right">% total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[#E5E7EB]">
        @foreach($matrix['rows'] as $r)
            <tr class="hover:bg-gray-50">
                <th scope="row" class="px-4 py-2.5 text-left font-medium">{{ $r['label'] }}</th>
                @foreach($matrix['columns'] as $c)
                    <td class="px-4 py-2.5 text-right tabular-nums {{ isset($r['cells'][$c['key']]) ? '' : 'text-gray-300' }}">{{ isset($r['cells'][$c['key']]) ? $fmt($r['cells'][$c['key']]) : '–' }}</td>
                @endforeach
                <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ $fmt($r['total']) }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums text-[#64748B]">{{ \App\Support\Money::num($r['pct'], 1) }}%</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot class="border-t-2 border-[#16A34A]/30 bg-emerald-50/50 font-semibold">
            <tr>
                <th scope="row" class="px-4 py-2.5 text-left">Total</th>
                @foreach($matrix['columns'] as $c)<td class="px-4 py-2.5 text-right tabular-nums">{{ $fmt($c['total']) }}</td>@endforeach
                <td class="px-4 py-2.5 text-right tabular-nums">{{ $fmt($matrix['grand_total']) }}</td><td></td>
            </tr>
        </tfoot>
    </table>
    </div>
</section>
@include('reports.sales._evidence', ['evidence' => $matrix['evidence']])
@endif
@endsection
