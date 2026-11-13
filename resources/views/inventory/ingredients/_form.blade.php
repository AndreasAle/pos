{{-- Form bahan baku bersama untuk tambah dan edit.
     Butuh: $action, $submitLabel, $cancelUrl, dan $ingredient (null saat tambah). --}}
@php
    $ing    = $ingredient ?? null;
    $isEdit = (bool) $ing;
    $val    = fn($key, $default = null) => old($key, $ing?->{$key} ?? $default);
    $units  = ['pcs' => 'Pcs', 'gram' => 'Gram', 'kg' => 'Kilogram', 'ml' => 'Mililiter', 'liter' => 'Liter', 'pack' => 'Pack', 'box' => 'Box', 'sachet' => 'Sachet'];
@endphp

<form method="POST" action="{{ $action }}" class="page text-[#0B1B3F] pt-2 max-w-3xl">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <x-panel title="Data Bahan Baku" sub="Satuan menentukan cara stok dan resep dihitung." class="fade-up" style="--i:1">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <x-field label="Nama Bahan" name="name" :required="true" class="sm:col-span-2">
                <x-input name="name" :value="$val('name')" required placeholder="Contoh: Kopi Arabika, Susu UHT" />
            </x-field>
            <x-field label="SKU / Kode" name="sku" hint="Opsional.">
                <x-input name="sku" :value="$val('sku')" class="font-mono" placeholder="Contoh: BHN-001" />
            </x-field>
            <x-field label="Satuan" name="unit" :required="true">
                <x-select name="unit" required>
                    @foreach($units as $v => $l)
                    <option value="{{ $v }}" @selected($val('unit') === $v)>{{ $l }}</option>
                    @endforeach
                </x-select>
            </x-field>
            <x-field label="Stok Minimum" name="minimum_stock" hint="Peringatan muncul jika stok di bawah angka ini.">
                <x-input name="minimum_stock" type="number" :value="$val('minimum_stock', 0)" min="0" step="0.001" />
            </x-field>
            <x-field label="Harga Rata-rata" name="average_cost" hint="Harga per satuan, dipakai menghitung modal produk.">
                <x-input name="average_cost" type="number" prefix="Rp" :value="$val('average_cost', 0)" min="0" step="any" />
            </x-field>
            @if($isEdit)
            <div class="sm:col-span-2">
                <x-switch name="is_active" label="Bahan aktif" desc="Bahan nonaktif tidak bisa dipilih di resep." :checked="(bool) $val('is_active', true)" />
            </div>
            @endif
        </div>
    </x-panel>

    <div class="fade-up mt-4 flex flex-wrap items-center gap-2" style="--i:2">
        <x-btn type="submit">{{ $submitLabel }}</x-btn>
        <x-btn :href="$cancelUrl" variant="ghost">Batal</x-btn>
    </div>
</form>
