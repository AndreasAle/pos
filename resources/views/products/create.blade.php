@extends('layouts.app')
@section('title', 'Tambah Produk')
@section('page-title', 'Tambah Produk')

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head title="Produk Baru" :back="route('products.index')" backLabel="Produk / Menu" sub="Isi data produk, lalu simpan untuk menampilkannya di kasir." style="--i:0" />
</div>
@include('products._form', [
    'product'     => null,
    'action'      => route('products.store'),
    'submitLabel' => 'Simpan Produk',
    'cancelUrl'   => route('products.index'),
])
@endsection
