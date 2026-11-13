@extends('layouts.app')
@section('title', 'Edit User')
@section('page-title', 'Edit User')

@section('content')
<div class="page text-[#0B1B3F] pt-2 -mb-2">
    <x-page-head :title="$user->name" :back="route('users.index')" backLabel="Manajemen User" sub="Ubah data akun, lalu simpan perubahan." style="--i:0">
        <x-slot:badge><x-pill :tone="$user->is_active ? 'green' : 'red'">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill></x-slot:badge>
    </x-page-head>
</div>
@include('users._form', [
    'user'        => $user,
    'action'      => route('users.update', $user),
    'submitLabel' => 'Simpan Perubahan',
    'cancelUrl'   => route('users.index'),
])
@endsection
