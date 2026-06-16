@extends('layouts.app')
@section('title','Edit Resep: ' . $product->name)
@section('page-title','Edit Resep: ' . $product->name)

@section('content')
<div class="max-w-2xl" x-data="{
    items: {{ json_encode($product->recipe?->items->map(fn($i) => ['ingredient_id' => $i->ingredient_id, 'name' => $i->ingredient->name, 'unit' => $i->ingredient->unit, 'qty' => (float)$i->qty])->values() ?? collect()) }},
    addItem() { this.items.push({ ingredient_id: '', name: '', unit: '', qty: 1 }); },
    removeItem(idx) { this.items.splice(idx, 1); },
}">
    <div class="flex items-center gap-4 mb-5">
        <a href="{{ route('recipes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Resep Produk</a>
    </div>

    <form method="POST" action="{{ route('recipes.update', $product) }}">
        @csrf
        <x-card title="Resep untuk: {{ $product->name }}">
            <div class="space-y-3 mb-4">
                <template x-for="(item, idx) in items" :key="idx">
                    <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-3">
                        <div class="flex-1">
                            <select :name="'items['+idx+'][ingredient_id]'" x-model="item.ingredient_id" required
                                    @change="const opt = $event.target.options[$event.target.selectedIndex]; item.unit = opt.dataset.unit || ''"
                                    class="w-full text-sm border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                <option value="">-- Pilih Bahan --</option>
                                @foreach($ingredients as $ing)
                                <option value="{{ $ing->id }}" data-unit="{{ $ing->unit }}"
                                        :selected="item.ingredient_id == {{ $ing->id }}">
                                    {{ $ing->name }} ({{ $ing->unit }})
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-32">
                            <div class="flex items-center border border-gray-300 rounded-xl overflow-hidden">
                                <input type="number" :name="'items['+idx+'][qty]'" x-model.number="item.qty"
                                       min="0.001" step="0.001" required
                                       class="w-full px-3 py-2 text-sm focus:outline-none text-right">
                                <span class="px-2 text-xs text-gray-500 bg-gray-50 border-l border-gray-200 whitespace-nowrap" x-text="item.unit || 'satuan'"></span>
                            </div>
                        </div>
                        <button type="button" @click="removeItem(idx)"
                                class="text-red-400 hover:text-red-600 p-1.5 rounded-lg hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </template>

                <template x-if="items.length === 0">
                    <p class="text-sm text-gray-400 text-center py-4">Belum ada bahan ditambahkan.</p>
                </template>
            </div>

            <button type="button" @click="addItem()"
                    class="w-full flex items-center justify-center gap-2 text-sm font-medium text-emerald-700 border-2 border-dashed border-emerald-300 hover:border-emerald-500 hover:bg-emerald-50 py-3 rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Bahan
            </button>

            <div class="flex items-center gap-3 mt-5 pt-4 border-t border-gray-100">
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                    Simpan Resep
                </button>
                <a href="{{ route('products.show', $product) }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </x-card>
    </form>
</div>
@endsection
