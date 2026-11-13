@extends('layouts.app')
@section('title','Edit Pelanggan')
@section('page-title','Edit Pelanggan')

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head :title="$customer->name" :back="route('customers.show', $customer)" backLabel="Detail Pelanggan" sub="Ubah data pelanggan, lalu simpan perubahan." style="--i:0">
        <x-slot:badge><x-pill :tone="$customer->is_active ? 'green' : 'gray'">{{ $customer->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill></x-slot:badge>
    </x-page-head>
</div>
@include('customers._form', [
    'customer'    => $customer,
    'action'      => route('customers.update', $customer),
    'submitLabel' => 'Simpan Perubahan',
    'cancelUrl'   => route('customers.show', $customer),
])
@endsection
