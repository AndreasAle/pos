@extends('layouts.app')
@section('title', 'Edit Outlet')
@section('page-title', 'Edit Outlet')

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head :title="$outlet->name" :back="route('outlets.index')" backLabel="Manajemen Outlet" sub="Ubah data outlet, lalu simpan perubahan." style="--i:0">
        <x-slot:badge><x-pill :tone="$outlet->is_active ? 'green' : 'gray'">{{ $outlet->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill></x-slot:badge>
    </x-page-head>
</div>
@include('outlets._form', [
    'outlet'      => $outlet,
    'action'      => route('outlets.update', $outlet),
    'submitLabel' => 'Simpan Perubahan',
    'cancelUrl'   => route('outlets.index'),
])
@endsection
