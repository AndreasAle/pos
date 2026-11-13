@extends('layouts.app')
@section('title', 'Retur Transaksi')
@section('page-title', 'Retur Transaksi')
@section('page-subtitle', 'Order yang di-refund atau dibatalkan, lengkap dengan alasan, kasir, dan tingkat retur terhadap penjualan.')

@section('content')
@php $card = 'min-w-0 rounded-xl border border-[#E5E7EB] bg-white'; $rp = fn ($v) => \App\Support\Money::rp($v); $tz = $f->timezone; @endphp
<div class="y-report text-[14px] text-[#0B1B3F]">
@include('reports.fin._period', ['exports' => ['Excel' => 'xlsx'], 'extra' => '<label class="block"><span class="mb-1 block text-xs font-semibold text-[#64748B]">Jenis</span><select name="status" class="h-10 rounded-lg text-sm">'.collect(['all' => 'Refund + batal', 'refunded' => 'Refund', 'cancelled' => 'Dibatalkan'])->map(fn ($l, $k) => '<option value="'.$k.'"'.($status === $k ? ' selected' : '').'>'.$l.'</option>')->implode('').'</select></label><label class="block"><span class="mb-1 block text-xs font-semibold text-[#64748B]">No. order</span><input type="search" name="q" value="'.e(request('q')).'" class="h-10 rounded-lg text-sm"></label>'])

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="{{ $card }} p-5"><p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Order refund</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ $r['refunded']['orders'] }}</p><p class="mt-1 text-xs text-[#64748B]">{{ $rp($r['refunded']['grand_total']) }} dikembalikan</p></div>
    <div class="{{ $card }} p-5"><p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Order batal (void)</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ $r['cancelled']['orders'] }}</p><p class="mt-1 text-xs text-[#64748B]">{{ $rp($r['cancelled']['grand_total']) }}</p></div>
    <div class="{{ $card }} p-5"><p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Tingkat retur</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ $r['return_rate_pct'] === null ? 'N/A' : \App\Support\Money::num($r['return_rate_pct'], 2).'%' }}</p><p class="mt-1 text-xs text-[#64748B]">(refund + batal) ÷ semua order</p></div>
    <div class="{{ $card }} p-5"><p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Penjualan paid (dasar)</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ $rp($r['paid']['net_sales']) }}</p><p class="mt-1 text-xs text-[#64748B]">{{ $r['paid']['orders'] }} order lunas</p></div>
</div>
<p class="mt-2 text-xs text-[#64748B]">Periode memakai tanggal transaksi asli. Skema hanya mencatat refund dan void penuh per order; refund parsial tidak tersedia.</p>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <section class="{{ $card }} overflow-hidden xl:col-span-2" aria-labelledby="rl">
        <h2 id="rl" class="border-b border-[#E5E7EB] px-4 py-3 text-sm font-semibold">Daftar order retur</h2>
        <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-sm">
            <thead class="text-xs"><tr><th scope="col" class="px-4 py-2.5 text-left">No. order</th><th scope="col" class="px-4 py-2.5 text-left">Transaksi</th><th scope="col" class="px-4 py-2.5 text-left">Jenis</th><th scope="col" class="px-4 py-2.5 text-left">Alasan</th><th scope="col" class="px-4 py-2.5 text-left">Kasir</th><th scope="col" class="px-4 py-2.5 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-[#E5E7EB]">
            @forelse($orders as $o)
                <tr><td class="px-4 py-2.5"><a href="{{ route('reports.sales.order', ['orderId' => $o->id, 'back' => request()->fullUrl()]) }}" class="font-semibold text-[#16A34A] hover:underline">{{ $o->order_number }}</a></td>
                    <td class="whitespace-nowrap px-4 py-2.5 text-[#475569]">{{ $o->created_at->setTimezone($tz)->format('d M H:i') }}@if($o->refunded_at)<span class="block text-xs text-[#94A3B8]">refund {{ $o->refunded_at->setTimezone($tz)->format('d M H:i') }}</span>@endif</td>
                    <td class="px-4 py-2.5"><x-pill :tone="$o->status === 'refunded' ? 'amber' : 'gray'">{{ $o->status === 'refunded' ? 'Refund' : 'Dibatalkan' }}</x-pill></td>
                    <td class="px-4 py-2.5 text-[#475569]">{{ $o->status === 'refunded' ? ($o->refund_reason ?: '-') : ($o->cancel_reason ?: '-') }}</td>
                    <td class="px-4 py-2.5">{{ $o->user?->name }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp((float) $o->grand_total) }}</td></tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-[#64748B]">Tidak ada order retur untuk filter ini.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="border-t border-[#E5E7EB] px-4 py-3">{{ $orders->links() }}</div>
    </section>
    <div class="space-y-4">
        <section class="{{ $card }} p-5" aria-labelledby="rr"><h2 id="rr" class="text-base font-semibold text-[#0B1B3F]">Alasan terbanyak</h2>
            <ul class="mt-2 divide-y divide-[#E5E7EB] text-sm">@forelse($r['reasons'] as $x)<li class="flex justify-between gap-3 py-2"><span>{{ $x['reason'] }} <span class="text-xs text-[#94A3B8]">({{ $x['status'] === 'refunded' ? 'refund' : 'batal' }}, {{ $x['orders'] }})</span></span><span class="tabular-nums">{{ $rp($x['amount']) }}</span></li>@empty<li class="py-4 text-center text-[#94A3B8]">Tidak ada data</li>@endforelse</ul></section>
        <section class="{{ $card }} p-5" aria-labelledby="rc"><h2 id="rc" class="text-base font-semibold text-[#0B1B3F]">Per kasir</h2>
            <ul class="mt-2 divide-y divide-[#E5E7EB] text-sm">@forelse($r['cashiers'] as $x)<li class="flex justify-between py-2"><span>{{ $x['name'] }} <span class="text-xs text-[#94A3B8]">({{ $x['orders'] }})</span></span><span class="tabular-nums">{{ $rp($x['amount']) }}</span></li>@empty<li class="py-4 text-center text-[#94A3B8]">Tidak ada data</li>@endforelse</ul></section>
    </div>
</div>
@include('reports.sales._evidence', ['evidence' => $r['evidence']])
</div>
@endsection
