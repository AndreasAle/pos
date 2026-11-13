{{-- Form bundle bersama untuk tambah dan edit.
     Butuh: $products, $action, $submitLabel, $cancelUrl, dan $bundle (null saat tambah). --}}
@php
    $b      = $bundle ?? null;
    $isEdit = (bool) $b;
    $val    = fn($key, $default = null) => old($key, $b?->{$key} ?? $default);
    $initial = $isEdit
        ? $b->items->map(fn($i) => ['product_id' => (string) $i->product_id, 'qty' => (float) $i->qty])->values()
        : collect();
    $prices = $products->mapWithKeys(fn($p) => [(string) $p->id => (float) $p->price]);
@endphp

<form method="POST" action="{{ $action }}" class="page text-[#0B1B3F] pt-2"
      x-data="bundleForm(@js($initial), @js($prices), {{ (int) $val('price', 0) }})">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-4 items-start">

        <div class="space-y-4 min-w-0">
            <x-panel title="Informasi Bundle" sub="Nama dan deskripsi singkat paket." class="fade-up" style="--i:1">
                <div class="space-y-5">
                    <x-field label="Nama Bundle" name="name" :required="true">
                        <x-input name="name" :value="$val('name')" required placeholder="Contoh: Paket Hemat A, Set Makan Siang" />
                    </x-field>
                    <x-field label="Deskripsi" name="description" hint="Opsional, keterangan singkat paket.">
                        <x-input name="description" :value="$val('description')" placeholder="Contoh: 1 makanan + 1 minuman" />
                    </x-field>
                </div>
            </x-panel>

            <x-panel title="Isi Paket" sub="Pilih produk dan jumlahnya." class="fade-up" style="--i:2">
                <div class="space-y-3">
                    <template x-for="(item, idx) in items" :key="idx">
                        <div class="flex items-center gap-2.5 rounded-lg border border-[#E5E7EB] bg-slate-50 p-3">
                            <div class="min-w-0 flex-1">
                                <x-select :compact="true" x-bind:name="'items['+idx+'][product_id]'" x-model="item.product_id" required>
                                    <option value="">Pilih produk</option>
                                    @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} &mdash; Rp {{ number_format($product->price, 0, ',', '.') }}</option>
                                    @endforeach
                                </x-select>
                            </div>
                            <input type="number" x-bind:name="'items['+idx+'][qty]'" x-model.number="item.qty" min="0.001" step="0.5" placeholder="Qty" required aria-label="Jumlah"
                                   class="h-10 w-20 rounded-lg border border-[#E5E7EB] bg-white text-center text-sm font-semibold focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30">
                            <button type="button" @click="removeItem(idx)" x-show="items.length > 1" aria-label="Hapus item"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-[#64748B] transition-colors hover:bg-red-50 hover:text-red-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>

                    <button type="button" @click="addItem()"
                            class="flex h-11 w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-[#E5E7EB] text-sm font-semibold text-[#64748B] transition-colors hover:border-[#16A34A]/60 hover:text-[#16A34A]">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Tambah produk
                    </button>
                </div>
            </x-panel>
        </div>

        <div class="space-y-4 lg:sticky lg:top-20">
            <x-panel title="Harga Bundle" class="fade-up" style="--i:2">
                <x-field label="Harga Jual" name="price" :required="true" hint="Harga paket di kasir, boleh lebih murah dari total normal.">
                    <x-input name="price" type="number" prefix="Rp" :value="$val('price', 0)" min="0" step="1" required x-model.number="price" inputmode="numeric" />
                </x-field>

                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between"><dt class="text-[#64748B]">Total harga normal</dt><dd class="font-semibold tabular-nums" x-text="rp(normal)"></dd></div>
                    <div class="flex items-center justify-between"><dt class="text-[#64748B]">Harga bundle</dt><dd class="font-semibold tabular-nums" x-text="rp(price)"></dd></div>
                </dl>
                <div class="mt-4 flex items-center justify-between rounded-lg border px-4 py-3"
                     :class="saving > 0 ? 'border-[#BBF7D0] bg-[#ECFDF3]' : (saving < 0 ? 'border-amber-200 bg-amber-50' : 'border-[#E5E7EB] bg-slate-50')">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider" :class="saving > 0 ? 'text-[#15803D]' : (saving < 0 ? 'text-amber-800' : 'text-[#64748B]')"
                           x-text="saving > 0 ? 'Pelanggan hemat' : (saving < 0 ? 'Lebih mahal dari normal' : 'Sama dengan normal')"></p>
                        <p class="mt-0.5 text-xs text-[#64748B]" x-text="rp(Math.abs(saving))"></p>
                    </div>
                    <p class="text-2xl font-extrabold tabular-nums" :class="saving > 0 ? 'text-[#15803D]' : (saving < 0 ? 'text-amber-800' : 'text-[#64748B]')" x-text="percent + '%'"></p>
                </div>
            </x-panel>

            <div class="fade-up flex flex-col gap-2" style="--i:3">
                <x-btn type="submit" size="lg">{{ $submitLabel }}</x-btn>
                <x-btn :href="$cancelUrl" variant="ghost">Batal</x-btn>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
function bundleForm(existing, prices, initialPrice) {
    return {
        items: existing && existing.length ? existing.map(i => ({ product_id: String(i.product_id), qty: i.qty })) : [{ product_id: '', qty: 1 }],
        prices: prices || {},
        price: initialPrice || 0,
        get normal() { return this.items.reduce((s, i) => s + (Number(this.prices[i.product_id] || 0) * Number(i.qty || 0)), 0) },
        get saving() { return this.normal - Number(this.price || 0) },
        get percent() { return this.normal > 0 ? Math.round(Math.abs(this.saving) / this.normal * 100) : 0 },
        rp(v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID') },
        addItem() { this.items.push({ product_id: '', qty: 1 }); },
        removeItem(idx) { if (this.items.length > 1) this.items.splice(idx, 1); },
    };
}
</script>
@endpush
