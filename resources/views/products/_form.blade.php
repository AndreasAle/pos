{{-- Form produk bersama untuk tambah dan edit.
     Butuh: $categories, $outlets, $action, $submitLabel, $cancelUrl, dan $product (null saat tambah). --}}
@php
    $p      = $product ?? null;
    $isEdit = (bool) $p;
    $val    = fn($key, $default = null) => old($key, $p?->{$key} ?? $default);
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="page text-[#0B1B3F] pt-2"
      x-data="{
          price: {{ (int) $val('price', 0) }}, cost: {{ (int) $val('cost_price', 0) }},
          preview: @js($isEdit && $p->image ? $p->image_url : null),
          get margin() { return this.price > 0 ? Math.round((this.price - this.cost) / this.price * 100) : 0 },
          pick(e) { const f = e.target.files[0]; if (f) { this.preview = URL.createObjectURL(f) } }
      }">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-4 items-start">

        {{-- Kiri --}}
        <div class="space-y-4 min-w-0">
            <x-panel title="Informasi Produk" sub="Nama dan kategori yang tampil di kasir." class="fade-up" style="--i:1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Nama Produk" name="name" :required="true" class="sm:col-span-2">
                        <x-input name="name" :value="$val('name')" required placeholder="Contoh: Es Kopi Susu, Nasi Ayam Geprek..." />
                    </x-field>
                    <x-field label="Kategori" name="product_category_id">
                        <x-select name="product_category_id">
                            <option value="">Tanpa kategori</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) $val('product_category_id') === (string) $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    <x-field label="SKU" name="sku" hint="Kode produk, boleh dikosongkan.">
                        <x-input name="sku" :value="$val('sku')" placeholder="Contoh: KOPI-001" class="font-mono" />
                    </x-field>
                    @if($outlets->count() > 1)
                    <x-field label="Outlet" name="outlet_id" hint="Kosong berarti tersedia di semua outlet." class="sm:col-span-2">
                        <x-select name="outlet_id">
                            <option value="">Semua outlet</option>
                            @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}" @selected((string) $val('outlet_id') === (string) $outlet->id)>{{ $outlet->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>
                    @endif
                    <x-field label="Deskripsi" name="description" class="sm:col-span-2">
                        <x-textarea name="description" placeholder="Deskripsi singkat produk (opsional)">{{ $val('description') }}</x-textarea>
                    </x-field>
                </div>
            </x-panel>

            <x-panel title="Harga" sub="Harga jual dan modal untuk menghitung margin." class="fade-up" style="--i:2">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <x-field label="Harga Jual" name="price" :required="true">
                        <x-input name="price" type="number" prefix="Rp" :value="$val('price', 0)" min="0" step="1" required x-model.number="price" inputmode="numeric" />
                    </x-field>
                    <x-field label="Harga Modal" name="cost_price">
                        <x-input name="cost_price" type="number" prefix="Rp" :value="$val('cost_price', 0)" min="0" step="1" x-model.number="cost" inputmode="numeric" />
                    </x-field>
                </div>
                <div class="mt-5 flex items-center justify-between rounded-lg border px-4 py-3"
                     :class="margin >= 0 ? 'border-[#BBF7D0] bg-[#ECFDF3]' : 'border-red-200 bg-red-50'">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider" :class="margin >= 0 ? 'text-[#15803D]' : 'text-red-700'">Margin</p>
                        <p class="mt-0.5 text-xs text-[#64748B]">Untung per porsi: <span class="font-semibold text-[#0B1B3F]" x-text="'Rp ' + Math.max(0, price - cost).toLocaleString('id-ID')"></span></p>
                    </div>
                    <p class="text-2xl font-extrabold tabular-nums" :class="margin >= 0 ? 'text-[#15803D]' : 'text-red-700'" x-text="margin + '%'"></p>
                </div>
            </x-panel>
        </div>

        {{-- Kanan --}}
        <div class="space-y-4 lg:sticky lg:top-20">
            <x-panel title="Foto Produk" class="fade-up" style="--i:2">
                <label class="group relative block cursor-pointer overflow-hidden rounded-lg border-2 border-dashed border-[#E5E7EB] bg-slate-50 transition-colors hover:border-[#16A34A]/60">
                    <div class="aspect-square w-full">
                        <template x-if="preview"><img :src="preview" alt="" class="h-full w-full object-cover"></template>
                        <div x-show="!preview" class="flex h-full w-full flex-col items-center justify-center gap-2 p-6 text-center">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#ECFDF3] text-[#16A34A]">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                            <p class="text-sm font-semibold">Pilih foto produk</p>
                            <p class="text-xs text-[#64748B]">JPEG, PNG, atau WebP, maks 10 MB</p>
                        </div>
                    </div>
                    <input type="file" name="image" accept="image/*" class="sr-only" @change="pick($event)">
                </label>
                @if($isEdit && $p->image)<p class="mt-2 text-xs text-[#64748B]">Pilih foto baru untuk mengganti yang sekarang.</p>@endif
                @error('image')<p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </x-panel>

            <x-panel title="Pengaturan" class="fade-up" style="--i:3">
                <div class="space-y-3">
                    <x-switch name="is_active" label="Produk aktif" desc="Nonaktif berarti tidak tampil di kasir." :checked="(bool) $val('is_active', true)" />
                    <x-switch name="is_stock_tracked" label="Pantau stok via resep" desc="Stok bahan baku berkurang otomatis saat terjual." :checked="(bool) $val('is_stock_tracked', false)" />
                </div>
            </x-panel>

            <div class="fade-up flex flex-col gap-2" style="--i:4">
                <x-btn type="submit" size="lg">{{ $submitLabel }}</x-btn>
                <x-btn :href="$cancelUrl" variant="ghost">Batal</x-btn>
            </div>
        </div>
    </div>
</form>
