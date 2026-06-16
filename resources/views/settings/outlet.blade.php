@extends('layouts.app')
@section('title','Pengaturan Outlet')
@section('page-title','Pengaturan Outlet')
@section('content')
@include('settings._nav')

<div class="space-y-4">
    @forelse($outlets as $outlet)
    <x-card title="{{ $outlet->name }} {{ $outlet->code ? '('.$outlet->code.')' : '' }}">
        <form method="POST" action="{{ route('settings.outlet.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Outlet</label>
                    <input type="text" name="name" value="{{ old('name', $outlet->name) }}" required
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kode Outlet</label>
                    <input type="text" name="code" value="{{ old('code', $outlet->code) }}"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon</label>
                    <input type="text" name="phone" value="{{ old('phone', $outlet->phone) }}"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                    <input type="text" name="address" value="{{ old('address', $outlet->address) }}"
                           class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>
            <button type="submit"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-xl text-sm transition-colors">
                Simpan Outlet
            </button>
        </form>
    </x-card>
    @empty
    <p class="text-sm text-gray-400">Belum ada outlet. <a href="{{ route('outlets.create') }}" class="text-emerald-600 hover:underline">Tambah outlet</a></p>
    @endforelse
</div>
@endsection
