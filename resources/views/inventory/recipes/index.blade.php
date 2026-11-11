@extends('layouts.app')
@section('title','Resep Produk')
@section('page-title','Resep Produk')
@section('content')
<div class="flex items-center gap-3 mb-5">
    <a href="{{ route('ingredients.index') }}" class="text-sm font-medium bg-white text-gray-600 border border-gray-300 hover:border-emerald-400 px-4 py-2 rounded-xl">Bahan Baku</a>
    <a href="{{ route('inventory.movements') }}" class="text-sm font-medium bg-white text-gray-600 border border-gray-300 hover:border-emerald-400 px-4 py-2 rounded-xl">Pergerakan Stok</a>
    <a href="{{ route('recipes.index') }}" class="text-sm font-semibold bg-emerald-600 text-white px-4 py-2 rounded-xl">Resep Produk</a>
</div>
<p class="text-sm text-gray-500 mb-4">Produk yang aktif dipantau stoknya. Klik Edit Resep untuk mengatur bahan yang digunakan.</p>

<x-card :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Produk</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Bahan Resep</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($products as $product)
            <tr class="hover:bg-gray-50/50">
                <td class="px-5 py-3">
                    <p class="font-semibold text-gray-900">{{ $product->name }}</p>
                    <p class="text-xs text-gray-400">Rp {{ number_format($product->price,0,',','.') }}</p>
                </td>
                <td class="px-5 py-3">
                    @if($product->recipe && $product->recipe->items->count())
                    <div class="flex flex-wrap gap-1">
                        @foreach($product->recipe->items as $item)
                        <span class="text-xs bg-emerald-50 text-emerald-700 border border-emerald-100 px-2 py-0.5 rounded-full">
                            {{ $item->ingredient->name }} ({{ number_format($item->qty,3) }} {{ $item->ingredient->unit }})
                        </span>
                        @endforeach
                    </div>
                    @else
                    <span class="text-xs text-gray-400 italic">Belum ada resep</span>
                    @endif
                </td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('recipes.edit', $product) }}"
                       class="text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-3 py-1.5 rounded-xl transition-colors">
                        Edit Resep
                    </a>
                </td>
            </tr>
            @empty
            <tr><td colspan="3" class="px-5 py-10 text-center text-sm text-gray-400">
                Belum ada produk yang dipantau stoknya.
                <a href="{{ route('products.index') }}" class="text-emerald-600 hover:underline">Aktifkan Pantau Stok di halaman Produk</a>
            </td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
