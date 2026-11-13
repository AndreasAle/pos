@extends('layouts.app')
@section('title', 'Tambah Outlet')
@section('page-title', 'Tambah Outlet')

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head title="Outlet Baru" :back="route('outlets.index')" backLabel="Manajemen Outlet" sub="Tambahkan cabang baru untuk mulai mencatat transaksi di sana." style="--i:0" />
</div>
@include('outlets._form', [
    'outlet'      => null,
    'action'      => route('outlets.store'),
    'submitLabel' => 'Simpan Outlet',
    'cancelUrl'   => route('outlets.index'),
])
@endsection
