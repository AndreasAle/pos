{{-- Kolom "Beli X gratis Y", tampil hanya untuk tipe promo itu. Butuh $categories dan $promotion (opsional). --}}
<div x-show="type === 'buy_get'" x-cloak class="rounded-lg border border-[#BBF7D0] bg-[#ECFDF3] p-4 sm:p-5">
    <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-[#15803D]">Aturan Beli Gratis</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-field label="Beli (qty)" name="buy_qty">
            <x-input name="buy_qty" type="number" :value="old('buy_qty', $promotion->buy_qty ?? 1)" min="1" max="99" />
        </x-field>
        <x-field label="Gratis (qty)" name="get_qty">
            <x-input name="get_qty" type="number" :value="old('get_qty', $promotion->get_qty ?? 1)" min="1" max="99" />
        </x-field>
        <x-field label="Berlaku untuk kategori" name="product_category_id" class="sm:col-span-2">
            <x-select name="product_category_id">
                <option value="">Semua produk</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected((string) old('product_category_id', $promotion->product_category_id ?? '') === (string) $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </x-select>
        </x-field>
    </div>
    <p class="mt-4 text-xs leading-relaxed text-[#15803D]">
        Contoh Beli 1 Gratis 1 kategori Minuman: pelanggan ambil 2 minuman, bayar 1. Ambil 4 bayar 2.
        Yang digratiskan selalu yang harganya paling murah, dan minuman boleh beda jenis.
    </p>
</div>
