@extends('layouts.app')
@section('title','Tambah Bahan Baku')
@section('page-title','Tambah Bahan Baku')

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head title="Bahan Baku Baru" :back="route('ingredients.index')" backLabel="Bahan Baku" sub="Catat bahan baku untuk memantau stok dan modal produk." style="--i:0" />
</div>
@include('inventory.ingredients._form', [
    'ingredient'  => null,
    'action'      => route('ingredients.store'),
    'submitLabel' => 'Simpan Bahan',
    'cancelUrl'   => route('ingredients.index'),
])
@endsection
