@extends('layouts.app')
@section('title', 'Laba Rugi')
@section('page-title', 'Laporan Laba Rugi')
@section('page-subtitle', 'Pendapatan, harga pokok, dan beban usaha dalam format laba rugi bertingkat, bisa dibandingkan dengan periode sebelumnya.')

@section('content')
@php
    $card = 'min-w-0 rounded-xl border border-[#E5E7EB] bg-white';
    $rp = fn ($v) => \App\Support\Money::rp($v);
    $pc = fn ($v) => $v === null ? 'N/A' : \App\Support\Money::num($v, 1).'%';
    // One statement line: label, current value, optional prior value; negative = deduction.
    $line = fn ($label, $cur, $pri = null, $bold = false, $indent = 0) => compact('label', 'cur', 'pri', 'bold', 'indent');
    $lines = [
        $line('Penjualan kotor item', $r['revenue']['gross_item_sales'], $prior['revenue']['gross_item_sales'] ?? null),
        $line('Diskon', -$r['revenue']['discounts'], isset($prior) ? -$prior['revenue']['discounts'] : null, false, 1),
        $line('Penjualan bersih', $r['revenue']['net_sales'], $prior['revenue']['net_sales'] ?? null, true),
        $line('Harga pokok penjualan (HPP)', -$r['cogs'], isset($prior) ? -$prior['cogs'] : null, false, 1),
        $line('LABA KOTOR', $r['gross_profit'], $prior['gross_profit'] ?? null, true),
    ];
    $opexRows = $r['opex'];
@endphp
<div class="y-report text-[14px] text-[#0B1B3F]">
@include('reports.fin._period', ['compare' => true, 'exports' => ['Excel' => 'xlsx', 'PDF' => 'pdf']])

@foreach($r['warnings'] as $w)<div role="status" class="mb-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900">{{ $w }}</div>@endforeach

<div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="{{ $card }} p-5"><p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Penjualan bersih</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ $rp($r['revenue']['net_sales']) }}</p></div>
    <div class="{{ $card }} p-5"><p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Laba kotor</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ $rp($r['gross_profit']) }}</p><p class="mt-1 text-xs text-[#64748B]">Margin {{ $pc($r['gross_margin_pct']) }}</p></div>
    <div class="{{ $card }} p-5"><p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Laba operasional</p><p class="mt-1 text-2xl font-bold tabular-nums {{ $r['operating_profit'] < 0 ? 'text-red-600' : '' }}">{{ $rp($r['operating_profit']) }}</p><p class="mt-1 text-xs text-[#64748B]">Margin {{ $pc($r['operating_margin_pct']) }}</p></div>
    <div class="{{ $card }} border-[#16A34A]/40 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-[#16A34A]">Laba sebelum pajak penghasilan</p><p class="mt-1 text-2xl font-bold tabular-nums {{ $r['profit_before_tax'] < 0 ? 'text-red-600' : '' }}">{{ $rp($r['profit_before_tax']) }}</p><p class="mt-1 text-xs text-[#64748B]">Margin {{ $pc($r['net_margin_pct']) }}</p></div>
</div>

<section class="{{ $card }} mt-4 overflow-hidden" aria-labelledby="pl">
    <div class="border-b border-[#E5E7EB] px-4 py-3"><h2 id="pl" class="text-base font-semibold text-[#0B1B3F]">Laporan Laba Rugi</h2><p class="text-xs text-[#64748B]">Basis kas untuk beban (tanggal biaya). Dalam Rupiah. Pengurang ditulis negatif.</p></div>
    <div class="overflow-x-auto"><table class="w-full min-w-[520px] text-sm">
        <thead class="text-xs"><tr><th scope="col" class="px-4 py-2.5 text-left">Pos</th><th scope="col" class="px-4 py-2.5 text-right">{{ isset($prior) ? 'Periode ini' : 'Jumlah' }}</th>@isset($prior)<th scope="col" class="px-4 py-2.5 text-right">Pembanding</th><th scope="col" class="px-4 py-2.5 text-right">Perubahan</th>@endisset</tr></thead>
        <tbody class="divide-y divide-[#E5E7EB]">
        @php
            $emit = function ($l) use ($rp, $prior) {
                $chg = isset($prior) && $l['pri'] !== null && abs($l['pri']) > 0 ? \App\Support\Money::pct(round(($l['cur'] - $l['pri']) / abs($l['pri']) * 100, 1)) : (isset($prior) ? 'N/A' : null);
                return '<tr class="'.($l['bold'] ? 'bg-gray-50 font-semibold' : '').'"><td class="px-4 py-2.5" style="padding-left:'.(1 + $l['indent'] * 1.25).'rem">'.e($l['label']).'</td><td class="px-4 py-2.5 text-right tabular-nums">'.$rp($l['cur']).'</td>'.(isset($prior) ? '<td class="px-4 py-2.5 text-right tabular-nums text-[#475569]">'.$rp($l['pri']).'</td><td class="px-4 py-2.5 text-right tabular-nums">'.$chg.'</td>' : '').'</tr>';
            };
        @endphp
        @foreach($lines as $l){!! $emit($l) !!}@endforeach
        <tr><td colspan="{{ isset($prior) ? 4 : 2 }}" class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wide text-[#64748B]">Beban operasional</td></tr>
        @forelse($opexRows as $i => $o){!! $emit($line($o['code'].'  '.$o['name'], -$o['amount'], isset($prior) ? -($prior['opex'][$i]['amount'] ?? 0) : null, false, 1)) !!}
        @empty<tr><td colspan="{{ isset($prior) ? 4 : 2 }}" class="px-4 py-3 pl-8 text-[#94A3B8]">Belum ada biaya operasional tercatat. <a class="font-semibold text-[#16A34A] hover:underline" href="{{ route('expenses.create') }}">Catat biaya</a></td></tr>@endforelse
        {!! $emit($line('Total beban operasional', -$r['opex_total'], isset($prior) ? -$prior['opex_total'] : null, true)) !!}
        {!! $emit($line('LABA OPERASIONAL', $r['operating_profit'], $prior['operating_profit'] ?? null, true)) !!}
        @foreach($r['other_expenses'] as $o){!! $emit($line($o['code'].'  '.$o['name'], -$o['amount'], null, false, 1)) !!}@endforeach
        {!! $emit($line('Beban lain-lain', -$r['other_total'], isset($prior) ? -$prior['other_total'] : null)) !!}
        {!! $emit($line('LABA SEBELUM PAJAK PENGHASILAN', $r['profit_before_tax'], $prior['profit_before_tax'] ?? null, true)) !!}
        </tbody>
    </table></div>
</section>

<section class="{{ $card }} mt-4 p-4" aria-labelledby="memo"><h2 id="memo" class="text-base font-semibold text-[#0B1B3F]">Catatan di luar laba rugi (memo)</h2>
    <dl class="mt-2 grid grid-cols-1 gap-x-8 gap-y-1.5 text-sm sm:grid-cols-2">
        <div class="flex justify-between"><dt class="text-[#64748B]">Pajak dipungut (liabilitas, bukan pendapatan)</dt><dd class="tabular-nums">{{ $rp($r['memo']['tax_collected']) }}</dd></div>
        <div class="flex justify-between"><dt class="text-[#64748B]">Setoran pajak tercatat</dt><dd class="tabular-nums">{{ $rp($r['memo']['tax_remitted']) }}</dd></div>
        <div class="flex justify-between"><dt class="text-[#64748B]">Service charge dipungut</dt><dd class="tabular-nums">{{ $rp($r['memo']['service_amount']) }}</dd></div>
        <div class="flex justify-between"><dt class="text-[#64748B]">Ongkir dipungut</dt><dd class="tabular-nums">{{ $rp($r['memo']['delivery_fees']) }}</dd></div>
        <div class="flex justify-between"><dt class="text-[#64748B]">Pembelian diterima (persediaan, bukan beban)</dt><dd class="tabular-nums">{{ $rp($r['memo']['purchases_received']) }}</dd></div>
        <div class="flex justify-between"><dt class="text-[#64748B]">Transaksi</dt><dd class="tabular-nums">{{ $r['memo']['transactions'] }}</dd></div>
    </dl>
</section>
@include('reports.sales._evidence', ['evidence' => $r['evidence'], 'notes' => ['HPP = jumlah (qty × harga pokok snapshot) item terjual'.($r['cogs_coverage_pct'] !== null ? ', cakupan '.\App\Support\Money::num($r['cogs_coverage_pct'], 1).'%' : '').'. Pembelian stok adalah persediaan. Pajak penghasilan tidak dihitung. Ini laporan manajemen, bukan laporan keuangan teraudit.']])
</div>
@endsection
