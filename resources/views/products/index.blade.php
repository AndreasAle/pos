@extends('layouts.app')
@section('title', 'Produk / Menu')
@section('page-title', 'Produk / Menu')

@section('content')
{{-- Toolbar --}}
<div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
    <form method="GET" class="flex flex-1 items-center gap-2">
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Cari produk..."
                   class="pl-9 pr-4 py-2.5 w-full border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
        </div>
        <select name="category_id" onchange="this.form.submit()"
                class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <option value="">Semua Kategori</option>
            @foreach($categories as $cat)
            <option value="{{ $cat->id }}" {{ request('category_id')==$cat->id?'selected':'' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
        <select name="status" onchange="this.form.submit()"
                class="px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <option value="">Semua Status</option>
            <option value="1" {{ request('status')==='1'?'selected':'' }}>Aktif</option>
            <option value="0" {{ request('status')==='0'?'selected':'' }}>Nonaktif</option>
        </select>
    </form>
    <a href="{{ route('barcodes.select') }}"
       class="inline-flex items-center gap-2 bg-gray-700 hover:bg-gray-800 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors shrink-0">
        🏷 Label Barcode
    </a>
    <a href="{{ route('products.create') }}"
       class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Tambah Produk
    </a>
</div>

{{-- Product Grid --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
    @forelse($products as $product)
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow group">
        <div class="aspect-square bg-gray-100 relative overflow-hidden">
            @if($product->image)
            <img src="{{ asset('storage/' . $product->image) }}"
                 alt="{{ $product->name }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
            @else
            <div class="w-full h-full flex items-center justify-center">
                <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                </svg>
            </div>
            @endif
            @if(!$product->is_active)
            <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                <span class="text-white text-xs font-semibold bg-black/60 px-2 py-1 rounded-full">Nonaktif</span>
            </div>
            @endif
        </div>
        <div class="p-3">
            <p class="text-sm font-semibold text-gray-900 truncate leading-tight">{{ $product->name }}</p>
            @if($product->category)
            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $product->category->name }}</p>
            @endif
            <p class="text-sm font-bold text-emerald-700 mt-1">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
            <div class="flex items-center gap-1 mt-2">
                <a href="{{ route('products.show', $product) }}"
                   class="flex-1 text-center text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2 py-1.5 rounded-lg transition-colors">
                    Detail
                </a>
                <form method="POST" action="{{ route('products.toggle', $product) }}">
                    @csrf @method('PATCH')
                    <button title="{{ $product->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                            class="text-xs font-medium {{ $product->is_active ? 'text-orange-500 hover:bg-orange-50' : 'text-emerald-600 hover:bg-emerald-50' }} px-2 py-1.5 rounded-lg transition-colors">
                        {{ $product->is_active ? '🔴' : '🟢' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="col-span-6 text-center py-16 text-gray-400">
        <svg class="w-16 h-16 mx-auto mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
        </svg>
        <p>Belum ada produk</p>
        <a href="{{ route('products.create') }}" class="text-emerald-600 text-sm hover:underline mt-1 inline-block">Tambah produk pertama</a>
    </div>
    @endforelse
</div>

<div class="mt-4">{{ $products->links() }}</div>
@endsection
