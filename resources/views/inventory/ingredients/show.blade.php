@extends('layouts.app')
@section('title', $ingredient->name)
@section('page-title', $ingredient->name)

@section('content')
<div class="flex items-center justify-between mb-5">
    <a href="{{ route('ingredients.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Bahan Baku</a>
    <a href="{{ route('ingredients.edit', $ingredient) }}"
       class="text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl">Edit</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
    <x-stat-card label="Stok Saat Ini" value="{{ number_format($ingredient->current_stock,2) }} {{ $ingredient->unit }}"
                 :color="$ingredient->isLowStock() ? 'orange' : 'emerald'"/>
    <x-stat-card label="Stok Minimum" value="{{ number_format($ingredient->minimum_stock,2) }} {{ $ingredient->unit }}" color="blue"/>
    <x-stat-card label="Harga Rata-rata" value="Rp {{ number_format($ingredient->average_cost,0,',','.') }} / {{ $ingredient->unit }}" color="purple"/>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="space-y-4">
        {{-- Stock In --}}
        <x-card title="Stok Masuk">
            <form method="POST" action="{{ route('ingredients.stock-in', $ingredient) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Jumlah ({{ $ingredient->unit }})</label>
                    <input type="number" name="qty" min="0.001" step="0.001" required placeholder="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Harga per {{ $ingredient->unit }}</label>
                    <div class="relative">
                        <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                        <input type="number" name="unit_cost" min="0" step="100" value="{{ $ingredient->average_cost }}"
                               class="pl-7 w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Catatan</label>
                    <input type="text" name="notes" placeholder="Supplier, referensi..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2.5 rounded-xl">
                    + Tambah Stok
                </button>
            </form>
        </x-card>

        {{-- Adjustment --}}
        <x-card title="Penyesuaian Stok">
            <form method="POST" action="{{ route('ingredients.adjustment', $ingredient) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Stok Aktual Baru</label>
                    <input type="number" name="new_stock" min="0" step="0.001" value="{{ $ingredient->current_stock }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Alasan</label>
                    <input type="text" name="notes" placeholder="Stok opname, koreksi..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold py-2.5 rounded-xl">
                    Sesuaikan Stok
                </button>
            </form>
        </x-card>
    </div>

    {{-- Movement history --}}
    <div class="lg:col-span-2">
        <x-card title="Riwayat Pergerakan Stok" :padding="false">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase">Tipe</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase">Qty</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase">Stok Sebelum</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase">Stok Sesudah</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase">Catatan</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($movements as $mv)
                    @php
                    $mvColors = ['in'=>'green','out'=>'red','adjustment'=>'blue','sale'=>'orange','return'=>'purple','waste'=>'gray'];
                    @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-2.5">
                            <x-badge :color="$mvColors[$mv->type]??'gray'">{{ ucfirst($mv->type) }}</x-badge>
                        </td>
                        <td class="px-4 py-2.5 text-right font-bold {{ $mv->qty >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $mv->qty >= 0 ? '+' : '' }}{{ number_format($mv->qty, 3) }}
                        </td>
                        <td class="px-4 py-2.5 text-right text-gray-500">{{ number_format($mv->stock_before, 3) }}</td>
                        <td class="px-4 py-2.5 text-right font-semibold text-gray-900">{{ number_format($mv->stock_after, 3) }}</td>
                        <td class="px-4 py-2.5 text-gray-600 text-xs max-w-xs truncate">{{ $mv->notes ?: '-' }}</td>
                        <td class="px-4 py-2.5 text-right text-gray-500 text-xs">{{ $mv->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">Belum ada riwayat</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
        <div class="mt-3">{{ $movements->links() }}</div>
    </div>
</div>
@endsection
