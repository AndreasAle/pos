@extends('layouts.app')
@section('title', 'Tambah Outlet')
@section('page-title', 'Tambah Outlet')

@section('content')
<div class="max-w-lg">
    <x-card title="Informasi Outlet">
        <form method="POST" action="{{ route('outlets.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Outlet <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                       placeholder="Contoh: Outlet Cabang Selatan">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kode Outlet</label>
                <input type="text" name="code" value="{{ old('code') }}"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                       placeholder="Contoh: CB-01">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon</label>
                <input type="text" name="phone" value="{{ old('phone') }}"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                       placeholder="08xx-xxxx-xxxx">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                <textarea name="address" rows="3"
                          class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                          placeholder="Alamat lengkap outlet">{{ old('address') }}</textarea>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors">
                    Simpan Outlet
                </button>
                <a href="{{ route('outlets.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
