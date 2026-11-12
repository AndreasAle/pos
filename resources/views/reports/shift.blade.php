@extends('layouts.app')
@section('title','Laporan Shift')
@section('page-title','Laporan Shift')
@section('page-subtitle','Riwayat buka dan tutup kasir, lengkap dengan kas yang seharusnya, kas aktual, dan selisihnya.')
@section('content')
@php
    $rp = fn ($v) => 'Rp ' . number_format(abs((float) $v), 0, ',', '.');
    $signed = fn ($v) => ((float) $v < 0 ? '-' : ((float) $v > 0 ? '+' : '')) . 'Rp ' . number_format(abs((float) $v), 0, ',', '.');
    $closed = $shifts->getCollection()->where('status', 'closed');
    $diffTotal = (float) $closed->sum('cash_difference');
    $diffCount = $closed->filter(fn ($s) => (float) $s->cash_difference !== 0.0)->count();
    $activeCount = $shifts->getCollection()->where('status', 'open')->count();
@endphp
<div class="y-report">
@include('reports._nav')
@include('reports._filter', ['outlets' => $outlets, 'f' => $f, 'exports' => [['label' => 'Excel', 'url' => route('reports.shift.export', request()->query())]]])

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kpi label="Total shift" :value="number_format($shifts->total())" sub="Pada periode ini" tone="brand"/>
    <x-kpi label="Sedang berjalan" :value="$activeCount" :sub="$activeCount ? 'Belum ditutup' : 'Semua shift sudah ditutup'"/>
    <x-kpi label="Selisih kas" :value="$signed($diffTotal)" sub="Total pada halaman ini" :tone="$diffTotal < 0 ? 'danger' : 'default'"/>
    <x-kpi label="Shift dengan selisih" :value="$diffCount" :sub="$closed->count() > 0 ? 'dari '.$closed->count().' shift tertutup' : 'Belum ada shift tertutup'" :tone="$diffCount > 0 ? 'warn' : 'default'"/>
</div>

<x-panel class="mt-4" flush title="Riwayat shift" :sub="number_format($shifts->total()).' shift'">
    @if($shifts->count())
    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-sm">
            <thead><tr><th class="px-5 py-3 text-left">Kasir</th><th class="px-4 py-3 text-left">Outlet</th><th class="px-4 py-3 text-left">Buka</th><th class="px-4 py-3 text-left">Tutup</th><th class="px-4 py-3 text-right">Modal awal</th><th class="px-4 py-3 text-right">Kas seharusnya</th><th class="px-4 py-3 text-right">Kas aktual</th><th class="px-5 py-3 text-right">Selisih</th></tr></thead>
            <tbody class="divide-y divide-[#E5E7EB]">
            @foreach($shifts as $s)
                @php $isClosed = $s->status === 'closed'; $d = (float) $s->cash_difference; @endphp
                <tr>
                    <td class="px-5 py-3.5"><span class="flex items-center gap-2.5"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#ECFDF3] text-xs font-bold text-[#16A34A]">{{ strtoupper(mb_substr($s->user->name ?? '-', 0, 1)) }}</span><span class="font-semibold">{{ $s->user->name ?? '-' }}</span></span></td>
                    <td class="px-4 py-3.5 text-[#475569]">{{ $s->outlet->name ?? '-' }}</td>
                    <td class="px-4 py-3.5 whitespace-nowrap text-[#475569]">{{ $s->opened_at->format('d M Y, H:i') }}</td>
                    <td class="px-4 py-3.5 whitespace-nowrap text-[#475569]">{{ $s->closed_at?->format('d M Y, H:i') ?? '—' }}</td>
                    <td class="px-4 py-3.5 text-right tabular-nums">{{ $rp($s->opening_cash) }}</td>
                    <td class="px-4 py-3.5 text-right tabular-nums">{{ $isClosed ? $rp($s->closing_cash_expected) : '—' }}</td>
                    <td class="px-4 py-3.5 text-right tabular-nums">{{ $isClosed ? $rp($s->closing_cash_actual) : '—' }}</td>
                    <td class="px-5 py-3.5 text-right">
                        @if($isClosed)
                            <span class="font-bold tabular-nums {{ $d < 0 ? 'text-red-600' : ($d > 0 ? 'text-amber-600' : 'text-[#64748B]') }}">{{ $d == 0 ? 'Pas' : $signed($d) }}</span>
                        @else
                            <x-pill tone="green">Aktif</x-pill>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="border-t border-[#E5E7EB] px-5 py-3">{{ $shifts->links() }}</div>
    @else
        <x-empty title="Belum ada shift pada periode ini" text="Shift muncul setelah kasir membuka kas. Ubah rentang tanggal untuk melihat riwayat lain." icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
    @endif
</x-panel>
</div>
@endsection
