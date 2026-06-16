@extends('layouts.app')
@section('title', 'Edit Outlet')
@section('page-title', 'Edit Outlet')

@section('content')
<div class="max-w-lg">
    <x-card title="Edit Outlet: {{ $outlet->name }}">
        <form method="POST" action="{{ route('outlets.update', $outlet) }}" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Outlet <span class="text-red-500">*</span></label>
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
                <textarea name="address" rows="3"
                          class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">{{ old('address', $outlet->address) }}</textarea>
            </div>
            <div class="flex items-center gap-3">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" class="h-4 w-4 text-emerald-600 rounded"
                       {{ old('is_active', $outlet->is_active) ? 'checked' : '' }}>
                <label for="is_active" class="text-sm text-gray-700">Outlet Aktif</label>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition-colors">
                    Simpan Perubahan
                </button>
                <a href="{{ route('outlets.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Batal</a>
            </div>
        </form>
    </x-card>
</div>
@endsection
