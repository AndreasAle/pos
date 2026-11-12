@extends('layouts.app')
@section('title', $purchase->purchase_number)
@section('page-title', 'Pembelian '.$purchase->purchase_number)
@section('content')
@php $rp = fn ($v) => \App\Support\Money::rp($v); $in = 'h-10 w-full rounded-lg text-sm'; $lb = 'mb-1 block text-xs font-semibold text-[#64748B]'; $ml = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'ewallet' => 'E-wallet', 'debit' => 'Debit', 'other' => 'Lainnya']; $cancelled = $purchase->status === 'cancelled'; @endphp
<div class="y-report text-[14px] text-[#0B1B3F]">
<a href="{{ route('purchases.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-[#64748B] hover:text-[#0B1B3F]"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Daftar pembelian</a>
@include('finance._flash')
<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
<section class="min-w-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white xl:col-span-2">
    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-[#E5E7EB] px-4 py-3"><div><h2 class="text-base font-bold text-[#0B1B3F]">{{ $purchase->supplier?->name }}</h2><p class="text-xs text-[#64748B]">{{ $purchase->purchase_date->format('d M Y') }} · jatuh tempo {{ $purchase->due_date?->format('d M Y') ?? '-' }}@if($purchase->supplier_invoice_no) · faktur {{ $purchase->supplier_invoice_no }}@endif · dicatat {{ $purchase->user?->name }}</p></div>
        @php $st = $cancelled ? ['gray', 'Dibatalkan'] : ['unpaid' => ['red', 'Belum bayar'], 'partial' => ['amber', 'Sebagian'], 'paid' => ['green', 'Lunas']][$purchase->payment_status]; @endphp
        <x-pill :tone="$st[0]">{{ $st[1] }}</x-pill></div>
    <div class="overflow-x-auto"><table class="w-full min-w-[520px] text-sm"><thead class="text-xs"><tr><th scope="col" class="px-4 py-2.5 text-left">Barang</th><th scope="col" class="px-4 py-2.5 text-right">Qty</th><th scope="col" class="px-4 py-2.5 text-right">Harga</th><th scope="col" class="px-4 py-2.5 text-right">Subtotal</th></tr></thead>
    <tbody class="divide-y divide-[#E5E7EB]">@foreach($purchase->items as $i)<tr><td class="px-4 py-2.5">{{ $i->description }}@if($i->ingredient)<span class="block text-xs text-[#64748B]">Bahan: {{ $i->ingredient->name }}{{ $purchase->stock_received ? ' · stok diterima' : '' }}</span>@endif</td><td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::num((float) $i->qty, 3) }} {{ $i->unit }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp((float) $i->unit_cost) }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp((float) $i->subtotal) }}</td></tr>@endforeach</tbody></table></div>
    <dl class="space-y-1.5 border-t border-[#E5E7EB] p-4 text-sm sm:ml-auto sm:max-w-xs"><div class="flex justify-between"><dt class="text-[#64748B]">Subtotal</dt><dd class="tabular-nums">{{ $rp((float) $purchase->subtotal) }}</dd></div><div class="flex justify-between"><dt class="text-[#64748B]">Diskon</dt><dd class="tabular-nums">−{{ $rp((float) $purchase->discount_amount) }}</dd></div><div class="flex justify-between"><dt class="text-[#64748B]">Pajak</dt><dd class="tabular-nums">{{ $rp((float) $purchase->tax_amount) }}</dd></div><div class="flex justify-between"><dt class="text-[#64748B]">Ongkir</dt><dd class="tabular-nums">{{ $rp((float) $purchase->shipping_amount) }}</dd></div><div class="flex justify-between border-t border-gray-200 pt-1.5 font-bold"><dt>Total</dt><dd class="tabular-nums">{{ $rp((float) $purchase->total) }}</dd></div><div class="flex justify-between"><dt class="text-[#64748B]">Dibayar</dt><dd class="tabular-nums">{{ $rp((float) $purchase->paid_amount) }}</dd></div><div class="flex justify-between font-semibold"><dt>Sisa hutang</dt><dd class="tabular-nums">{{ $rp($purchase->outstanding) }}</dd></div></dl>
    @if($cancelled)<p class="border-t border-[#E5E7EB] px-4 py-3 text-sm text-[#475569]">Dibatalkan {{ $purchase->cancelled_at?->format('d M Y H:i') }}: {{ $purchase->cancel_reason }}</p>@endif
</section>
<div class="space-y-4">
    <section class="min-w-0 rounded-xl border border-[#E5E7EB] bg-white p-4"><h2 class="text-base font-semibold text-[#0B1B3F]">Riwayat pembayaran</h2>
        <ul class="mt-2 divide-y divide-[#E5E7EB] text-sm">@forelse($purchase->payments as $p)<li class="flex justify-between gap-3 py-2"><span>{{ $p->paid_at->format('d M Y') }} · {{ $ml[$p->payment_method] }}@if($p->reference_no)<span class="text-xs text-[#94A3B8]"> · {{ $p->reference_no }}</span>@endif</span><span class="tabular-nums">{{ $rp((float) $p->amount) }}</span></li>@empty<li class="py-4 text-center text-[#94A3B8]">Belum ada pembayaran</li>@endforelse</ul></section>
    @if(! $cancelled && $purchase->outstanding > 0)
    <section class="min-w-0 rounded-xl border border-[#E5E7EB] bg-white p-4"><h2 class="text-base font-semibold text-[#0B1B3F]">Bayar hutang</h2>
        <form method="POST" action="{{ route('purchases.pay', $purchase) }}" class="mt-3 space-y-3">@csrf
            <label class="block"><span class="{{ $lb }}">Jumlah (maks {{ $rp($purchase->outstanding) }})</span><input type="number" name="amount" required min="1" max="{{ $purchase->outstanding }}" value="{{ (int) $purchase->outstanding }}" class="{{ $in }}"></label>
            <div class="grid grid-cols-2 gap-3"><label class="block"><span class="{{ $lb }}">Tanggal</span><input type="date" name="paid_at" required value="{{ now()->toDateString() }}" class="{{ $in }}"></label><label class="block"><span class="{{ $lb }}">Metode</span><select name="payment_method" class="{{ $in }}">@foreach($ml as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label></div>
            <label class="block"><span class="{{ $lb }}">No. referensi</span><input name="reference_no" maxlength="100" class="{{ $in }}"></label>
            <button class="h-10 rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white hover:bg-[#15803D]">Catat pembayaran</button></form></section>
    @endif
    @if(! $cancelled && (float) $purchase->paid_amount <= 0)
    <section class="min-w-0 rounded-xl border border-[#E5E7EB] bg-white p-4"><h2 class="text-base font-semibold text-[#0B1B3F]">Batalkan pembelian</h2><p class="mt-1 text-xs text-[#64748B]">Hanya bila belum ada pembayaran. Stok yang diterima akan ditarik kembali.</p>
        <form method="POST" action="{{ route('purchases.cancel', $purchase) }}" class="mt-2 space-y-2" x-data x-on:submit="if (!confirm('Batalkan pembelian ini?')) $event.preventDefault()">@csrf<input name="reason" required maxlength="255" placeholder="Alasan" class="{{ $in }}"><button class="h-10 rounded-lg border border-red-300 px-4 text-sm font-semibold text-red-700 hover:bg-red-50">Batalkan</button></form></section>
    @endif
</div></div></div>
@endsection
