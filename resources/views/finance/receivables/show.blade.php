@extends('layouts.app')
@section('title', $receivable->receivable_number)
@section('page-title', 'Piutang '.$receivable->receivable_number)
@section('content')
@php $rp = fn ($v) => \App\Support\Money::rp($v); $in = 'h-10 w-full rounded-lg text-sm'; $lb = 'mb-1 block text-xs font-semibold text-[#64748B]'; $ml = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'ewallet' => 'E-wallet', 'debit' => 'Debit', 'other' => 'Lainnya']; $void = $receivable->status === 'void'; @endphp
<div class="y-report text-[14px] text-[#0B1B3F]">
<a href="{{ route('receivables.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-[#64748B] hover:text-[#0B1B3F]"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Daftar piutang</a>
@include('finance._flash')
<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
<section class="min-w-0 rounded-xl border border-[#E5E7EB] bg-white p-4 xl:col-span-2">
    <div class="flex flex-wrap justify-between gap-2"><div><h2 class="text-base font-bold text-[#0B1B3F]">{{ $receivable->party_name }}</h2><p class="text-xs text-[#64748B]">Terbit {{ $receivable->issue_date->format('d M Y') }} · jatuh tempo {{ $receivable->due_date?->format('d M Y') ?? '-' }}@if($receivable->customer) · pelanggan {{ $receivable->customer->name }}@endif</p></div>
        @php $st = $void ? ['gray', 'Dibatalkan'] : ['unpaid' => ['red', 'Belum dibayar'], 'partial' => ['amber', 'Sebagian'], 'paid' => ['green', 'Lunas']][$receivable->payment_status]; @endphp
        <x-pill :tone="$st[0]" class="h-fit">{{ $st[1] }}</x-pill></div>
    @if($receivable->description)<p class="mt-2 text-sm text-[#475569]">{{ $receivable->description }}</p>@endif
    <dl class="mt-4 grid grid-cols-3 gap-3 text-sm"><div class="rounded-xl border border-[#E5E7EB] p-3"><dt class="text-xs text-[#64748B]">Jumlah</dt><dd class="mt-1 font-bold tabular-nums">{{ $rp((float) $receivable->amount) }}</dd></div><div class="rounded-xl border border-[#E5E7EB] p-3"><dt class="text-xs text-[#64748B]">Diterima</dt><dd class="mt-1 font-bold tabular-nums">{{ $rp((float) $receivable->paid_amount) }}</dd></div><div class="rounded-lg border border-[#16A34A]/40 p-3"><dt class="text-xs text-[#16A34A]">Sisa</dt><dd class="mt-1 font-bold tabular-nums">{{ $rp($receivable->outstanding) }}</dd></div></dl>
    <h3 class="mt-4 text-sm font-semibold">Riwayat penerimaan</h3>
    <ul class="mt-1 divide-y divide-[#E5E7EB] text-sm">@forelse($receivable->payments as $p)<li class="flex justify-between py-2"><span>{{ $p->paid_at->format('d M Y') }} · {{ $ml[$p->payment_method] }}@if($p->notes)<span class="text-xs text-[#94A3B8]"> · {{ $p->notes }}</span>@endif</span><span class="tabular-nums">{{ $rp((float) $p->amount) }}</span></li>@empty<li class="py-4 text-center text-[#94A3B8]">Belum ada penerimaan</li>@endforelse</ul>
    @if($void)<p class="mt-3 text-sm text-[#475569]">Dibatalkan: {{ $receivable->void_reason }}</p>@endif
</section>
<div class="space-y-4">
@if(! $void && $receivable->outstanding > 0)
<section class="min-w-0 rounded-xl border border-[#E5E7EB] bg-white p-4"><h2 class="text-base font-semibold text-[#0B1B3F]">Terima pembayaran</h2>
<form method="POST" action="{{ route('receivables.pay', $receivable) }}" class="mt-3 space-y-3">@csrf
<label class="block"><span class="{{ $lb }}">Jumlah (maks {{ $rp($receivable->outstanding) }})</span><input type="number" name="amount" required min="1" max="{{ $receivable->outstanding }}" value="{{ (int) $receivable->outstanding }}" class="{{ $in }}"></label>
<div class="grid grid-cols-2 gap-3"><label class="block"><span class="{{ $lb }}">Tanggal</span><input type="date" name="paid_at" required value="{{ now()->toDateString() }}" class="{{ $in }}"></label><label class="block"><span class="{{ $lb }}">Metode</span><select name="payment_method" class="{{ $in }}">@foreach($ml as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label></div>
<label class="block"><span class="{{ $lb }}">Catatan</span><input name="notes" maxlength="255" class="{{ $in }}"></label>
<button class="h-10 rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white hover:bg-[#15803D]">Catat penerimaan</button></form></section>
@endif
@if(! $void && (float) $receivable->paid_amount <= 0)
<section class="min-w-0 rounded-xl border border-[#E5E7EB] bg-white p-4"><h2 class="text-base font-semibold text-[#0B1B3F]">Batalkan piutang</h2>
<form method="POST" action="{{ route('receivables.void', $receivable) }}" class="mt-2 space-y-2" x-data x-on:submit="if (!confirm('Batalkan piutang ini?')) $event.preventDefault()">@csrf<input name="reason" required maxlength="255" placeholder="Alasan" class="{{ $in }}"><button class="h-10 rounded-lg border border-red-300 px-4 text-sm font-semibold text-red-700 hover:bg-red-50">Batalkan</button></form></section>
@endif
</div></div></div>
@endsection
