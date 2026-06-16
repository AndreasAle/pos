@extends('layouts.app')
@section('title','Bahan Baku')
@section('page-title','Bahan Baku / Inventory')

@section('content')
<div class="flex items-center justify-between mb-5">
    <div class="flex gap-2">
        <a href="{{ route('ingredients.index') }}" class="text-sm font-semibold bg-emerald-600 text-white px-4 py-2 rounded-xl">Bahan Baku</a>
        <a href="{{ route('inventory.movements') }}" class="text-sm font-medium bg-white text-gray-600 border border-gray-300 hover:border-emerald-400 px-4 py-2 rounded-xl">Pergerakan Stok</a>
        <a href="{{ route('recipes.index') }}" class="text-sm font-medium bg-white text-gray-600 border border-gray-300 hover:border-emerald-400 px-4 py-2 rounded-xl">Resep Produk</a>
    </div>
    <a href="{{ route('ingredients.create') }}"
       class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Bahan
    </a>
</div>

@php $lowStocks = $ingredients->filter(fn($i) => (float)$i->current_stock <= (float)$i->minimum_stock && (float)$i->minimum_stock > 0); @endphp
@if($lowStocks->count())
<div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 mb-4 flex items-start gap-3">
    <svg class="w-5 h-5 text-orange-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 4a8 8 0 100 16A8 8 0 0012 4z"/>
    </svg>
    <div>
        <p class="text-sm font-semibold text-orange-800">{{ $lowStocks->count() }} bahan stok menipis!</p>
        <p class="text-sm text-orange-700 mt-0.5">{{ $lowStocks->pluck('name')->join(', ') }}</p>
    </div>
</div>
@endif

<x-card :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Nama Bahan</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Satuan</th>
                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Stok</th>
                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Stok Min</th>
                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Harga Rata</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($ingredients as $ing)
            @php $isLow = (float)$ing->current_stock <= (float)$ing->minimum_stock && (float)$ing->minimum_stock > 0; @endphp
            <tr class="{{ $isLow ? 'bg-orange-50/40' : 'hover:bg-gray-50/50' }} transition-colors">
                <td class="px-5 py-3">
                    <div class="flex items-center gap-2">
                        @if($isLow)<span class="w-1.5 h-1.5 rounded-full bg-orange-500 flex-shrink-0"></span>@endif
                        <div>
                            <p class="font-semibold text-gray-900">{{ $ing->name }}</p>
                            @if($ing->sku)<p class="text-xs text-gray-400 font-mono">{{ $ing->sku }}</p>@endif
                        </div>
                    </div>
                </td>
                <td class="px-3 py-3 text-gray-600">{{ $ing->unit }}</td>
                <td class="px-3 py-3 text-right">
                    <span class="font-bold {{ $isLow ? 'text-red-600' : 'text-gray-900' }}">
                        {{ number_format($ing->current_stock, 2) }}
                    </span>
                </td>
                <td class="px-3 py-3 text-right text-gray-500">{{ number_format($ing->minimum_stock, 2) }}</td>
                <td class="px-3 py-3 text-right text-gray-600">Rp {{ number_format($ing->average_cost, 0, ',', '.') }}</td>
                <td class="px-5 py-3 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                        <a href="{{ route('ingredients.show', $ing) }}"
                           class="text-xs font-medium text-emerald-600 hover:text-emerald-700 px-2.5 py-1.5 rounded-lg hover:bg-emerald-50 transition-colors">
                            Detail
                        </a>
                        <a href="{{ route('ingredients.edit', $ing) }}"
                           class="text-xs font-medium text-gray-500 hover:text-gray-700 px-2.5 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                            Edit
                        </a>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400">
                Belum ada bahan baku.
                <a href="{{ route('ingredients.create') }}" class="text-emerald-600 hover:underline">Tambah sekarang</a>
            </td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
<div class="mt-4">{{ $ingredients->links() }}</div>
@endsection
