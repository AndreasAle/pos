@extends('layouts.app')
@section('title','Bahan Baku')
@section('page-title','Bahan Baku / Inventory')

@section('content')
@php
    $rp      = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $lowStocks = $ingredients->filter(fn($i) => (float) $i->current_stock <= (float) $i->minimum_stock && (float) $i->minimum_stock > 0);
@endphp

<div class="page text-[#0B1B3F] pt-2">

    <div class="fade-up flex flex-wrap items-center justify-between gap-3 mb-4" style="--i:0">
        @include('inventory._tabs', ['active' => 'ingredients'])
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-[#16A34A]"></span>{{ number_format($ingredients->total()) }} bahan
            </span>
            <x-btn :href="route('ingredients.create')">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Bahan
            </x-btn>
        </div>
    </div>

    @if($lowStocks->count())
    <div class="fade-up mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4" style="--i:1">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
        </span>
        <div class="min-w-0">
            <p class="text-sm font-bold text-amber-900">{{ $lowStocks->count() }} bahan stoknya menipis</p>
            <p class="mt-0.5 text-sm text-amber-800">{{ $lowStocks->pluck('name')->join(', ') }}</p>
        </div>
    </div>
    @endif

    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:2">
        @if($ingredients->count())
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Bahan</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Satuan</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Stok</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Stok Min</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Harga Rata</th>
                        <th class="px-6 py-3.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                    @foreach($ingredients as $ing)
                    @php $isLow = (float) $ing->current_stock <= (float) $ing->minimum_stock && (float) $ing->minimum_stock > 0; @endphp
                    <tr class="group transition-colors cursor-pointer {{ $isLow ? 'bg-amber-50/50 hover:bg-amber-50' : 'hover:bg-[#ECFDF3]/50' }}" onclick="window.location='{{ route('ingredients.show', $ing) }}'">
                        <td class="px-6 py-4">
                            <p class="font-semibold">{{ $ing->name }}</p>
                            @if($ing->sku)<p class="mt-0.5 font-mono text-xs text-[#64748B]">{{ $ing->sku }}</p>@endif
                        </td>
                        <td class="px-6 py-4 text-slate-700">{{ $ing->unit }}</td>
                        <td class="px-6 py-4 text-right">
                            <span class="inline-flex items-center gap-1.5 font-bold tabular-nums {{ $isLow ? 'text-red-600' : '' }}">
                                @if($isLow)<span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>@endif
                                {{ number_format($ing->current_stock, 2) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right tabular-nums text-[#64748B]">{{ number_format($ing->minimum_stock, 2) }}</td>
                        <td class="px-6 py-4 text-right tabular-nums text-slate-700">{{ $rp($ing->average_cost) }}</td>
                        <td class="px-6 py-4" onclick="event.stopPropagation()">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-btn :href="route('ingredients.show', $ing)" size="sm" variant="soft">Detail</x-btn>
                                <x-btn :href="route('ingredients.edit', $ing)" size="sm" variant="secondary">Edit</x-btn>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <ul class="md:hidden divide-y divide-[#E5E7EB]">
            @foreach($ingredients as $ing)
            @php $isLow = (float) $ing->current_stock <= (float) $ing->minimum_stock && (float) $ing->minimum_stock > 0; @endphp
            <li>
                <a href="{{ route('ingredients.show', $ing) }}" class="flex items-center justify-between gap-3 px-5 py-4 active:bg-[#ECFDF3]/60 {{ $isLow ? 'bg-amber-50/50' : '' }}">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $ing->name }}</p>
                        <p class="mt-0.5 text-xs text-[#64748B]">min. {{ number_format($ing->minimum_stock, 2) }} {{ $ing->unit }} &middot; {{ $rp($ing->average_cost) }}</p>
                    </div>
                    <p class="shrink-0 text-right font-bold tabular-nums {{ $isLow ? 'text-red-600' : '' }}">{{ number_format($ing->current_stock, 2) }} <span class="text-xs font-medium text-[#64748B]">{{ $ing->unit }}</span></p>
                </a>
            </li>
            @endforeach
        </ul>
        @else
        <x-empty title="Belum ada bahan baku" text="Catat bahan baku untuk memantau stok dan menghitung modal per produk." icon="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z">
            <x-btn :href="route('ingredients.create')">Tambah bahan</x-btn>
        </x-empty>
        @endif
    </section>

    <div class="mt-5">{{ $ingredients->links() }}</div>
</div>
@endsection
