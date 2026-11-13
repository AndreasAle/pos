@extends('layouts.app')
@section('title','Laporan Inventory')
@section('page-title','Laporan Inventory')
@section('page-subtitle','Posisi stok bahan baku saat ini, nilai persediaan, dan pergerakan stok pada periode yang dipilih.')
@section('content')
@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $value = $ingredients->sum(fn ($i) => $i->current_stock * $i->average_cost);
    $mvTone = ['in' => ['green', 'Stok masuk'], 'out' => ['red', 'Stok keluar'], 'adjustment' => ['blue', 'Penyesuaian'], 'sale' => ['amber', 'Penjualan'], 'return' => ['blue', 'Retur'], 'waste' => ['gray', 'Terbuang']];
@endphp
<div class="y-report">
@include('reports._nav')
@include('reports._filter', ['f' => $f])

<div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
    <x-kpi label="Bahan baku" :value="count($ingredients)" sub="Aktif dan terdaftar"/>
    <x-kpi label="Stok menipis" :value="count($lowStock)" :sub="count($lowStock) ? 'Segera belanja' : 'Semua stok aman'" :tone="count($lowStock) ? 'warn' : 'default'"/>
    <x-kpi label="Nilai persediaan" :value="$rp($value)" sub="Stok × harga rata-rata" tone="brand"/>
</div>

@if(count($lowStock))
<x-panel class="mt-4" title="Perlu segera dibeli" :sub="count($lowStock).' bahan di bawah batas minimum'">
    <x-slot:actions><a href="{{ route('purchases.create') }}" class="inline-flex h-10 items-center rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#15803D]">Catat pembelian</a></x-slot:actions>
    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($lowStock as $ing)
        @php $empty = (float) $ing->current_stock <= 0; @endphp
        <div class="flex items-center justify-between gap-3 rounded-lg border {{ $empty ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }} px-3.5 py-2.5">
            <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ $ing->name }}</p><p class="text-xs text-[#64748B]">Minimum {{ rtrim(rtrim(number_format($ing->minimum_stock, 2, ',', '.'), '0'), ',') }} {{ $ing->unit }}</p></div>
            <span class="shrink-0 text-sm font-bold tabular-nums {{ $empty ? 'text-red-700' : 'text-amber-800' }}">{{ $empty ? 'Habis' : rtrim(rtrim(number_format($ing->current_stock, 2, ',', '.'), '0'), ',').' '.$ing->unit }}</span>
        </div>
        @endforeach
    </div>
</x-panel>
@endif

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
    <x-panel flush title="Stok saat ini" :sub="count($ingredients).' bahan baku'">
        @if(count($ingredients))
        <div class="overflow-x-auto">
            <table class="w-full min-w-[420px] text-sm">
                <thead><tr><th class="px-5 py-3 text-left">Bahan</th><th class="px-3 py-3 text-center">Satuan</th><th class="px-3 py-3 text-right">Stok</th><th class="px-5 py-3 text-right">Nilai</th></tr></thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                @foreach($ingredients as $ing)
                    @php $isLow = (float) $ing->current_stock <= (float) $ing->minimum_stock && (float) $ing->minimum_stock > 0; @endphp
                    <tr>
                        <td class="px-5 py-3"><span class="flex items-center gap-2"><span class="h-2 w-2 shrink-0 rounded-full {{ $isLow ? 'bg-amber-500' : 'bg-[#16A34A]' }}"></span><span class="font-medium">{{ $ing->name }}</span></span></td>
                        <td class="px-3 py-3 text-center text-xs text-[#64748B]">{{ $ing->unit }}</td>
                        <td class="px-3 py-3 text-right font-bold tabular-nums {{ $isLow ? 'text-amber-700' : '' }}">{{ rtrim(rtrim(number_format($ing->current_stock, 2, ',', '.'), '0'), ',') ?: '0' }}</td>
                        <td class="px-5 py-3 text-right tabular-nums text-[#475569]">{{ $rp($ing->current_stock * $ing->average_cost) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @else
            <x-empty title="Belum ada bahan baku" text="Tambahkan bahan baku di menu Bahan Baku agar stoknya terpantau di sini."/>
        @endif
    </x-panel>

    <x-panel flush title="Pergerakan stok" sub="Pada periode yang dipilih">
        @if($movements->count())
        <div class="overflow-x-auto">
            <table class="w-full min-w-[420px] text-sm">
                <thead><tr><th class="px-5 py-3 text-left">Bahan</th><th class="px-3 py-3 text-left">Jenis</th><th class="px-3 py-3 text-right">Jumlah</th><th class="px-5 py-3 text-right">Waktu</th></tr></thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                @foreach($movements as $mv)
                    <tr>
                        <td class="px-5 py-3 font-medium">{{ $mv->ingredient->name }}</td>
                        <td class="px-3 py-3"><x-pill :tone="$mvTone[$mv->type][0] ?? 'gray'">{{ $mvTone[$mv->type][1] ?? ucfirst($mv->type) }}</x-pill></td>
                        <td class="px-3 py-3 text-right font-bold tabular-nums {{ $mv->qty >= 0 ? 'text-[#16A34A]' : 'text-red-600' }}">{{ $mv->qty >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($mv->qty, 3, ',', '.'), '0'), ',') ?: '0' }}</td>
                        <td class="px-5 py-3 text-right text-xs whitespace-nowrap text-[#64748B]">{{ $mv->created_at->format('d M, H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($movements->hasPages())<div class="border-t border-[#E5E7EB] px-5 py-3">{{ $movements->links() }}</div>@endif
        @else
            <x-empty title="Tidak ada pergerakan stok" text="Belum ada stok masuk, keluar, atau penyesuaian pada periode ini."/>
        @endif
    </x-panel>
</div>
</div>
@endsection
