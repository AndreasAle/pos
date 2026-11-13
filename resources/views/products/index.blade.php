@extends('layouts.app')
@section('title', 'Produk / Menu')
@section('page-title', 'Produk / Menu')

@section('content')
@php
    $rp = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $hasFilter = request()->filled('search') || request()->filled('category_id') || request('status') !== null && request('status') !== '';
@endphp

<div class="page text-[#0B1B3F] pt-2">

    {{-- Sub judul + jumlah --}}
    <div class="fade-up flex flex-wrap items-end justify-between gap-2 mb-4" style="--i:0">
        <p class="text-sm text-[#64748B]">Kelola menu, harga, dan status produk yang tampil di kasir.</p>
        <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
            <span class="w-1.5 h-1.5 rounded-full bg-[#16A34A]"></span>{{ number_format($products->total()) }} produk
        </span>
    </div>

    {{-- Toolbar --}}
    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white p-3 sm:p-4 mb-4" style="--i:1">
        <div class="flex flex-col xl:flex-row xl:items-center gap-3">
            <form method="GET" class="grid grid-cols-2 sm:flex sm:flex-1 sm:items-center gap-2 min-w-0">
                <div class="relative col-span-2 sm:flex-1 sm:max-w-sm">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari produk atau SKU..."
                           class="h-10 w-full rounded-lg border border-[#E5E7EB] bg-white pl-10 pr-3 text-sm placeholder:text-slate-400 focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30 transition">
                </div>
                <x-select name="category_id" :compact="true" onchange="this.form.submit()" class="sm:w-48">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </x-select>
                <x-select name="status" :compact="true" onchange="this.form.submit()" class="sm:w-40">
                    <option value="">Semua Status</option>
                    <option value="1" @selected(request('status') === '1')>Aktif</option>
                    <option value="0" @selected(request('status') === '0')>Nonaktif</option>
                </x-select>
                <x-btn type="submit" variant="dark" class="col-span-2 sm:col-span-1">Cari</x-btn>
                @if($hasFilter)
                <a href="{{ route('products.index') }}" class="h-10 inline-flex items-center justify-center gap-1.5 px-3 rounded-lg text-sm font-semibold text-[#64748B] hover:bg-slate-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>Reset
                </a>
                @endif
            </form>

            <div class="grid grid-cols-2 sm:flex gap-2 xl:ml-auto">
                <x-btn :href="route('barcodes.select')" variant="secondary">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    Label Barcode
                </x-btn>
                <x-btn :href="route('products.create')">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah Produk
                </x-btn>
            </div>
        </div>
    </section>

    {{-- Grid produk --}}
    @if($products->count())
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6 gap-3 sm:gap-4">
        @foreach($products as $product)
        <article class="fade-up group overflow-hidden rounded-xl border border-[#E5E7EB] bg-white transition-all hover:-translate-y-0.5 hover:border-[#16A34A]/50 hover:shadow-md" style="--i:{{ min($loop->index, 8) + 2 }}">
            <a href="{{ route('products.show', $product) }}" class="relative block aspect-square overflow-hidden bg-slate-100">
                @if($product->image)
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy"
                     class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105 {{ $product->is_active ? '' : 'grayscale opacity-70' }}">
                @else
                <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-[#ECFDF3] to-slate-100">
                    <svg class="h-10 w-10 text-[#16A34A]/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
                </div>
                @endif
                @unless($product->is_active)
                <span class="absolute left-2 top-2 rounded-full bg-[#0B1B3F]/80 px-2 py-0.5 text-[11px] font-semibold text-white">Nonaktif</span>
                @endunless
            </a>
            <div class="p-3.5">
                <p class="truncate text-sm font-semibold leading-tight">{{ $product->name }}</p>
                <p class="mt-0.5 h-4 truncate text-xs text-[#64748B]">{{ $product->category->name ?? 'Tanpa kategori' }}</p>
                <p class="mt-1.5 text-base font-bold tabular-nums text-[#15803D]">{{ $rp($product->price) }}</p>
                <div class="mt-3 flex items-center gap-1.5">
                    <a href="{{ route('products.show', $product) }}" class="flex h-9 flex-1 items-center justify-center rounded-lg border border-[#BBF7D0] bg-[#ECFDF3] text-[13px] font-semibold text-[#15803D] transition-colors hover:bg-[#DDF8E8]">Detail</a>
                    <form method="POST" action="{{ route('products.toggle', $product) }}">
                        @csrf @method('PATCH')
                        <button type="submit" title="{{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" aria-label="{{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                class="flex h-9 w-9 items-center justify-center rounded-lg border transition-colors {{ $product->is_active ? 'border-[#E5E7EB] text-[#64748B] hover:border-amber-300 hover:bg-amber-50 hover:text-amber-700' : 'border-[#BBF7D0] bg-[#ECFDF3] text-[#15803D] hover:bg-[#DDF8E8]' }}">
                            @if($product->is_active)
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M3 3l18 18"/></svg>
                            @else
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            @endif
                        </button>
                    </form>
                </div>
            </div>
        </article>
        @endforeach
    </div>
    <div class="mt-5">{{ $products->links() }}</div>
    @else
    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white" style="--i:2">
        <x-empty :title="$hasFilter ? 'Produk tidak ditemukan' : 'Belum ada produk'"
                 :text="$hasFilter ? 'Coba ubah kata kunci atau filter pencarian.' : 'Tambahkan menu pertama supaya bisa dijual lewat kasir.'"
                 icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10">
            @if($hasFilter)
                <x-btn :href="route('products.index')" variant="secondary">Reset filter</x-btn>
            @else
                <x-btn :href="route('products.create')">Tambah produk pertama</x-btn>
            @endif
        </x-empty>
    </section>
    @endif
</div>
@endsection
