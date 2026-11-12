@extends('layouts.app')
@section('title', 'Hutang')
@section('page-title', 'Hutang Usaha')
@section('content')
@php $rp = fn ($v) => \App\Support\Money::rp($v); @endphp
<div class="text-[14px] text-gray-800">
@include('finance._flash')
@include('finance._aging', ['aging' => $aging, 'labels' => $labels, 'title' => 'Umur hutang ke supplier'])
<section class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white" aria-labelledby="pl">
    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3"><h2 id="pl" class="text-sm font-semibold">Tagihan belum lunas</h2><a href="{{ route('purchases.create') }}" class="inline-flex h-9 items-center rounded-lg bg-[#0C7856] px-3 text-sm font-semibold text-white hover:bg-[#0a6a4c]">Catat pembelian</a></div>
    <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-sm"><thead class="bg-gray-50 text-xs font-semibold text-gray-500"><tr><th scope="col" class="px-4 py-2.5 text-left">No. pembelian</th><th scope="col" class="px-4 py-2.5 text-left">Supplier</th><th scope="col" class="px-4 py-2.5 text-left">Jatuh tempo</th><th scope="col" class="px-4 py-2.5 text-right">Total</th><th scope="col" class="px-4 py-2.5 text-right">Dibayar</th><th scope="col" class="px-4 py-2.5 text-right">Sisa</th></tr></thead>
    <tbody class="divide-y divide-gray-100">@forelse($aging['rows'] as $r)<tr><td class="px-4 py-2.5"><a href="{{ route('purchases.show', $r['id']) }}" class="font-semibold text-[#0C7856] hover:underline">{{ $r['number'] }}</a></td><td class="px-4 py-2.5">{{ $r['party'] }}</td><td class="px-4 py-2.5 {{ $r['days_overdue'] > 0 ? 'text-red-600' : '' }}">{{ $r['due'] ? $r['due']->format('d M Y') : '-' }}@if($r['days_overdue'] > 0)<span class="text-xs"> · lewat {{ $r['days_overdue'] }} hari</span>@endif</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp($r['total']) }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp($r['paid']) }}</td><td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ $rp($r['outstanding']) }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">Tidak ada hutang yang belum lunas.</td></tr>@endforelse</tbody></table></div>
</section></div>
@endsection
