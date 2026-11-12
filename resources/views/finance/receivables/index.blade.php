@extends('layouts.app')
@section('title', 'Piutang')
@section('page-title', 'Piutang Usaha')
@section('content')
@php $rp = fn ($v) => \App\Support\Money::rp($v); $void = request('status') === 'void'; @endphp
<div class="text-[14px] text-gray-800">
@include('finance._flash')
@include('finance._aging', ['aging' => $aging, 'labels' => $labels, 'title' => 'Umur piutang'])
<section class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white" aria-labelledby="rl">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3"><h2 id="rl" class="text-sm font-semibold">{{ $void ? 'Piutang dibatalkan' : 'Daftar piutang' }}</h2>
        <div class="flex gap-2"><a href="{{ route('receivables.index', $void ? [] : ['status' => 'void']) }}" class="inline-flex h-9 items-center rounded-lg border border-gray-300 px-3 text-sm font-semibold text-gray-700 hover:border-[#0C7856]">{{ $void ? 'Lihat aktif' : 'Lihat dibatalkan' }}</a><a href="{{ route('receivables.create') }}" class="inline-flex h-9 items-center rounded-lg bg-[#0C7856] px-3 text-sm font-semibold text-white hover:bg-[#0a6a4c]">Catat piutang</a></div></div>
    <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-sm"><thead class="bg-gray-50 text-xs font-semibold text-gray-500"><tr><th scope="col" class="px-4 py-2.5 text-left">No.</th><th scope="col" class="px-4 py-2.5 text-left">Pihak</th><th scope="col" class="px-4 py-2.5 text-left">Terbit</th><th scope="col" class="px-4 py-2.5 text-left">Jatuh tempo</th><th scope="col" class="px-4 py-2.5 text-right">Jumlah</th><th scope="col" class="px-4 py-2.5 text-right">Sisa</th></tr></thead>
    <tbody class="divide-y divide-gray-100">@forelse($receivables as $r)<tr><td class="px-4 py-2.5"><a href="{{ route('receivables.show', $r) }}" class="font-semibold text-[#0C7856] hover:underline">{{ $r->receivable_number }}</a></td><td class="px-4 py-2.5">{{ $r->party_name }}</td><td class="px-4 py-2.5">{{ $r->issue_date->format('d M Y') }}</td><td class="px-4 py-2.5">{{ $r->due_date?->format('d M Y') ?? '-' }}</td><td class="px-4 py-2.5 text-right tabular-nums">{{ $rp((float) $r->amount) }}</td><td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ $rp($r->outstanding) }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">Belum ada piutang.</td></tr>@endforelse</tbody></table></div>
    <div class="border-t border-gray-100 px-4 py-3">{{ $receivables->links() }}</div>
</section>
<p class="mt-2 text-xs text-gray-500">Piutang dicatat manual. Kasir POS belum mendukung penjualan kredit, jadi tidak ada piutang otomatis dari transaksi.</p></div>
@endsection
