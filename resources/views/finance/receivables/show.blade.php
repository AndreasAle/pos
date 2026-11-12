@extends('layouts.app')
@section('title', $receivable->receivable_number)
@section('page-title', 'Piutang '.$receivable->receivable_number)
@section('content')
@php $rp = fn ($v) => \App\Support\Money::rp($v); $in = 'h-10 w-full rounded-lg border-gray-300 text-sm focus:border-[#0C7856] focus:ring-[#0C7856]'; $lb = 'mb-1 block text-xs font-semibold text-gray-500'; $ml = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'ewallet' => 'E-wallet', 'debit' => 'Debit', 'other' => 'Lainnya']; $void = $receivable->status === 'void'; @endphp
<div class="text-[14px] text-gray-800">
<a href="{{ route('receivables.index') }}" class="mb-3 inline-block text-sm font-semibold text-[#0C7856] hover:underline">← Daftar piutang</a>
@include('finance._flash')
<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
<section class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 xl:col-span-2">
    <div class="flex flex-wrap justify-between gap-2"><div><h2 class="text-base font-bold text-[#102C35]">{{ $receivable->party_name }}</h2><p class="text-xs text-gray-500">Terbit {{ $receivable->issue_date->format('d M Y') }} · jatuh tempo {{ $receivable->due_date?->format('d M Y') ?? '-' }}@if($receivable->customer) · pelanggan {{ $receivable->customer->name }}@endif</p></div>
        <span class="h-fit rounded px-2 py-0.5 text-xs font-semibold {{ $void ? 'bg-gray-100 text-gray-600' : ['unpaid' => 'bg-red-50 text-red-700', 'partial' => 'bg-amber-50 text-amber-800', 'paid' => 'bg-emerald-50 text-emerald-700'][$receivable->payment_status] }}">{{ $void ? 'Dibatalkan' : ['unpaid' => 'Belum dibayar', 'partial' => 'Sebagian', 'paid' => 'Lunas'][$receivable->payment_status] }}</span></div>
    @if($receivable->description)<p class="mt-2 text-sm text-gray-600">{{ $receivable->description }}</p>@endif
    <dl class="mt-4 grid grid-cols-3 gap-3 text-sm"><div class="rounded-lg border border-gray-200 p-3"><dt class="text-xs text-gray-500">Jumlah</dt><dd class="mt-1 font-bold tabular-nums">{{ $rp((float) $receivable->amount) }}</dd></div><div class="rounded-lg border border-gray-200 p-3"><dt class="text-xs text-gray-500">Diterima</dt><dd class="mt-1 font-bold tabular-nums">{{ $rp((float) $receivable->paid_amount) }}</dd></div><div class="rounded-lg border border-[#0C7856]/40 p-3"><dt class="text-xs text-[#0C7856]">Sisa</dt><dd class="mt-1 font-bold tabular-nums">{{ $rp($receivable->outstanding) }}</dd></div></dl>
    <h3 class="mt-4 text-sm font-semibold">Riwayat penerimaan</h3>
    <ul class="mt-1 divide-y divide-gray-100 text-sm">@forelse($receivable->payments as $p)<li class="flex justify-between py-2"><span>{{ $p->paid_at->format('d M Y') }} · {{ $ml[$p->payment_method] }}@if($p->notes)<span class="text-xs text-gray-400"> · {{ $p->notes }}</span>@endif</span><span class="tabular-nums">{{ $rp((float) $p->amount) }}</span></li>@empty<li class="py-4 text-center text-gray-400">Belum ada penerimaan</li>@endforelse</ul>
    @if($void)<p class="mt-3 text-sm text-gray-600">Dibatalkan: {{ $receivable->void_reason }}</p>@endif
</section>
<div class="space-y-4">
@if(! $void && $receivable->outstanding > 0)
<section class="min-w-0 rounded-lg border border-gray-200 bg-white p-4"><h2 class="text-sm font-semibold">Terima pembayaran</h2>
<form method="POST" action="{{ route('receivables.pay', $receivable) }}" class="mt-3 space-y-3">@csrf
<label class="block"><span class="{{ $lb }}">Jumlah (maks {{ $rp($receivable->outstanding) }})</span><input type="number" name="amount" required min="1" max="{{ $receivable->outstanding }}" value="{{ (int) $receivable->outstanding }}" class="{{ $in }}"></label>
<div class="grid grid-cols-2 gap-3"><label class="block"><span class="{{ $lb }}">Tanggal</span><input type="date" name="paid_at" required value="{{ now()->toDateString() }}" class="{{ $in }}"></label><label class="block"><span class="{{ $lb }}">Metode</span><select name="payment_method" class="{{ $in }}">@foreach($ml as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label></div>
<label class="block"><span class="{{ $lb }}">Catatan</span><input name="notes" maxlength="255" class="{{ $in }}"></label>
<button class="h-10 rounded-lg bg-[#0C7856] px-4 text-sm font-semibold text-white hover:bg-[#0a6a4c]">Catat penerimaan</button></form></section>
@endif
@if(! $void && (float) $receivable->paid_amount <= 0)
<section class="min-w-0 rounded-lg border border-gray-200 bg-white p-4"><h2 class="text-sm font-semibold">Batalkan piutang</h2>
<form method="POST" action="{{ route('receivables.void', $receivable) }}" class="mt-2 space-y-2" x-data x-on:submit="if (!confirm('Batalkan piutang ini?')) $event.preventDefault()">@csrf<input name="reason" required maxlength="255" placeholder="Alasan" class="{{ $in }}"><button class="h-10 rounded-lg border border-red-300 px-4 text-sm font-semibold text-red-700 hover:bg-red-50">Batalkan</button></form></section>
@endif
</div></div></div>
@endsection
