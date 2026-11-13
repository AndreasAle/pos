@extends('layouts.app')
@section('title','Edit Bahan')
@section('page-title','Edit: ' . $ingredient->name)

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head :title="$ingredient->name" :back="route('ingredients.show', $ingredient)" backLabel="Detail Bahan" sub="Ubah data bahan baku, lalu simpan perubahan." style="--i:0">
        <x-slot:badge><x-pill :tone="$ingredient->is_active ? 'green' : 'gray'">{{ $ingredient->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill></x-slot:badge>
    </x-page-head>
</div>
@include('inventory.ingredients._form', [
    'ingredient'  => $ingredient,
    'action'      => route('ingredients.update', $ingredient),
    'submitLabel' => 'Simpan Perubahan',
    'cancelUrl'   => route('ingredients.show', $ingredient),
])
@endsection
