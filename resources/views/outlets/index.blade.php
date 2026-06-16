@extends('layouts.app')
@section('title', 'Outlet')
@section('page-title', 'Manajemen Outlet')

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500">Kelola semua outlet bisnis Anda</p>
    <a href="{{ route('outlets.create') }}"
       class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Tambah Outlet
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($outlets as $outlet)
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-start justify-between mb-3">
            <div>
                <p class="font-semibold text-gray-900">{{ $outlet->name }}</p>
                @if($outlet->code)
                <span class="text-xs text-gray-400 font-mono">{{ $outlet->code }}</span>
                @endif
            </div>
            <x-badge :color="$outlet->is_active ? 'green' : 'gray'">
                {{ $outlet->is_active ? 'Aktif' : 'Nonaktif' }}
            </x-badge>
        </div>
        @if($outlet->address)
        <p class="text-sm text-gray-500 mb-2">{{ $outlet->address }}</p>
        @endif
        @if($outlet->phone)
        <p class="text-sm text-gray-500 mb-3">📞 {{ $outlet->phone }}</p>
        @endif
        <p class="text-xs text-gray-400 mb-4">{{ number_format($outlet->orders_count) }} total transaksi</p>
        <div class="flex items-center gap-2">
            <a href="{{ route('outlets.edit', $outlet) }}"
               class="flex-1 text-center text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-3 py-2 rounded-lg transition-colors">
                Edit
            </a>
            @if($outlet->orders_count == 0)
            <form method="POST" action="{{ route('outlets.destroy', $outlet) }}"
                  onsubmit="return confirm('Hapus outlet ini?')">
                @csrf @method('DELETE')
                <button class="text-sm font-medium text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-2 rounded-lg transition-colors">
                    Hapus
                </button>
            </form>
            @endif
        </div>
    </div>
    @empty
    <div class="col-span-3 text-center py-16 text-gray-400">
        <p class="text-lg">Belum ada outlet</p>
        <p class="text-sm mt-1">Tambahkan outlet untuk mulai beroperasi</p>
    </div>
    @endforelse
</div>
{{ $outlets->links() }}
@endsection
