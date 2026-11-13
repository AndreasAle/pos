@extends('layouts.app')
@section('title','Tambah Pelanggan')
@section('page-title','Tambah Pelanggan')

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head title="Pelanggan Baru" :back="route('customers.index')" backLabel="Pelanggan" sub="Simpan data pelanggan untuk melacak belanja dan poin loyalitas." style="--i:0" />
</div>
@include('customers._form', [
    'customer'    => null,
    'action'      => route('customers.store'),
    'submitLabel' => 'Simpan Pelanggan',
    'cancelUrl'   => route('customers.index'),
])
@endsection
