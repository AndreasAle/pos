@extends('layouts.app')
@section('title', $ingredient->name)
@section('page-title', $ingredient->name)

@section('content')
@php
    $rp    = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $low   = $ingredient->isLowStock();
    $u     = $ingredient->unit;
    $plus  = 'M12 4v16m8-8H4';
@endphp

<div class="page text-[#0B1B3F] pt-2">

    <x-page-head :title="$ingredient->name" :back="route('ingredients.index')" backLabel="Bahan Baku" style="--i:0">
        <x-slot:badge>
            <x-pill :tone="$ingredient->is_active ? 'green' : 'gray'">{{ $ingredient->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill>
            @if($low)<x-pill tone="amber">Stok menipis</x-pill>@endif
        </x-slot:badge>
        <x-slot:actions>
            <x-btn :href="route('ingredients.edit', $ingredient)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15H9v-2.8l8.6-8.6z"/></svg>
                Edit
            </x-btn>
        </x-slot:actions>
    </x-page-head>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-4">
        <x-kpi class="fade-up" style="--i:1" :tone="$low ? 'warn' : 'brand'" label="Stok Saat Ini" :value="number_format($ingredient->current_stock, 2) . ' ' . $u" />
        <x-kpi class="fade-up" style="--i:2" label="Stok Minimum" :value="number_format($ingredient->minimum_stock, 2) . ' ' . $u" />
        <x-kpi class="fade-up" style="--i:3" label="Harga Rata-rata" :value="$rp($ingredient->average_cost) . ' / ' . $u" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[360px_minmax(0,1fr)] gap-4 items-start">

        {{-- Kiri: aksi stok --}}
        <div class="space-y-4">
            <x-panel title="Stok Masuk" sub="Catat pembelian atau penambahan stok." class="fade-up" style="--i:2">
                <form method="POST" action="{{ route('ingredients.stock-in', $ingredient) }}" class="space-y-4">
                    @csrf
                    <x-field :label="'Jumlah (' . $u . ')'" name="qty">
                        <x-input name="qty" type="number" min="0.001" step="0.001" required placeholder="0" class="h-10" />
                    </x-field>
                    <x-field :label="'Harga per ' . $u" name="unit_cost">
                        <x-input name="unit_cost" type="number" prefix="Rp" min="0" step="any" :value="$ingredient->average_cost" class="h-10" />
                    </x-field>
                    <x-field label="Catatan" name="notes">
                        <x-input name="notes" placeholder="Supplier, referensi..." class="h-10" />
                    </x-field>
                    <x-btn type="submit" class="w-full">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $plus }}"/></svg>
                        Tambah Stok
                    </x-btn>
                </form>
            </x-panel>

            <x-panel title="Penyesuaian Stok" sub="Samakan stok sistem dengan hasil hitung fisik." class="fade-up" style="--i:3">
                <form method="POST" action="{{ route('ingredients.adjustment', $ingredient) }}" class="space-y-4">
                    @csrf
                    <x-field label="Stok aktual baru" name="new_stock">
                        <x-input name="new_stock" type="number" min="0" step="0.001" :value="$ingredient->current_stock" class="h-10" />
                    </x-field>
                    <x-field label="Alasan" name="notes">
                        <x-input name="notes" placeholder="Stok opname, koreksi..." class="h-10" />
                    </x-field>
                    <x-btn type="submit" variant="secondary" class="w-full">Sesuaikan Stok</x-btn>
                </form>
            </x-panel>
        </div>

        {{-- Kanan: riwayat --}}
        <div class="min-w-0">
            <section class="fade-up overflow-hidden rounded-xl border border-[#E5E7EB] bg-white" style="--i:3">
                <div class="flex items-center justify-between gap-3 border-b border-[#E5E7EB] px-5 py-4">
                    <h2 class="text-base font-semibold">Riwayat Pergerakan Stok</h2>
                    <x-pill tone="green">{{ number_format($movements->total()) }} catatan</x-pill>
                </div>

                @if($movements->count())
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm whitespace-nowrap">
                        <thead>
                            <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                                <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Tipe</th>
                                <th class="text-right px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Qty</th>
                                <th class="text-right px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Sebelum</th>
                                <th class="text-right px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Sesudah</th>
                                <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Catatan</th>
                                <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E7EB]">
                            @foreach($movements as $mv)
                            <tr class="hover:bg-[#ECFDF3]/50 transition-colors">
                                <td class="px-6 py-4"><x-movement-type :type="$mv->type" /></td>
                                <td class="px-4 py-4 text-right font-bold tabular-nums {{ $mv->qty >= 0 ? 'text-[#15803D]' : 'text-red-600' }}">{{ $mv->qty >= 0 ? '+' : '' }}{{ number_format($mv->qty, 3) }}</td>
                                <td class="px-4 py-4 text-right tabular-nums text-[#64748B]">{{ number_format($mv->stock_before, 3) }}</td>
                                <td class="px-4 py-4 text-right font-semibold tabular-nums">{{ number_format($mv->stock_after, 3) }}</td>
                                <td class="max-w-[14rem] truncate px-4 py-4 text-xs text-[#64748B]">{{ $mv->notes ?: '-' }}</td>
                                <td class="px-6 py-4 text-right text-xs text-[#64748B]">{{ $mv->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="md:hidden divide-y divide-[#E5E7EB]">
                    @foreach($movements as $mv)
                    <li class="px-5 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <x-movement-type :type="$mv->type" />
                            <span class="font-bold tabular-nums {{ $mv->qty >= 0 ? 'text-[#15803D]' : 'text-red-600' }}">{{ $mv->qty >= 0 ? '+' : '' }}{{ number_format($mv->qty, 3) }} {{ $u }}</span>
                        </div>
                        <p class="mt-2 text-xs text-[#64748B]">{{ number_format($mv->stock_before, 3) }} &rarr; <span class="font-semibold text-[#0B1B3F]">{{ number_format($mv->stock_after, 3) }}</span> &middot; {{ $mv->created_at->format('d/m/Y H:i') }}</p>
                        @if($mv->notes)<p class="mt-1 text-xs text-[#64748B]">{{ $mv->notes }}</p>@endif
                    </li>
                    @endforeach
                </ul>
                @else
                <x-empty title="Belum ada riwayat" text="Stok masuk, penjualan, dan penyesuaian akan tercatat di sini." icon="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                @endif
            </section>
            <div class="mt-4">{{ $movements->links() }}</div>
        </div>
    </div>
</div>
@endsection
