@extends('layouts.app')
@section('title', 'Edit Bundle')
@section('page-title', 'Edit Bundle')

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head :title="$bundle->name" :back="route('bundles.index')" backLabel="Bundling Produk" sub="Ubah isi paket atau harganya, lalu simpan perubahan." style="--i:0">
        <x-slot:badge><x-pill :tone="$bundle->is_active ? 'green' : 'gray'">{{ $bundle->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill></x-slot:badge>
    </x-page-head>
</div>
@include('bundles._form', [
    'bundle'      => $bundle,
    'action'      => route('bundles.update', $bundle),
    'submitLabel' => 'Simpan Perubahan',
    'cancelUrl'   => route('bundles.index'),
])
@endsection
