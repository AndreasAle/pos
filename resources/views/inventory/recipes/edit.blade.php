@extends('layouts.app')
@section('title','Edit Resep: ' . $product->name)
@section('page-title','Edit Resep: ' . $product->name)

@section('content')
@php
    $initial = $product->recipe?->items->map(fn($i) => [
        'ingredient_id' => $i->ingredient_id,
        'unit'          => $i->ingredient->unit,
        'qty'           => (float) $i->qty,
    ])->values() ?? collect();
    $costs = $ingredients->mapWithKeys(fn($i) => [(string) $i->id => (float) $i->average_cost]);
@endphp

<form method="POST" action="{{ route('recipes.update', $product) }}" class="page text-[#0B1B3F] pt-2"
      x-data="{
        items: @js($initial),
        costs: @js($costs),
        price: {{ (float) $product->price }},
        addItem() { this.items.push({ ingredient_id: '', unit: '', qty: 1 }); },
        removeItem(idx) { this.items.splice(idx, 1); },
        get cost() { return this.items.reduce((s, i) => s + (Number(this.costs[i.ingredient_id] || 0) * Number(i.qty || 0)), 0) },
        get margin() { return this.price > 0 ? Math.round((this.price - this.cost) / this.price * 100) : 0 },
        rp(v) { return 'Rp ' + Math.round(Number(v || 0)).toLocaleString('id-ID') },
      }">
    @csrf

    <x-page-head :title="$product->name" :back="route('recipes.index')" backLabel="Resep Produk" sub="Atur bahan yang dipakai untuk satu porsi produk ini." style="--i:0" />

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-4 items-start">

        <x-panel title="Bahan Resep" sub="Jumlah dihitung per satu porsi." class="fade-up" style="--i:1">
            <div class="space-y-3">
                <template x-for="(item, idx) in items" :key="idx">
                    <div class="flex flex-wrap items-center gap-2.5 rounded-lg border border-[#E5E7EB] bg-slate-50 p-3">
                        <div class="min-w-[12rem] flex-1">
                            <x-select :compact="true" x-bind:name="'items['+idx+'][ingredient_id]'" x-model="item.ingredient_id" required
                                      @change="const opt = $event.target.options[$event.target.selectedIndex]; item.unit = opt.dataset.unit || ''">
                                <option value="">Pilih bahan</option>
                                @foreach($ingredients as $ing)
                                <option value="{{ $ing->id }}" data-unit="{{ $ing->unit }}">{{ $ing->name }} ({{ $ing->unit }})</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div class="flex h-10 w-40 items-stretch overflow-hidden rounded-lg border border-[#E5E7EB] bg-white focus-within:border-[#16A34A] focus-within:ring-2 focus-within:ring-[#16A34A]/30">
                            <input type="number" x-bind:name="'items['+idx+'][qty]'" x-model.number="item.qty" min="0.001" step="0.001" required aria-label="Jumlah"
                                   class="w-full min-w-0 bg-transparent px-3 text-right text-sm font-semibold focus:outline-none">
                            <span class="flex items-center border-l border-[#E5E7EB] bg-slate-50 px-2.5 text-xs font-semibold text-[#64748B] whitespace-nowrap" x-text="item.unit || 'satuan'"></span>
                        </div>
                        <button type="button" @click="removeItem(idx)" aria-label="Hapus bahan"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-[#64748B] transition-colors hover:bg-red-50 hover:text-red-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </template>

                <template x-if="items.length === 0">
                    <p class="rounded-lg border border-dashed border-[#E5E7EB] bg-slate-50 px-4 py-6 text-center text-sm text-[#64748B]">Belum ada bahan. Tambahkan bahan pertama.</p>
                </template>

                <button type="button" @click="addItem()"
                        class="flex h-11 w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-[#E5E7EB] text-sm font-semibold text-[#64748B] transition-colors hover:border-[#16A34A]/60 hover:text-[#16A34A]">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Tambah bahan
                </button>
            </div>
        </x-panel>

        <div class="space-y-4 lg:sticky lg:top-20">
            <x-panel title="Estimasi Modal" sub="Dihitung dari harga rata-rata bahan." class="fade-up" style="--i:2">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between"><dt class="text-[#64748B]">Harga jual</dt><dd class="font-semibold tabular-nums" x-text="rp(price)"></dd></div>
                    <div class="flex items-center justify-between"><dt class="text-[#64748B]">Modal bahan / porsi</dt><dd class="font-semibold tabular-nums" x-text="rp(cost)"></dd></div>
                </dl>
                <div class="mt-4 flex items-center justify-between rounded-lg border px-4 py-3"
                     :class="margin >= 0 ? 'border-[#BBF7D0] bg-[#ECFDF3]' : 'border-red-200 bg-red-50'">
                    <p class="text-xs font-semibold uppercase tracking-wider" :class="margin >= 0 ? 'text-[#15803D]' : 'text-red-700'">Margin</p>
                    <p class="text-2xl font-extrabold tabular-nums" :class="margin >= 0 ? 'text-[#15803D]' : 'text-red-700'" x-text="margin + '%'"></p>
                </div>
            </x-panel>

            <div class="fade-up flex flex-col gap-2" style="--i:3">
                <x-btn type="submit" size="lg">Simpan Resep</x-btn>
                <x-btn :href="route('products.show', $product)" variant="ghost">Batal</x-btn>
            </div>
        </div>
    </div>
</form>
@endsection
