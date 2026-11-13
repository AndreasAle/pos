@extends('layouts.app')
@section('title', $product->name)
@section('page-title', $product->name)

@section('content')
@php
    $rp     = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $margin = $product->price > 0 ? (($product->price - $product->cost_price) / $product->price) * 100 : 0;
    $label  = 'text-xs font-semibold uppercase tracking-wider text-[#64748B]';
    $trash  = 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16';
@endphp

<div class="page text-[#0B1B3F] pt-2">

    <x-page-head :title="$product->name" :back="route('products.index')" backLabel="Produk / Menu" style="--i:0">
        <x-slot:badge><x-pill :tone="$product->is_active ? 'green' : 'gray'">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill></x-slot:badge>
        <x-slot:actions>
            <x-btn :href="route('products.edit', $product)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15H9v-2.8l8.6-8.6z"/></svg>
                Edit Produk
            </x-btn>
            <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Hapus produk ini?')">
                @csrf @method('DELETE')
                <x-btn type="submit" variant="danger-outline">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $trash }}"/></svg>
                    Hapus
                </x-btn>
            </form>
        </x-slot:actions>
    </x-page-head>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        <div class="lg:col-span-2 space-y-4 min-w-0">

            {{-- Ringkasan --}}
            <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white p-5 sm:p-6" style="--i:1">
                <div class="flex flex-col sm:flex-row gap-5">
                    <div class="h-32 w-32 shrink-0 overflow-hidden rounded-xl bg-slate-100 border border-[#E5E7EB]">
                        @if($product->image)
                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                        @else
                        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-[#ECFDF3] to-slate-100">
                            <svg class="h-10 w-10 text-[#16A34A]/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                        </div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($product->category)<x-pill tone="blue">{{ $product->category->name }}</x-pill>@endif
                            @if($product->sku)<span class="font-mono text-xs text-[#64748B]">SKU: {{ $product->sku }}</span>@endif
                        </div>
                        @if($product->description)<p class="mt-3 text-sm leading-relaxed text-[#64748B]">{{ $product->description }}</p>@endif
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-lg border border-[#BBF7D0] bg-[#ECFDF3] p-3.5">
                                <p class="text-xs font-semibold uppercase tracking-wider text-[#15803D]">Harga Jual</p>
                                <p class="mt-1 text-xl font-extrabold tabular-nums">{{ $rp($product->price) }}</p>
                            </div>
                            <div class="rounded-lg border border-[#E5E7EB] bg-slate-50 p-3.5">
                                <p class="{{ $label }}">Harga Modal</p>
                                <p class="mt-1 text-xl font-extrabold tabular-nums">{{ $rp($product->cost_price) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Varian --}}
            <x-panel title="Varian Produk" sub="Pilihan ukuran atau tingkat, dengan selisih harga." class="fade-up" style="--i:2">
                @if($product->variants->count())
                <ul class="mb-5 divide-y divide-[#E5E7EB] rounded-lg border border-[#E5E7EB]">
                    @foreach($product->variants as $variant)
                    <li class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ $variant->name }}</p>
                            <p class="mt-0.5 text-xs text-[#64748B]">
                                {{ $rp($product->price + $variant->price_adjustment) }}
                                @if($variant->price_adjustment != 0)
                                <span class="font-semibold {{ $variant->price_adjustment > 0 ? 'text-amber-600' : 'text-[#15803D]' }}">({{ $variant->price_adjustment > 0 ? '+' : '-' }}{{ $rp(abs($variant->price_adjustment)) }})</span>
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('products.variants.destroy', [$product, $variant]) }}" onsubmit="return confirm('Hapus varian ini?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg text-[#64748B] transition-colors hover:bg-red-50 hover:text-red-600" title="Hapus varian" aria-label="Hapus varian">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $trash }}"/></svg>
                            </button>
                        </form>
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="mb-5 rounded-lg border border-dashed border-[#E5E7EB] bg-slate-50 px-4 py-5 text-center text-sm text-[#64748B]">Belum ada varian.</p>
                @endif
                <form method="POST" action="{{ route('products.variants.store', $product) }}" class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_160px_auto] items-end gap-3">
                    @csrf
                    <x-field label="Nama varian">
                        <x-input name="name" placeholder="Contoh: Ukuran L, Pedas Banget" required class="h-10" />
                    </x-field>
                    <x-field label="Selisih harga">
                        <x-input name="price_adjustment" type="number" prefix="Rp" value="0" step="1" class="h-10" />
                    </x-field>
                    <x-btn type="submit"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>Tambah</x-btn>
                </form>
            </x-panel>

            {{-- Add-on --}}
            <x-panel title="Add-on / Topping" sub="Tambahan berbayar yang bisa dipilih kasir." class="fade-up" style="--i:3">
                @if($product->addons->count())
                <ul class="mb-5 divide-y divide-[#E5E7EB] rounded-lg border border-[#E5E7EB]">
                    @foreach($product->addons as $addon)
                    <li class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ $addon->name }}</p>
                            <p class="mt-0.5 text-xs font-semibold text-[#15803D]">+ {{ $rp($addon->price) }}</p>
                        </div>
                        <form method="POST" action="{{ route('products.addons.destroy', [$product, $addon]) }}" onsubmit="return confirm('Hapus add-on ini?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg text-[#64748B] transition-colors hover:bg-red-50 hover:text-red-600" title="Hapus add-on" aria-label="Hapus add-on">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $trash }}"/></svg>
                            </button>
                        </form>
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="mb-5 rounded-lg border border-dashed border-[#E5E7EB] bg-slate-50 px-4 py-5 text-center text-sm text-[#64748B]">Belum ada add-on.</p>
                @endif
                <form method="POST" action="{{ route('products.addons.store', $product) }}" class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_160px_auto] items-end gap-3">
                    @csrf
                    <x-field label="Nama add-on">
                        <x-input name="name" placeholder="Contoh: Extra Keju, Tambah Es" required class="h-10" />
                    </x-field>
                    <x-field label="Harga">
                        <x-input name="price" type="number" prefix="Rp" value="0" min="0" step="1" class="h-10" />
                    </x-field>
                    <x-btn type="submit"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>Tambah</x-btn>
                </form>
            </x-panel>
        </div>

        {{-- Kanan --}}
        <div class="space-y-4 lg:sticky lg:top-20">
            <x-panel title="Info Tambahan" class="fade-up" style="--i:2">
                <dl class="space-y-3.5 text-sm">
                    <div class="flex items-center justify-between"><dt class="text-[#64748B]">Margin</dt><dd class="font-bold tabular-nums {{ $margin >= 0 ? 'text-[#15803D]' : 'text-red-600' }}">{{ number_format($margin, 1) }}%</dd></div>
                    <div class="flex items-center justify-between"><dt class="text-[#64748B]">Pantau stok</dt><dd><x-pill :tone="$product->is_stock_tracked ? 'green' : 'gray'">{{ $product->is_stock_tracked ? 'Ya' : 'Tidak' }}</x-pill></dd></div>
                    <div class="flex items-center justify-between"><dt class="text-[#64748B]">Varian</dt><dd class="font-bold tabular-nums">{{ $product->variants->count() }}</dd></div>
                    <div class="flex items-center justify-between"><dt class="text-[#64748B]">Add-on</dt><dd class="font-bold tabular-nums">{{ $product->addons->count() }}</dd></div>
                </dl>
            </x-panel>

            <x-panel title="Resep" class="fade-up" style="--i:3">
                @if($product->recipe)
                <p class="{{ $label }} mb-3">Bahan yang digunakan</p>
                <ul class="divide-y divide-[#E5E7EB] rounded-lg border border-[#E5E7EB]">
                    @foreach($product->recipe->items as $item)
                    <li class="flex items-center justify-between gap-3 px-3.5 py-2.5 text-sm">
                        <span class="truncate font-medium">{{ $item->ingredient->name }}</span>
                        <span class="shrink-0 tabular-nums text-[#64748B]">{{ number_format($item->qty, 2) }} {{ $item->ingredient->unit }}</span>
                    </li>
                    @endforeach
                </ul>
                <x-btn :href="route('recipes.edit', $product)" variant="secondary" size="sm" class="mt-4">Edit Resep</x-btn>
                @else
                <x-empty title="Belum ada resep" text="Tambahkan bahan agar stok berkurang otomatis." class="py-6 px-0">
                    <x-btn :href="route('recipes.edit', $product)" size="sm">Tambah Resep</x-btn>
                </x-empty>
                @endif
            </x-panel>
        </div>
    </div>
</div>
@endsection
