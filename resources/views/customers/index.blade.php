@extends('layouts.app')
@section('title','Pelanggan')
@section('page-title','Manajemen Pelanggan')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-5">
    <form method="GET" class="flex-1 max-w-sm">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau no. HP..."
                   class="pl-9 pr-4 py-2.5 w-full border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
        </div>
    </form>
    <a href="{{ route('customers.create') }}"
       class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors flex-shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Pelanggan
    </a>
</div>

<x-card :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Nama</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">No. HP</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Total Transaksi</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Total Belanja</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Poin</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($customers as $c)
            <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="px-5 py-3.5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-bold text-emerald-700">{{ substr($c->name,0,1) }}</span>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">{{ $c->name }}</p>
                            @if($c->email)<p class="text-xs text-gray-400">{{ $c->email }}</p>@endif
                        </div>
                    </div>
                </td>
                <td class="px-5 py-3.5 text-gray-600">{{ $c->phone ?: '—' }}</td>
                <td class="px-5 py-3.5 text-right font-medium text-gray-900">{{ number_format($c->total_transactions) }}</td>
                <td class="px-5 py-3.5 text-right font-semibold text-emerald-700">Rp {{ number_format($c->total_spending,0,',','.') }}</td>
                <td class="px-5 py-3.5 text-right">
                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-full px-2 py-0.5">
                        ⭐ {{ number_format($c->loyalty_points) }}
                    </span>
                </td>
                <td class="px-5 py-3.5 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                        <a href="{{ route('customers.show', $c) }}"
                           class="text-xs font-medium text-emerald-600 hover:text-emerald-700 px-2.5 py-1.5 rounded-lg hover:bg-emerald-50 transition-colors">Detail</a>
                        <a href="{{ route('customers.edit', $c) }}"
                           class="text-xs font-medium text-gray-500 hover:text-gray-700 px-2.5 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">Edit</a>
                        <form method="POST" action="{{ route('customers.destroy', $c) }}"
                              onsubmit="return confirm('Hapus pelanggan ini?')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-medium text-red-500 hover:text-red-600 px-2.5 py-1.5 rounded-lg hover:bg-red-50 transition-colors">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400">
                    Belum ada data pelanggan.
                    <a href="{{ route('customers.create') }}" class="text-emerald-600 hover:underline">Tambah pelanggan</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</x-card>
<div class="mt-4">{{ $customers->links() }}</div>
@endsection
