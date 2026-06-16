@extends('layouts.app')
@section('title','Cetak Label Barcode')
@section('page-title','Cetak Label Barcode')

@section('content')
<form action="{{ route('barcodes.print') }}" method="GET" target="_blank" id="print-form">
<div class="flex items-center justify-between mb-5">
    <p class="text-sm text-gray-500">Pilih produk yang akan dicetak labelnya</p>
    <div class="flex items-center gap-2">
        <button type="button" onclick="toggleAll()" class="text-xs text-gray-500 border border-gray-200 px-3 py-1.5 rounded-xl hover:bg-gray-50">
            Pilih Semua
        </button>
        <button type="submit"
                class="text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2 rounded-xl transition-colors">
            🖨 Cetak Label
        </button>
    </div>
</div>

<x-card :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="w-10 px-4 py-3"><input type="checkbox" id="check-all" class="h-4 w-4 text-emerald-600 rounded" onchange="toggleAll()"></th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Produk</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">SKU</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500">Harga</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Qty Label</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Preview</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($products as $product)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <input type="checkbox" name="ids[]" value="{{ $product->id }}" class="h-4 w-4 text-emerald-600 rounded product-check">
                </td>
                <td class="px-4 py-3">
                    <p class="font-semibold text-gray-900">{{ $product->name }}</p>
                    @if($product->product_category_id)
                    <p class="text-xs text-gray-400">{{ optional($product->category)->name }}</p>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <span class="font-mono text-xs text-gray-700">{{ $product->sku ?: str_pad($product->id, 8, '0', STR_PAD_LEFT) }}</span>
                </td>
                <td class="px-4 py-3 text-right text-gray-700">Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                <td class="px-4 py-3 text-center">
                    <input type="number" name="qty_{{ $product->id }}" value="1" min="1" max="100"
                           class="w-16 text-center text-sm border border-gray-200 rounded-lg py-1 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </td>
                <td class="px-4 py-3 text-center">
                    <a href="{{ route('barcodes.show', $product) }}" target="_blank"
                       class="text-xs text-emerald-700 hover:underline">Lihat</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</x-card>
</form>

<script>
function toggleAll() {
    const all = document.getElementById('check-all');
    document.querySelectorAll('.product-check').forEach(cb => cb.checked = all.checked);
}
</script>
@endsection
