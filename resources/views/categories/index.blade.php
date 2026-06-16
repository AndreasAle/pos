@extends('layouts.app')
@section('title', 'Kategori Menu')
@section('page-title', 'Kategori Menu')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Form Tambah --}}
    <div>
        <x-card title="Tambah Kategori">
            <form method="POST" action="{{ route('categories.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kategori <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                           placeholder="Contoh: Minuman, Makanan Utama...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Warna Label</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="color" value="{{ old('color', '#10b981') }}"
                               class="h-10 w-14 rounded-lg border border-gray-300 cursor-pointer">
                        <span class="text-xs text-gray-500">Pilih warna untuk label kategori di POS</span>
                    </div>
                </div>
                <button type="submit"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                    Tambah Kategori
                </button>
            </form>
        </x-card>
    </div>

    {{-- List Kategori --}}
    <div class="lg:col-span-2">
        <x-card title="Daftar Kategori ({{ count($categories) }})">
            @forelse($categories as $category)
            <div class="flex items-center gap-3 py-2.5 {{ !$loop->last ? 'border-b border-gray-50' : '' }}"
                 x-data="{ editing: false }">
                <div class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: {{ $category->color ?? '#10b981' }}"></div>
                <div class="flex-1 min-w-0">
                    <div x-show="!editing">
                        <p class="font-medium text-gray-900 text-sm">{{ $category->name }}</p>
                        <p class="text-xs text-gray-400">{{ $category->products_count }} produk</p>
                    </div>
                    <form x-show="editing" method="POST" action="{{ route('categories.update', $category) }}" class="flex items-center gap-2">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $category->name }}" required
                               class="flex-1 px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <input type="color" name="color" value="{{ $category->color ?? '#10b981' }}" class="h-8 w-10 rounded border border-gray-300">
                        <input type="hidden" name="is_active" value="{{ $category->is_active ? '1' : '0' }}">
                        <button type="submit" class="text-xs font-medium text-emerald-600 hover:text-emerald-700 px-2 py-1 rounded-lg bg-emerald-50">Simpan</button>
                        <button type="button" @click="editing = false" class="text-xs text-gray-400 hover:text-gray-600">Batal</button>
                    </form>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0" x-show="!editing">
                    <x-badge :color="$category->is_active ? 'green' : 'gray'">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                    <button @click="editing = true"
                            class="text-xs font-medium text-gray-500 hover:text-gray-700 px-2 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        Edit
                    </button>
                    @if($category->products_count == 0)
                    <form method="POST" action="{{ route('categories.destroy', $category) }}"
                          onsubmit="return confirm('Hapus kategori ini?')">
                        @csrf @method('DELETE')
                        <button class="text-xs font-medium text-red-500 hover:text-red-600 px-2 py-1 rounded-lg hover:bg-red-50 transition-colors">
                            Hapus
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @empty
            <p class="text-sm text-gray-400 text-center py-8">Belum ada kategori. Tambahkan di form sebelah.</p>
            @endforelse
        </x-card>
    </div>
</div>
@endsection
