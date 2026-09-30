{{-- "Beli X gratis Y" fields, shown only for that promo type. Expects
     $categories and an optional $promotion. --}}
<div x-show="type === 'buy_get'" x-cloak class="space-y-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Beli (qty)</label>
            <input type="number" name="buy_qty" min="1" max="99" value="{{ old('buy_qty', $promotion->buy_qty ?? 1) }}"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Gratis (qty)</label>
            <input type="number" name="get_qty" min="1" max="99" value="{{ old('get_qty', $promotion->get_qty ?? 1) }}"
                   class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Berlaku untuk Kategori</label>
        <select name="product_category_id" class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
            <option value="">-- Semua produk --</option>
            @foreach($categories as $cat)
            <option value="{{ $cat->id }}" {{ (string) old('product_category_id', $promotion->product_category_id ?? '') === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>
    <p class="text-xs text-emerald-700">
        Contoh Beli 1 Gratis 1 kategori Minuman: pelanggan ambil 2 minuman, bayar 1.
        Ambil 4 bayar 2. Yang digratiskan selalu yang harganya paling murah.
        Minuman boleh beda jenis.
    </p>
</div>
