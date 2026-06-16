@extends('layouts.app')
@section('title','Promo & Diskon')
@section('page-title','Promo & Diskon')

@section('content')
<div class="flex items-center justify-between mb-5">
    <p class="text-sm text-gray-500">{{ $promotions->total() }} promo terdaftar</p>
    <a href="{{ route('promotions.create') }}"
       class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Promo
    </a>
</div>

<x-card :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Nama Promo</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Tipe & Nilai</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Min. Order</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Periode</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Outlet</th>
                <th class="text-center px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($promotions as $promo)
            <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="px-5 py-3.5">
                    <p class="font-semibold text-gray-900">{{ $promo->name }}</p>
                    @if($promo->code)
                    <p class="text-xs font-mono text-gray-400 mt-0.5">Kode: {{ $promo->code }}</p>
                    @endif
                </td>
                <td class="px-3 py-3.5">
                    <span class="inline-flex items-center gap-1 text-sm font-bold {{ $promo->type==='percent' ? 'text-blue-700' : 'text-emerald-700' }}">
                        {{ $promo->type === 'percent' ? $promo->value.'%' : 'Rp '.number_format($promo->value,0,',','.') }}
                    </span>
                    <span class="text-xs text-gray-400 ml-1">{{ $promo->type === 'percent' ? 'diskon persen' : 'diskon nominal' }}</span>
                </td>
                <td class="px-3 py-3.5 text-gray-600">
                    {{ $promo->min_order > 0 ? 'Rp '.number_format($promo->min_order,0,',','.') : '—' }}
                </td>
                <td class="px-3 py-3.5 text-gray-600 text-xs">
                    @if($promo->starts_at || $promo->ends_at)
                    {{ $promo->starts_at?->format('d/m/Y') ?? '∞' }} — {{ $promo->ends_at?->format('d/m/Y') ?? '∞' }}
                    @else
                    <span class="text-gray-400">Tidak ada batas</span>
                    @endif
                </td>
                <td class="px-3 py-3.5 text-gray-600">{{ $promo->outlet?->name ?? 'Semua Outlet' }}</td>
                <td class="px-3 py-3.5 text-center">
                    <x-badge :color="$promo->is_active ? 'green' : 'gray'">
                        {{ $promo->is_active ? 'Aktif' : 'Nonaktif' }}
                    </x-badge>
                </td>
                <td class="px-5 py-3.5 text-right">
                    <div class="flex items-center justify-end gap-1.5">
                        <form method="POST" action="{{ route('promotions.toggle', $promo) }}">
                            @csrf @method('PATCH')
                            <button class="text-xs font-medium {{ $promo->is_active ? 'text-orange-500 hover:bg-orange-50' : 'text-emerald-600 hover:bg-emerald-50' }} px-2.5 py-1.5 rounded-lg transition-colors">
                                {{ $promo->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>
                        <a href="{{ route('promotions.edit', $promo) }}"
                           class="text-xs font-medium text-gray-500 hover:text-gray-700 px-2.5 py-1.5 rounded-lg hover:bg-gray-100">Edit</a>
                        <form method="POST" action="{{ route('promotions.destroy', $promo) }}"
                              onsubmit="return confirm('Hapus promo ini?')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-medium text-red-500 px-2.5 py-1.5 rounded-lg hover:bg-red-50">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-gray-400">
                Belum ada promo. <a href="{{ route('promotions.create') }}" class="text-emerald-600 hover:underline">Buat promo pertama</a>
            </td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
<div class="mt-4">{{ $promotions->links() }}</div>
@endsection
