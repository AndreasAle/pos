@extends('layouts.app')
@section('title', 'Bundling Produk')
@section('page-title', 'Bundling Produk')

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500">Kelola paket bundling produk yang tampil di kasir POS.</p>
    <a href="{{ route('bundles.create') }}"
       class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
        </svg>
        Tambah Bundle
    </a>
</div>

@if($bundles->isEmpty())
<div class="bg-white rounded-2xl border border-gray-200 p-16 text-center">
    <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
        </svg>
    </div>
    <p class="text-gray-500 font-medium">Belum ada bundle</p>
    <p class="text-sm text-gray-400 mt-1">Buat paket bundling produk untuk promosi atau set menu.</p>
    <a href="{{ route('bundles.create') }}"
       class="inline-flex items-center gap-2 mt-4 bg-emerald-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl hover:bg-emerald-700 transition-colors">
        Buat Bundle Pertama
    </a>
</div>
@else
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @foreach($bundles as $bundle)
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow overflow-hidden">
        {{-- Header --}}
        <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <h3 class="font-bold text-gray-900 text-sm truncate">{{ $bundle->name }}</h3>
                    <x-badge :color="$bundle->is_active ? 'green' : 'gray'">
                        {{ $bundle->is_active ? 'Aktif' : 'Nonaktif' }}
                    </x-badge>
                </div>
                @if($bundle->description)
                <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $bundle->description }}</p>
                @endif
            </div>
            <p class="text-base font-extrabold text-emerald-700 whitespace-nowrap">
                Rp {{ number_format($bundle->price, 0, ',', '.') }}
            </p>
        </div>

        {{-- Items --}}
        <div class="px-5 py-3 space-y-1.5">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Isi Paket</p>
            @foreach($bundle->items as $item)
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-700 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    {{ optional($item->product)->name ?? '(produk dihapus)' }}
                </span>
                <span class="text-gray-500 font-medium text-xs">× {{ rtrim(rtrim(number_format($item->qty, 3, '.', ''), '0'), '.') }}</span>
            </div>
            @endforeach
        </div>

        {{-- Actions --}}
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-2">
            <form method="POST" action="{{ route('bundles.toggle', $bundle) }}">
                @csrf @method('PATCH')
                <button type="submit"
                        class="text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors
                               {{ $bundle->is_active ? 'text-orange-600 border-orange-200 hover:bg-orange-50' : 'text-emerald-600 border-emerald-200 hover:bg-emerald-50' }}">
                    {{ $bundle->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
            </form>
            <div class="flex items-center gap-2">
                <a href="{{ route('bundles.edit', $bundle) }}"
                   class="text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-3 py-1.5 rounded-lg transition-colors">
                    Edit
                </a>
                <form method="POST" action="{{ route('bundles.destroy', $bundle) }}"
                      onsubmit="return confirm('Hapus bundle {{ addslashes($bundle->name) }}?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg transition-colors">
                        Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
