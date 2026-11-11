@extends('layouts.app')
@section('title','Pergerakan Stok')
@section('page-title','Pergerakan Stok')
@section('content')
<div class="flex items-center gap-3 mb-5">
    <a href="{{ route('ingredients.index') }}" class="text-sm font-medium bg-white text-gray-600 border border-gray-300 hover:border-emerald-400 px-4 py-2 rounded-xl">Bahan Baku</a>
    <a href="{{ route('inventory.movements') }}" class="text-sm font-semibold bg-emerald-600 text-white px-4 py-2 rounded-xl">Pergerakan Stok</a>
    <a href="{{ route('recipes.index') }}" class="text-sm font-medium bg-white text-gray-600 border border-gray-300 hover:border-emerald-400 px-4 py-2 rounded-xl">Resep Produk</a>
</div>

<x-card :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Bahan</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Tipe</th>
                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Qty</th>
                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Sebelum</th>
                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Sesudah</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">User</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Catatan</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Waktu</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($movements as $mv)
            @php $mvColors = ['in'=>'green','out'=>'red','adjustment'=>'blue','sale'=>'orange','return'=>'purple','waste'=>'gray']; @endphp
            <tr class="hover:bg-gray-50/50">
                <td class="px-5 py-2.5 font-medium text-gray-900">{{ $mv->ingredient->name }}</td>
                <td class="px-3 py-2.5"><x-badge :color="$mvColors[$mv->type]??'gray'">{{ ucfirst($mv->type) }}</x-badge></td>
                <td class="px-3 py-2.5 text-right font-bold {{ $mv->qty >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $mv->qty >= 0 ? '+' : '' }}{{ number_format($mv->qty, 3) }}
                </td>
                <td class="px-3 py-2.5 text-right text-gray-500">{{ number_format($mv->stock_before, 3) }}</td>
                <td class="px-3 py-2.5 text-right font-semibold text-gray-900">{{ number_format($mv->stock_after, 3) }}</td>
                <td class="px-3 py-2.5 text-gray-600">{{ $mv->user->name }}</td>
                <td class="px-3 py-2.5 text-gray-500 text-xs max-w-xs truncate">{{ $mv->notes ?: '-' }}</td>
                <td class="px-5 py-2.5 text-right text-gray-500 text-xs">{{ $mv->created_at->format('d/m/Y H:i') }}</td>
            </tr>
            @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400">Belum ada pergerakan stok</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
<div class="mt-4">{{ $movements->links() }}</div>
@endsection
