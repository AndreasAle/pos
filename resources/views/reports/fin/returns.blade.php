@extends('layouts.app')
@section('title', 'Retur Transaksi')
@section('page-title', 'Retur Transaksi')

@section('content')
@php $card = 'min-w-0 rounded-lg border border-gray-200 bg-white'; $rp = fn ($v) => \App\Support\Money::rp($v); $tz = $f->timezone; @endphp
<div class="text-[14px] text-gray-800">
@include('reports.fin._period', ['exports' => ['Excel' => 'xlsx'], 'extra' => '<label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Jenis</span><select name="status" class="h-10 rounded-lg border-gray-300 text-sm">'.collect(['all' => 'Refund + batal', 'refunded' => 'Refund', 'cancelled' => 'Batal'])->map(fn ($l, $k) => '<option value="'.$k.'"'.($status === $k ? ' selected' : '').'>'.$l.'</option>')->implode('').'</select></label><label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">No. order</span><input type="search" name="q" value="'.e(request('q')).'" class="h-10 rounded-lg border-gray-300 text-sm"></label>'])

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="{{ $card }} p-4"><p class="text-xs font-semibold text-gray-500">Order refund</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ $r['refunded']['orders'] }}</p><p class="mt-1 text-xs text-gray-500">{{ $rp($r['refunded']['grand_total']) }} dikembalikan</p></div>
    <div class="{{ $card }} p-4"><p class="text-xs font-semibold text-gray-500">Order batal (void)</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ $r['cancelled']['orders'] }}</p><p class="mt-1 text-xs text-gray-500">{{ $rp($r['cancelled']['grand_total']) }}</p></div>
    <div class="{{ $card }} p-4"><p class="text-xs font-semibold text-gray-500">Tingkat retur</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ $r['return_rate_pct'] === null ? 'N/A' : \App\Support\Money::num($r['return_rate_pct'], 2).'%' }}</p><p class="mt-1 text-xs text-gray-500">(refund + batal) ÷ semua order</p></div>
    <div class="{{ $card }} p-4"><p class="text-xs font-semibold text-gray-500">Penjualan paid (dasar)</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ $rp($r['paid']['net_sales']) }}</p><p class="mt-1 text-xs text-gray-500">{{ $r['paid']['orders'] }} order lunas</p></div>
</div>
<p class="mt-2 text-xs text-gray-500">Periode memakai tanggal transaksi asli. Skema hanya mencatat refund dan void penuh per order; refund parsial tidak tersedia.</p>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
    <section class="{{ $card }} overflow-hidden xl:col-span-2" aria-labelledby="rl">
        <h2 id="rl" class="border-b border-gray-100 px-4 py-3 text-sm font-semibold">Daftar order retur</h2>
        <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-sm">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-500"><tr><th scope="col" class="px-4 py-2.5 text-left">No. order</th><th scope="col" class="px-4 py-2.5 text-left">Transaksi</th><th scope="col" class="px-4 py-2.5 text-left">Jenis</th><th scope="col" class="px-4 py-2.5 text-left">Alasan</th><th scope="col" class="px-4 py-2.5 text-left">Kasir</th><th scope="col" class="px-4 py-2.5 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($orders as $o)
                <tr><td class="px-4 py-2.5"><a href="{{ route('reports.sales.order', ['orderId' => $o->id, 'back' => request()->fullUrl()]) }}" class="font-semibold text-[#0C7856] hover:underline">{{ $o->order_number }}</a></td>
                    <td class="whitespace-nowrap px-4 py-2.5 text-gray-600">{{ $o->created_at->setTimezone($tz)->format('d M H:i') }}@if($o->refunded_at)<span class="block text-xs text-gray-400">refund {{ $o->refunded_at->setTimezone($tz)->format('d M H:i') }}</span>@endif</td>
                    <td class="px-4 py-2.5"><span class="rounded px-2 py-0.5 text-xs font-semibold {{ $o->status === 'refunded' ? 'bg-amber-50 text-amber-800' : 'bg-gray-100 text-gray-600' }}">{{ $o->status === 'refunded' ? 'Refund' : 'Batal' }}</span></td>
                    <td class="px-4 py-2.5 text-gray-600">{{ $o->status === 'refunded' ? ($o->refund_reason ?: '-') : ($o->cancel_reason ?: '-') }}</td>
                    <td class="px-4 py-2.5">{{ $o->user?->name }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp((float) $o->grand_total) }}</td></tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">Tidak ada order retur untuk filter ini.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="border-t border-gray-100 px-4 py-3">{{ $orders->links() }}</div>
    </section>
    <div class="space-y-4">
        <section class="{{ $card }} p-4" aria-labelledby="rr"><h2 id="rr" class="text-sm font-semibold">Alasan terbanyak</h2>
            <ul class="mt-2 divide-y divide-gray-100 text-sm">@forelse($r['reasons'] as $x)<li class="flex justify-between gap-3 py-2"><span>{{ $x['reason'] }} <span class="text-xs text-gray-400">({{ $x['status'] === 'refunded' ? 'refund' : 'batal' }}, {{ $x['orders'] }})</span></span><span class="tabular-nums">{{ $rp($x['amount']) }}</span></li>@empty<li class="py-4 text-center text-gray-400">Tidak ada data</li>@endforelse</ul></section>
        <section class="{{ $card }} p-4" aria-labelledby="rc"><h2 id="rc" class="text-sm font-semibold">Per kasir</h2>
            <ul class="mt-2 divide-y divide-gray-100 text-sm">@forelse($r['cashiers'] as $x)<li class="flex justify-between py-2"><span>{{ $x['name'] }} <span class="text-xs text-gray-400">({{ $x['orders'] }})</span></span><span class="tabular-nums">{{ $rp($x['amount']) }}</span></li>@empty<li class="py-4 text-center text-gray-400">Tidak ada data</li>@endforelse</ul></section>
    </div>
</div>
@include('reports.sales._evidence', ['evidence' => $r['evidence']])
</div>
@endsection
