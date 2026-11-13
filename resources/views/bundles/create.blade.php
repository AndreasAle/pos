@extends('layouts.app')
@section('title', 'Buat Bundle Baru')
@section('page-title', 'Buat Bundle Baru')

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head title="Bundle Baru" :back="route('bundles.index')" backLabel="Bundling Produk" sub="Gabungkan beberapa produk jadi satu paket dengan harga khusus." style="--i:0" />
</div>
@include('bundles._form', [
    'bundle'      => null,
    'action'      => route('bundles.store'),
    'submitLabel' => 'Simpan Bundle',
    'cancelUrl'   => route('bundles.index'),
])
@endsection
