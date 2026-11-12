@extends('layouts.app')
@section('title', 'Hutang')
@section('page-title', 'Hutang Usaha')
@section('page-subtitle', 'Tagihan ke supplier yang belum dibayar, diurutkan menurut umur jatuh tempo.')
@section('content')
@php $rp = fn ($v) => \App\Support\Money::rp($v); @endphp
<div class="y-report">
@include('finance._flash')
@include('finance._aging', ['aging' => $aging, 'labels' => $labels, 'title' => 'Umur hutang ke supplier', 'noun' => 'Hutang'])

<x-panel class="mt-4" flush title="Tagihan belum lunas" sub="Klik nomor pembelian untuk membayar atau melihat rinciannya.">
    <x-slot:actions>
        <a href="{{ route('suppliers.index') }}" class="inline-flex h-10 items-center rounded-lg border border-[#E5E7EB] bg-white px-4 text-sm font-semibold hover:border-[#16A34A] hover:text-[#15803D]">Supplier</a>
        <a href="{{ route('purchases.create') }}" class="inline-flex h-10 items-center rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#15803D]">Catat pembelian</a>
    </x-slot:actions>
    @if(count($aging['rows']))
    <div class="overflow-x-auto">
        <table class="w-full min-w-[780px] text-sm">
            <thead><tr><th scope="col" class="px-5 py-3 text-left">No. pembelian</th><th scope="col" class="px-5 py-3 text-left">Supplier</th><th scope="col" class="px-5 py-3 text-left">Jatuh tempo</th><th scope="col" class="px-5 py-3 text-right">Total</th><th scope="col" class="px-5 py-3 text-right">Dibayar</th><th scope="col" class="px-5 py-3 text-right">Sisa</th><th scope="col" class="px-5 py-3"><span class="sr-only">Aksi</span></th></tr></thead>
            <tbody class="divide-y divide-[#E5E7EB]">
            @foreach($aging['rows'] as $r)
                @php $pct = $r['total'] > 0 ? min(100, $r['paid'] / $r['total'] * 100) : 0; @endphp
                <tr>
                    <td class="px-5 py-3.5"><a href="{{ route('purchases.show', $r['id']) }}" class="font-mono text-[13px] font-semibold text-[#16A34A] hover:underline">{{ $r['number'] }}</a></td>
                    <td class="px-5 py-3.5 font-medium">{{ $r['party'] }}</td>
                    <td class="px-5 py-3.5 whitespace-nowrap">
                        {{ $r['due'] ? $r['due']->format('d M Y') : '-' }}
                        @if($r['days_overdue'] > 0)<x-pill tone="red" class="ml-1.5">Lewat {{ $r['days_overdue'] }} hari</x-pill>
                        @elseif($r['due'])<x-pill tone="green" class="ml-1.5">Belum jatuh tempo</x-pill>@endif
                    </td>
                    <td class="px-5 py-3.5 text-right tabular-nums">{{ $rp($r['total']) }}</td>
                    <td class="px-5 py-3.5 text-right tabular-nums">
                        {{ $rp($r['paid']) }}
                        @if($r['paid'] > 0)<div class="ml-auto mt-1 h-1 w-20 rounded-full bg-slate-100"><div class="h-1 rounded-full bg-[#16A34A]" style="width: {{ $pct }}%"></div></div>@endif
                    </td>
                    <td class="px-5 py-3.5 text-right font-bold tabular-nums">{{ $rp($r['outstanding']) }}</td>
                    <td class="px-5 py-3.5 text-right"><a href="{{ route('purchases.show', $r['id']) }}" class="inline-flex h-9 items-center rounded-lg border border-[#E5E7EB] px-3 text-[13px] font-semibold hover:border-[#16A34A] hover:text-[#15803D]">Bayar</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @else
        <x-empty title="Tidak ada hutang yang belum lunas" text="Pembelian dari supplier yang belum dibayar akan tampil di sini, lengkap dengan umur dan jatuh temponya.">
            <a href="{{ route('purchases.create') }}" class="inline-flex h-10 items-center rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white hover:bg-[#15803D]">Catat pembelian</a>
        </x-empty>
    @endif
</x-panel>
</div>
@endsection
