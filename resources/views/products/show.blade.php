@extends('layouts.app')
@section('title', $product->name)
@section('page-title', $product->name)

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('products.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Kembali</a>
    <div class="flex items-center gap-2 ml-auto">
        <a href="{{ route('products.edit', $product) }}"
           class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-xl">
            Edit Produk
        </a>
        <form method="POST" action="{{ route('products.destroy', $product) }}"
              onsubmit="return confirm('Hapus produk ini?')">
            @csrf @method('DELETE')
            <button class="text-sm font-medium text-red-600 border border-red-200 hover:bg-red-50 px-4 py-2 rounded-xl transition-colors">
                Hapus
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left: Detail --}}
    <div class="lg:col-span-2 space-y-4">
        <x-card>
            <div class="flex gap-5">
                <div class="w-28 h-28 rounded-2xl bg-gray-100 flex-shrink-0 overflow-hidden">
                    @if($product->image)
                    <img src="{{ asset('storage/' . $product->image) }}" class="w-full h-full object-cover">
                    @else
                    <div class="w-full h-full flex items-center justify-center">
                        <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                        </svg>
                    </div>
                    @endif
                </div>
                <div class="flex-1">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">{{ $product->name }}</h2>
                            @if($product->category)
                            <p class="text-sm text-gray-500 mt-0.5">{{ $product->category->name }}</p>
                            @endif
                            @if($product->sku)
                            <p class="text-xs text-gray-400 font-mono mt-1">SKU: {{ $product->sku }}</p>
                            @endif
                        </div>
                        <x-badge :color="$product->is_active ? 'green' : 'red'">
                            {{ $product->is_active ? 'Aktif' : 'Nonaktif' }}
                        </x-badge>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div class="bg-emerald-50 rounded-xl p-3">
                            <p class="text-xs text-gray-500">Harga Jual</p>
                            <p class="text-lg font-bold text-emerald-700">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            <p class="text-xs text-gray-500">Harga Modal</p>
                            <p class="text-lg font-bold text-gray-700">Rp {{ number_format($product->cost_price, 0, ',', '.') }}</p>
                        </div>
                    </div>
                    @if($product->description)
                    <p class="text-sm text-gray-600 mt-3">{{ $product->description }}</p>
                    @endif
                </div>
            </div>
        </x-card>

        {{-- Variants --}}
        <x-card title="Varian Produk">
            <div class="space-y-2 mb-4">
                @forelse($product->variants as $variant)
                <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $variant->name }}</p>
                        <p class="text-xs text-gray-500">
                            Harga: Rp {{ number_format($product->price + $variant->price_adjustment, 0, ',', '.') }}
                            @if($variant->price_adjustment != 0)
                            <span class="{{ $variant->price_adjustment > 0 ? 'text-orange-500' : 'text-emerald-600' }}">
                                ({{ $variant->price_adjustment > 0 ? '+' : '' }}Rp {{ number_format($variant->price_adjustment, 0, ',', '.') }})
                            </span>
                            @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('products.variants.destroy', [$product, $variant]) }}"
                          onsubmit="return confirm('Hapus varian ini?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-500 hover:text-red-600">Hapus</button>
                    </form>
                </div>
                @empty
                <p class="text-sm text-gray-400">Belum ada varian</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('products.variants.store', $product) }}" class="flex items-end gap-2">
                @csrf
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nama Varian</label>
                    <input type="text" name="name" placeholder="Contoh: Ukuran L, Pedas Banget..." required
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div class="w-36">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Selisih Harga</label>
                    <input type="number" name="price_adjustment" value="0" step="500"
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                    + Tambah
                </button>
            </form>
        </x-card>

        {{-- Addons --}}
        <x-card title="Add-on / Topping">
            <div class="space-y-2 mb-4">
                @forelse($product->addons as $addon)
                <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $addon->name }}</p>
                        <p class="text-xs text-emerald-600">+ Rp {{ number_format($addon->price, 0, ',', '.') }}</p>
                    </div>
                    <form method="POST" action="{{ route('products.addons.destroy', [$product, $addon]) }}"
                          onsubmit="return confirm('Hapus add-on ini?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-500 hover:text-red-600">Hapus</button>
                    </form>
                </div>
                @empty
                <p class="text-sm text-gray-400">Belum ada add-on</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('products.addons.store', $product) }}" class="flex items-end gap-2">
                @csrf
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nama Add-on</label>
                    <input type="text" name="name" placeholder="Contoh: Extra Keju, Tambah Es..." required
                           class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div class="w-36">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Harga</label>
                    <div class="relative">
                        <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                        <input type="number" name="price" value="0" min="0" step="500"
                               class="pl-7 w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                    + Tambah
                </button>
            </form>
        </x-card>
    </div>

    {{-- Right: Recipe & Info --}}
    <div class="space-y-4">
        <x-card title="Info Tambahan">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Margin</dt>
                    <dd class="font-medium text-emerald-700">
                        @php $margin = $product->price > 0 ? (($product->price - $product->cost_price) / $product->price) * 100 : 0; @endphp
                        {{ number_format($margin, 1) }}%
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Pantau Stok</dt>
                    <dd><x-badge :color="$product->is_stock_tracked ? 'green' : 'gray'">{{ $product->is_stock_tracked ? 'Ya' : 'Tidak' }}</x-badge></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Varian</dt>
                    <dd class="font-medium">{{ $product->variants->count() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Add-on</dt>
                    <dd class="font-medium">{{ $product->addons->count() }}</dd>
                </div>
            </dl>
        </x-card>

        @if($product->recipe)
        <x-card title="Resep">
            <p class="text-xs text-gray-500 mb-2">Bahan yang digunakan:</p>
            @foreach($product->recipe->items as $item)
            <div class="flex justify-between text-sm py-1 border-b border-gray-50 last:border-0">
                <span class="text-gray-700">{{ $item->ingredient->name }}</span>
                <span class="text-gray-500">{{ number_format($item->qty, 2) }} {{ $item->ingredient->unit }}</span>
            </div>
            @endforeach
            <a href="{{ route('recipes.edit', $product) }}" class="text-xs text-emerald-600 hover:underline mt-2 inline-block">Edit Resep</a>
        </x-card>
        @else
        <x-card title="Resep">
            <p class="text-sm text-gray-400 mb-3">Belum ada resep untuk produk ini.</p>
            <a href="{{ route('recipes.edit', $product) }}"
               class="text-sm font-medium text-emerald-600 hover:underline">+ Tambah Resep</a>
        </x-card>
        @endif
    </div>
</div>
@endsection
