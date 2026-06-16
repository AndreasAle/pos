@extends('layouts.app')
@section('title','Shift Kasir')
@section('page-title','Shift Kasir')
@section('content')
<div class="flex items-center justify-between mb-5">
    @if(!$activeShift)
    <a href="{{ route('shifts.open') }}"
       class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2.5 rounded-xl">
        Buka Shift Baru
    </a>
    @else
    <div class="flex items-center gap-3">
        <span class="flex items-center gap-2 text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-2 rounded-xl">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Shift Aktif · {{ $activeShift->outlet->name }}
        </span>
        <a href="{{ route('shifts.close', $activeShift) }}"
           class="text-sm font-medium text-red-600 border border-red-200 hover:bg-red-50 px-4 py-2 rounded-xl transition-colors">
            Tutup Shift
        </a>
    </div>
    @endif
</div>
<x-card :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Kasir</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Outlet</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Buka</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Tutup</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Modal Awal</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($shifts as $shift)
            <tr class="hover:bg-gray-50/50">
                <td class="px-5 py-3 font-medium text-gray-900">{{ $shift->user->name }}</td>
                <td class="px-5 py-3 text-gray-600">{{ $shift->outlet->name }}</td>
                <td class="px-5 py-3 text-gray-600">{{ $shift->opened_at->format('d/m/Y H:i') }}</td>
                <td class="px-5 py-3 text-gray-600">{{ $shift->closed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="px-5 py-3 text-right">Rp {{ number_format($shift->opening_cash,0,',','.') }}</td>
                <td class="px-5 py-3">
                    <x-badge :color="$shift->status === 'open' ? 'green' : 'gray'">
                        {{ $shift->status === 'open' ? 'Aktif' : 'Tutup' }}
                    </x-badge>
                </td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('shifts.show', $shift) }}" class="text-xs text-emerald-600 hover:underline">Detail</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">Belum ada riwayat shift</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
<div class="mt-4">{{ $shifts->links() }}</div>
@endsection
