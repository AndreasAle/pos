@extends('layouts.app')
@section('title', 'Pembelian')
@section('page-title', 'Pembelian')
@section('content')
@php $rp = fn ($v) => \App\Support\Money::rp($v); $ps = ['unpaid' => 'Belum bayar', 'partial' => 'Sebagian', 'paid' => 'Lunas']; $pc = ['unpaid' => 'bg-red-50 text-red-700', 'partial' => 'bg-amber-50 text-amber-800', 'paid' => 'bg-emerald-50 text-emerald-700']; @endphp
<div class="text-[14px] text-gray-800">
@include('finance._flash')
<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-3">
    <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Cari</span><input type="search" name="q" value="{{ request('q') }}" placeholder="No. pembelian / faktur" class="h-10 rounded-lg border-gray-300 text-sm"></label>
    <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Supplier</span><select name="supplier" class="h-10 rounded-lg border-gray-300 text-sm"><option value="">Semua</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier') == $s->id)>{{ $s->name }}</option>@endforeach</select></label>
    <label class="block"><span class="mb-1 block text-xs font-semibold text-gray-500">Pembayaran</span><select name="payment" class="h-10 rounded-lg border-gray-300 text-sm"><option value="">Semua</option>@foreach($ps as $k => $l)<option value="{{ $k }}" @selected(request('payment') === $k)>{{ $l }}</option>@endforeach</select></label>
    <button class="h-10 rounded-lg bg-[#0C7856] px-4 text-sm font-semibold text-white hover:bg-[#0a6a4c]">Terapkan</button>
    <a href="{{ route('purchases.create') }}" class="ml-auto inline-flex h-10 items-center rounded-lg bg-[#0C7856] px-4 text-sm font-semibold text-white hover:bg-[#0a6a4c]">Catat pembelian</a>
</form>
<section class="overflow-hidden rounded-lg border border-gray-200 bg-white"><div class="overflow-x-auto"><table class="w-full min-w-[720px] text-sm"><caption class="sr-only">Daftar pembelian</caption>
<thead class="bg-gray-50 text-xs font-semibold text-gray-500"><tr><th scope="col" class="px-4 py-2.5 text-left">No.</th><th scope="col" class="px-4 py-2.5 text-left">Tanggal</th><th scope="col" class="px-4 py-2.5 text-left">Supplier</th><th scope="col" class="px-4 py-2.5 text-left">Jatuh tempo</th><th scope="col" class="px-4 py-2.5 text-right">Total</th><th scope="col" class="px-4 py-2.5 text-right">Sisa</th><th scope="col" class="px-4 py-2.5 text-left">Status</th></tr></thead>
<tbody class="divide-y divide-gray-100">@forelse($purchases as $p)<tr class="{{ $p->status === 'cancelled' ? 'text-gray-400' : '' }}"><td class="px-4 py-2.5"><a href="{{ route('purchases.show', $p) }}" class="font-semibold text-[#0C7856] hover:underline">{{ $p->purchase_number }}</a></td><td class="px-4 py-2.5">{{ $p->purchase_date->format('d M Y') }}</td><td class="px-4 py-2.5">{{ $p->supplier?->name }}</td><td class="px-4 py-2.5">{{ $p->due_date?->format('d M Y') ?? '-' }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp((float) $p->total) }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp($p->outstanding) }}</td><td class="px-4 py-2.5">@if($p->status === 'cancelled')<span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">Dibatalkan</span>@else<span class="rounded px-2 py-0.5 text-xs font-semibold {{ $pc[$p->payment_status] }}">{{ $ps[$p->payment_status] }}</span>@endif</td></tr>@empty<tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">Belum ada pembelian.</td></tr>@endforelse</tbody></table></div>
<div class="border-t border-gray-100 px-4 py-3">{{ $purchases->links() }}</div></section></div>
@endsection
