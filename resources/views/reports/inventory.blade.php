@extends('layouts.app')
@section('title','Laporan Inventory')
@section('page-title','Laporan Inventory')
@section('content')
@include('reports._nav')
@include('reports._filter', ['f' => $f])

{{-- Summary stats --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
    <x-stat-card label="Total Bahan Baku" value="{{ count($ingredients) }}" color="blue"/>
    <x-stat-card label="Stok Menipis" value="{{ count($lowStock) }}" color="orange"/>
    <x-stat-card label="Nilai Inventory" color="emerald"
                 value="Rp {{ number_format($ingredients->sum(fn($i) => $i->current_stock * $i->average_cost),0,',','.') }}"/>
</div>

@if(count($lowStock))
<div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 mb-5">
    <p class="text-sm font-semibold text-orange-800 mb-2">⚠ Bahan Stok Menipis / Habis</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
        @foreach($lowStock as $ing)
        <div class="flex items-center justify-between bg-white rounded-xl border border-orange-200 px-3 py-2">
            <span class="text-sm font-medium text-gray-900">{{ $ing->name }}</span>
            <span class="text-xs font-bold text-red-600">{{ number_format($ing->current_stock,2) }} {{ $ing->unit }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    {{-- Stock list --}}
    <x-card title="Stok Saat Ini" :padding="false">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100">
                    <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase">Bahan</th>
                    <th class="text-center px-3 py-2.5 text-xs font-semibold text-gray-500 uppercase">Satuan</th>
                    <th class="text-right px-3 py-2.5 text-xs font-semibold text-gray-500 uppercase">Stok</th>
                    <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase">Nilai</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($ingredients as $ing)
                @php $isLow = (float)$ing->current_stock <= (float)$ing->minimum_stock && (float)$ing->minimum_stock > 0; @endphp
                <tr class="{{ $isLow ? 'bg-orange-50/40' : 'hover:bg-gray-50/50' }}">
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-1.5">
                            @if($isLow)<span class="w-1.5 h-1.5 rounded-full bg-orange-500 flex-shrink-0"></span>@endif
                            <span class="font-medium text-gray-900">{{ $ing->name }}</span>
                        </div>
                    </td>
                    <td class="px-3 py-2.5 text-center text-gray-500 text-xs">{{ $ing->unit }}</td>
                    <td class="px-3 py-2.5 text-right font-bold {{ $isLow ? 'text-red-600' : 'text-gray-900' }}">
                        {{ number_format($ing->current_stock,2) }}
                    </td>
                    <td class="px-4 py-2.5 text-right text-gray-600">
                        Rp {{ number_format($ing->current_stock * $ing->average_cost,0,',','.') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>

    {{-- Movement history --}}
    <x-card title="Pergerakan Stok Periode Ini" :padding="false">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100">
                    <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase">Bahan</th>
                    <th class="text-left px-3 py-2.5 text-xs font-semibold text-gray-500 uppercase">Tipe</th>
                    <th class="text-right px-3 py-2.5 text-xs font-semibold text-gray-500 uppercase">Qty</th>
                    <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase">Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @php $mvColors = ['in'=>'green','out'=>'red','adjustment'=>'blue','sale'=>'orange','return'=>'purple','waste'=>'gray']; @endphp
                @forelse($movements as $mv)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-2.5 font-medium text-gray-900 text-xs">{{ $mv->ingredient->name }}</td>
                    <td class="px-3 py-2.5"><x-badge :color="$mvColors[$mv->type]??'gray'">{{ ucfirst($mv->type) }}</x-badge></td>
                    <td class="px-3 py-2.5 text-right font-bold text-xs {{ $mv->qty >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                        {{ $mv->qty >= 0 ? '+' : '' }}{{ number_format($mv->qty,3) }}
                    </td>
                    <td class="px-4 py-2.5 text-right text-gray-500 text-xs">{{ $mv->created_at->format('d/m H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">Tidak ada pergerakan</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($movements->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $movements->links() }}</div>
        @endif
    </x-card>
</div>
@endsection
