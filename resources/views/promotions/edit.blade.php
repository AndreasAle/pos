@extends('layouts.app')
@section('title','Edit Promo')
@section('page-title','Edit Promo: ' . $promotion->name)

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head :title="$promotion->name" :back="route('promotions.index')" backLabel="Promo & Diskon" sub="Ubah aturan promo, lalu simpan perubahan." style="--i:0">
        <x-slot:badge><x-pill :tone="$promotion->is_active ? 'green' : 'gray'">{{ $promotion->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill></x-slot:badge>
    </x-page-head>
</div>
@include('promotions._form', [
    'promotion'   => $promotion,
    'action'      => route('promotions.update', $promotion),
    'submitLabel' => 'Simpan Perubahan',
    'cancelUrl'   => route('promotions.index'),
])
@endsection
