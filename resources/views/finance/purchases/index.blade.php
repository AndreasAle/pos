@extends('layouts.app')
@section('title', 'Pembelian')
@section('page-title', 'Pembelian')
@section('page-subtitle', 'Belanja bahan baku dan barang dari supplier. Pembelian yang diterima otomatis menambah stok.')
@section('content')
@php
    $rp = fn ($v) => \App\Support\Money::rp($v);
    $ps = ['unpaid' => ['red', 'Belum bayar'], 'partial' => ['amber', 'Sebagian'], 'paid' => ['green', 'Lunas']];
    $filtered = request()->filled('q') || request()->filled('supplier') || request()->filled('payment');
@endphp
<div class="y-report">
@include('finance._flash')

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Pembelian bulan ini" :value="$rp($stats['month_total'])" :sub="$stats['month_count'].' pembelian'" tone="brand"/>
    <x-kpi label="Hutang berjalan" :value="$rp($stats['outstanding'])" sub="Belum dibayar ke supplier" :href="route('payables.index')"/>
    <x-kpi label="Lewat jatuh tempo" :value="$stats['overdue_count'].' tagihan'" :sub="$stats['overdue_count'] > 0 ? $rp($stats['overdue_amount']).' menunggak' : 'Tidak ada yang menunggak'" :tone="$stats['overdue_count'] > 0 ? 'danger' : 'default'" :href="route('payables.index')"/>
    <x-kpi label="Supplier aktif" :value="$suppliers->count()" sub="Kelola daftar supplier →" :href="route('suppliers.index')"/>
</div>

<form method="GET" class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-[#E5E7EB] bg-white p-4">
    <label class="block min-w-[200px] flex-1"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Cari</span><input type="search" name="q" value="{{ request('q') }}" placeholder="No. pembelian atau faktur" class="h-10 w-full px-3"></label>
    <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Supplier</span><select name="supplier" class="h-10 min-w-[170px] px-3"><option value="">Semua supplier</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier') == $s->id)>{{ $s->name }}</option>@endforeach</select></label>
    <label class="block"><span class="mb-1.5 block text-xs font-semibold text-[#64748B]">Pembayaran</span><select name="payment" class="h-10 min-w-[150px] px-3"><option value="">Semua</option>@foreach($ps as $k => [$tone, $l])<option value="{{ $k }}" @selected(request('payment') === $k)>{{ $l }}</option>@endforeach</select></label>
    <button class="h-10 rounded-lg bg-[#16A34A] px-5 text-sm font-semibold text-white shadow-sm hover:bg-[#15803D]">Terapkan</button>
    @if($filtered)<a href="{{ route('purchases.index') }}" class="inline-flex h-10 items-center rounded-lg px-3 text-sm font-semibold text-[#64748B] hover:bg-slate-100">Reset</a>@endif
    <a href="{{ route('purchases.create') }}" class="ml-auto inline-flex h-10 items-center gap-2 rounded-lg border border-[#16A34A] px-4 text-sm font-semibold text-[#15803D] hover:bg-[#ECFDF3]"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>Catat pembelian</a>
</form>

<x-panel class="mt-4" flush>
    @if($purchases->count())
    <div class="overflow-x-auto">
        <table class="w-full min-w-[780px] text-sm"><caption class="sr-only">Daftar pembelian</caption>
            <thead><tr><th scope="col" class="px-5 py-3 text-left">No.</th><th scope="col" class="px-5 py-3 text-left">Tanggal</th><th scope="col" class="px-5 py-3 text-left">Supplier</th><th scope="col" class="px-5 py-3 text-left">Jatuh tempo</th><th scope="col" class="px-5 py-3 text-right">Total</th><th scope="col" class="px-5 py-3 text-right">Sisa</th><th scope="col" class="px-5 py-3 text-left">Status</th></tr></thead>
            <tbody class="divide-y divide-[#E5E7EB]">
            @foreach($purchases as $p)
                @php $cancelled = $p->status === 'cancelled'; $late = ! $cancelled && $p->outstanding > 0 && $p->due_date && $p->due_date->endOfDay()->isPast(); @endphp
                <tr class="cursor-pointer {{ $cancelled ? 'text-[#94A3B8]' : '' }}" onclick="window.location='{{ route('purchases.show', $p) }}'">
                    <td class="px-5 py-3.5"><a href="{{ route('purchases.show', $p) }}" class="font-mono text-[13px] font-semibold text-[#16A34A] hover:underline">{{ $p->purchase_number }}</a>@if($p->supplier_invoice_no)<span class="block text-xs text-[#94A3B8]">Faktur {{ $p->supplier_invoice_no }}</span>@endif</td>
                    <td class="px-5 py-3.5 whitespace-nowrap">{{ $p->purchase_date->format('d M Y') }}</td>
                    <td class="px-5 py-3.5 font-medium">{{ $p->supplier?->name }}</td>
                    <td class="px-5 py-3.5 whitespace-nowrap">{{ $p->due_date?->format('d M Y') ?? '-' }}@if($late)<x-pill tone="red" class="ml-1.5">Lewat tempo</x-pill>@endif</td>
                    <td class="px-5 py-3.5 text-right tabular-nums">{{ $rp((float) $p->total) }}</td>
                    <td class="px-5 py-3.5 text-right tabular-nums {{ ! $cancelled && $p->outstanding > 0 ? 'font-bold' : '' }}">{{ $cancelled ? '-' : $rp($p->outstanding) }}</td>
                    <td class="px-5 py-3.5">@if($cancelled)<x-pill>Dibatalkan</x-pill>@else<x-pill :tone="$ps[$p->payment_status][0]">{{ $ps[$p->payment_status][1] }}</x-pill>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="border-t border-[#E5E7EB] px-5 py-3">{{ $purchases->links() }}</div>
    @else
        <x-empty :title="$filtered ? 'Tidak ada pembelian yang cocok' : 'Belum ada pembelian'" :text="$filtered ? 'Coba ubah atau reset filter.' : 'Catat belanja dari supplier. Stok bahan baku bertambah otomatis dan hutang tercatat bila belum dibayar lunas.'" icon="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z">
            <a href="{{ route('purchases.create') }}" class="inline-flex h-10 items-center rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white hover:bg-[#15803D]">Catat pembelian</a>
        </x-empty>
    @endif
</x-panel>
</div>
@endsection
