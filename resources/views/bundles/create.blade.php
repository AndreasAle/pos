@extends('layouts.app')
@section('title', 'Buat Bundle Baru')
@section('page-title', 'Buat Bundle Baru')

@section('content')
<div class="max-w-2xl" x-data="bundleForm()">
    <form method="POST" action="{{ route('bundles.store') }}">
        @csrf

        <div class="space-y-5">
            {{-- Info Umum --}}
            <x-card title="Informasi Bundle">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Bundle <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                               placeholder="Contoh: Paket Hemat A, Set Makan Siang...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
                        <input type="text" name="description" value="{{ old('description') }}"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                               placeholder="Opsional — keterangan singkat">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga Bundle <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500 font-medium">Rp</span>
                            <input type="number" name="price" value="{{ old('price', 0) }}" min="0" step="500" required
                                   class="pl-10 w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Harga jual bundle ini di kasir (bisa lebih murah dari total normal).</p>
                    </div>
                </div>
            </x-card>

            {{-- Isi Produk --}}
            <x-card title="Isi Paket Bundle">
                <div class="space-y-3">
                    <template x-for="(item, idx) in items" :key="idx">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-200">
                            <div class="flex-1">
                                <select :name="'items['+idx+'][product_id]'" x-model="item.product_id" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    <option value="">-- Pilih Produk --</option>
                                    @foreach($products as $product)
                                    <option value="{{ $product->id }}">
                                        {{ $product->name }} — Rp {{ number_format($product->price, 0, ',', '.') }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-24">
                                <input type="number" :name="'items['+idx+'][qty]'" x-model.number="item.qty"
                                       min="0.001" step="0.5" placeholder="Qty" required
                                       class="w-full px-2.5 py-2 border border-gray-300 rounded-lg text-sm text-center focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <button type="button" @click="removeItem(idx)"
                                    x-show="items.length > 1"
                                    class="p-1.5 text-gray-300 hover:text-red-500 transition-colors rounded-lg hover:bg-red-50">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </template>

                    <button type="button" @click="addItem()"
                            class="w-full flex items-center justify-center gap-2 py-2.5 border-2 border-dashed border-gray-300 hover:border-emerald-400 text-sm text-gray-500 hover:text-emerald-600 rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                        Tambah Produk
                    </button>
                </div>
            </x-card>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors shadow-sm">
                    Simpan Bundle
                </button>
                <a href="{{ route('bundles.index') }}"
                   class="text-sm text-gray-600 hover:text-gray-900 px-4 py-2.5 rounded-xl border border-gray-300 hover:bg-gray-50 transition-colors">
                    Batal
                </a>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function bundleForm() {
    return {
        items: [{ product_id: '', qty: 1 }],
        addItem()  { this.items.push({ product_id: '', qty: 1 }); },
        removeItem(idx) { if (this.items.length > 1) this.items.splice(idx, 1); },
    };
}
</script>
@endpush
@endsection
