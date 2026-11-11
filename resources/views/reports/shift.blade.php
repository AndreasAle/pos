@extends('layouts.app')
@section('title','Laporan Shift')
@section('page-title','Laporan Per Shift')
@section('content')
@include('reports._nav')

<div class="flex items-center justify-end gap-2 mb-4">
    <a href="{{ route('reports.shift.export', request()->query()) }}"
       class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-4 py-2 rounded-xl transition-colors">
        📊 Export Excel
    </a>
</div>

@include('reports._filter', ['outlets' => $outlets, 'f' => $f])

<x-card title="Riwayat Shift" :padding="false">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Kasir</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Outlet</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Buka</th>
                <th class="text-left px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Tutup</th>
                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Modal Awal</th>
                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Cash Expected</th>
                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase">Cash Aktual</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Selisih</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($shifts as $s)
            <tr class="hover:bg-gray-50/50">
                <td class="px-5 py-3 font-medium text-gray-900">{{ $s->user->name }}</td>
                <td class="px-3 py-3 text-gray-600">{{ $s->outlet->name }}</td>
                <td class="px-3 py-3 text-gray-600 text-xs">{{ $s->opened_at->format('d/m/Y H:i') }}</td>
                <td class="px-3 py-3 text-gray-600 text-xs">{{ $s->closed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="px-3 py-3 text-right text-gray-700">Rp {{ number_format($s->opening_cash,0,',','.') }}</td>
                <td class="px-3 py-3 text-right text-gray-700">
                    {{ $s->status === 'closed' ? 'Rp '.number_format($s->closing_cash_expected,0,',','.') : '—' }}
                </td>
                <td class="px-3 py-3 text-right text-gray-700">
                    {{ $s->status === 'closed' ? 'Rp '.number_format($s->closing_cash_actual,0,',','.') : '—' }}
                </td>
                <td class="px-5 py-3 text-right font-bold {{ $s->cash_difference < 0 ? 'text-red-600' : ($s->cash_difference > 0 ? 'text-emerald-600' : 'text-gray-500') }}">
                    @if($s->status === 'closed')
                    {{ $s->cash_difference >= 0 ? '+' : '' }}Rp {{ number_format($s->cash_difference,0,',','.') }}
                    @else
                    <x-badge color="green">Aktif</x-badge>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400">Tidak ada data shift</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
<div class="mt-4">{{ $shifts->links() }}</div>
@endsection
