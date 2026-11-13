@extends('layouts.app')
@section('title','Resep Produk')
@section('page-title','Resep Produk')

@section('content')
@php $rp = fn($v) => 'Rp ' . number_format($v, 0, ',', '.'); @endphp

<div class="page text-[#0B1B3F] pt-2">

    <div class="fade-up flex flex-wrap items-center justify-between gap-3 mb-2" style="--i:0">
        @include('inventory._tabs', ['active' => 'recipes'])
        <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
            <span class="w-1.5 h-1.5 rounded-full bg-[#16A34A]"></span>{{ number_format($products->total()) }} produk
        </span>
    </div>
    <p class="fade-up mb-4 text-sm text-[#64748B]" style="--i:0">Produk dengan pantau stok aktif. Atur bahan yang dipakai agar stok berkurang otomatis saat terjual.</p>

    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:1">
        @if($products->count())
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Produk</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Bahan Resep</th>
                        <th class="px-6 py-3.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                    @foreach($products as $product)
                    <tr class="hover:bg-[#ECFDF3]/50 transition-colors">
                        <td class="px-6 py-4 align-top">
                            <p class="font-semibold">{{ $product->name }}</p>
                            <p class="mt-0.5 text-xs tabular-nums text-[#64748B]">{{ $rp($product->price) }}</p>
                        </td>
                        <td class="px-6 py-4">
                            @if($product->recipe && $product->recipe->items->count())
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($product->recipe->items as $item)
                                <span class="inline-flex items-center rounded-full border border-[#BBF7D0] bg-[#ECFDF3] px-2.5 py-0.5 text-xs font-medium text-[#15803D]">
                                    {{ $item->ingredient->name }} <span class="ml-1 text-[#64748B]">{{ rtrim(rtrim(number_format($item->qty, 3, '.', ''), '0'), '.') }} {{ $item->ingredient->unit }}</span>
                                </span>
                                @endforeach
                            </div>
                            @else
                            <span class="text-xs italic text-[#64748B]">Belum ada resep</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right align-top">
                            <x-btn :href="route('recipes.edit', $product)" size="sm" :variant="($product->recipe && $product->recipe->items->count()) ? 'secondary' : 'primary'">
                                {{ ($product->recipe && $product->recipe->items->count()) ? 'Edit Resep' : 'Buat Resep' }}
                            </x-btn>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <ul class="md:hidden divide-y divide-[#E5E7EB]">
            @foreach($products as $product)
            <li class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $product->name }}</p>
                        <p class="mt-0.5 text-xs text-[#64748B]">{{ $rp($product->price) }}</p>
                    </div>
                    <x-btn :href="route('recipes.edit', $product)" size="sm" variant="secondary">Resep</x-btn>
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @if($product->recipe && $product->recipe->items->count())
                        @foreach($product->recipe->items as $item)
                        <span class="inline-flex rounded-full border border-[#BBF7D0] bg-[#ECFDF3] px-2.5 py-0.5 text-xs font-medium text-[#15803D]">{{ $item->ingredient->name }}</span>
                        @endforeach
                    @else
                        <span class="text-xs italic text-[#64748B]">Belum ada resep</span>
                    @endif
                </div>
            </li>
            @endforeach
        </ul>
        @else
        <x-empty title="Belum ada produk yang dipantau stoknya" text="Aktifkan Pantau Stok di halaman produk, lalu atur resepnya di sini."
                 icon="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
            <x-btn :href="route('products.index')">Buka halaman produk</x-btn>
        </x-empty>
        @endif
    </section>

    <div class="mt-5">{{ $products->links() }}</div>
</div>
@endsection
