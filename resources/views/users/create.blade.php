@extends('layouts.app')
@section('title', 'Tambah User')
@section('page-title', 'Tambah User')

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head title="User Baru" :back="route('users.index')" backLabel="Manajemen User" sub="Buat akun staf dan tentukan hak akses mereka." style="--i:0" />
</div>
@include('users._form', [
    'user'        => null,
    'action'      => route('users.store'),
    'submitLabel' => 'Tambah User',
    'cancelUrl'   => route('users.index'),
])
@endsection
