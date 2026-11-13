@extends('layouts.app')
@section('title', 'Edit Produk')
@section('page-title', 'Edit: ' . $product->name)

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head :title="$product->name" :back="route('products.show', $product)" backLabel="Detail Produk" sub="Ubah data produk, lalu simpan perubahan." style="--i:0">
        <x-slot:badge><x-pill :tone="$product->is_active ? 'green' : 'gray'">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill></x-slot:badge>
    </x-page-head>
</div>
@include('products._form', [
    'product'     => $product,
    'action'      => route('products.update', $product),
    'submitLabel' => 'Simpan Perubahan',
    'cancelUrl'   => route('products.show', $product),
])
@endsection
