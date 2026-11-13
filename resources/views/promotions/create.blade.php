@extends('layouts.app')
@section('title','Tambah Promo')
@section('page-title','Tambah Promo')

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head title="Promo Baru" :back="route('promotions.index')" backLabel="Promo & Diskon" sub="Atur jenis diskon, syarat, dan masa berlakunya." style="--i:0" />
</div>
@include('promotions._form', [
    'promotion'   => null,
    'action'      => route('promotions.store'),
    'submitLabel' => 'Simpan Promo',
    'cancelUrl'   => route('promotions.index'),
])
@endsection
