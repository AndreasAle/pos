@extends('layouts.app')
@section('title','Cetak Label Barcode')
@section('page-title','Cetak Label Barcode')

@section('content')
<form action="{{ route('barcodes.print') }}" method="GET" target="_blank" id="print-form" class="page text-[#0B1B3F] pt-2">

    <x-page-head title="Cetak Label Barcode" :back="route('products.index')" backLabel="Produk / Menu" sub="Pilih produk dan jumlah label yang akan dicetak." style="--i:0">
        <x-slot:actions>
            <x-btn variant="secondary" onclick="toggleAll(true)">Pilih Semua</x-btn>
            <x-btn type="submit">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak Label
            </x-btn>
        </x-slot:actions>
    </x-page-head>

    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:1">
        @if($products->count())
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                        <th class="w-12 px-6 py-3.5"><input type="checkbox" id="check-all" class="h-4 w-4 rounded border-[#E5E7EB] accent-[#16A34A]" onchange="toggleAll()" aria-label="Pilih semua"></th>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Produk</th>
                        <th class="text-left px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">SKU</th>
                        <th class="text-right px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Harga</th>
                        <th class="text-center px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Qty Label</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Preview</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                    @foreach($products as $product)
                    <tr class="hover:bg-[#ECFDF3]/50 transition-colors">
                        <td class="px-6 py-4"><input type="checkbox" name="ids[]" value="{{ $product->id }}" class="product-check h-4 w-4 rounded border-[#E5E7EB] accent-[#16A34A]" aria-label="Pilih {{ $product->name }}"></td>
                        <td class="px-4 py-4">
                            <p class="font-semibold">{{ $product->name }}</p>
                            @if($product->product_category_id)<p class="text-xs text-[#64748B]">{{ optional($product->category)->name }}</p>@endif
                        </td>
                        <td class="px-4 py-4 font-mono text-xs text-slate-700">{{ $product->sku ?: str_pad($product->id, 8, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-4 text-right font-semibold tabular-nums">Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                        <td class="px-4 py-4 text-center">
                            <input type="number" name="qty_{{ $product->id }}" value="1" min="1" max="100"
                                   class="h-9 w-20 rounded-lg border border-[#E5E7EB] text-center text-sm focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30">
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('barcodes.show', $product) }}" target="_blank" class="text-[13px] font-semibold text-[#16A34A] hover:underline">Lihat</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <x-empty title="Belum ada produk" text="Tambahkan produk dulu sebelum mencetak label." icon="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
            <x-btn :href="route('products.create')">Tambah produk</x-btn>
        </x-empty>
        @endif
    </section>
</form>

<script>
function toggleAll(force) {
    const all = document.getElementById('check-all');
    if (force === true) all.checked = true;
    document.querySelectorAll('.product-check').forEach(cb => cb.checked = all.checked);
}
</script>
@endsection
