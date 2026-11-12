@extends('layouts.app')
@section('title', 'Piutang')
@section('page-title', 'Piutang Usaha')
@section('page-subtitle', 'Uang yang masih harus diterima dari pelanggan atau pihak lain, beserta umur tagihannya.')
@section('content')
@php $rp = fn ($v) => \App\Support\Money::rp($v); $void = request('status') === 'void'; @endphp
<div class="y-report">
@include('finance._flash')
@include('finance._aging', ['aging' => $aging, 'labels' => $labels, 'title' => 'Umur piutang', 'noun' => 'Piutang'])

<x-panel class="mt-4" flush :title="$void ? 'Piutang dibatalkan' : 'Daftar piutang'" :sub="$void ? 'Dokumen yang dibatalkan tidak dihitung pada ringkasan di atas.' : 'Piutang aktif, terbaru di atas.'">
    <x-slot:actions>
        <a href="{{ route('receivables.index', $void ? [] : ['status' => 'void']) }}" class="inline-flex h-10 items-center rounded-lg border border-[#E5E7EB] bg-white px-4 text-sm font-semibold hover:border-[#16A34A] hover:text-[#15803D]">{{ $void ? 'Lihat aktif' : 'Lihat dibatalkan' }}</a>
        <a href="{{ route('receivables.create') }}" class="inline-flex h-10 items-center rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#15803D]">Catat piutang</a>
    </x-slot:actions>
    @if($receivables->count())
    <div class="overflow-x-auto">
        <table class="w-full min-w-[780px] text-sm">
            <thead><tr><th scope="col" class="px-5 py-3 text-left">No.</th><th scope="col" class="px-5 py-3 text-left">Pihak</th><th scope="col" class="px-5 py-3 text-left">Terbit</th><th scope="col" class="px-5 py-3 text-left">Jatuh tempo</th><th scope="col" class="px-5 py-3 text-right">Jumlah</th><th scope="col" class="px-5 py-3 text-right">Sisa</th><th scope="col" class="px-5 py-3 text-left">Status</th></tr></thead>
            <tbody class="divide-y divide-[#E5E7EB]">
            @foreach($receivables as $r)
                @php
                    $late = ! $void && $r->due_date && $r->due_date->endOfDay()->isPast() && $r->outstanding > 0;
                    $lateDays = $late ? (int) $r->due_date->startOfDay()->diffInDays(now()->startOfDay()) : 0;
                    $pay = ['unpaid' => ['red', 'Belum dibayar'], 'partial' => ['amber', 'Sebagian'], 'paid' => ['green', 'Lunas']][$r->payment_status] ?? ['gray', '-'];
                @endphp
                <tr class="cursor-pointer" onclick="window.location='{{ route('receivables.show', $r) }}'">
                    <td class="px-5 py-3.5"><a href="{{ route('receivables.show', $r) }}" class="font-mono text-[13px] font-semibold text-[#16A34A] hover:underline">{{ $r->receivable_number }}</a></td>
                    <td class="px-5 py-3.5"><span class="font-medium">{{ $r->party_name }}</span>@if($r->description)<span class="block text-xs text-[#64748B]">{{ \Illuminate\Support\Str::limit($r->description, 48) }}</span>@endif</td>
                    <td class="px-5 py-3.5 whitespace-nowrap text-[#475569]">{{ $r->issue_date->format('d M Y') }}</td>
                    <td class="px-5 py-3.5 whitespace-nowrap">{{ $r->due_date?->format('d M Y') ?? '-' }}@if($late)<x-pill tone="red" class="ml-1.5">Lewat {{ $lateDays }} hari</x-pill>@endif</td>
                    <td class="px-5 py-3.5 text-right tabular-nums">{{ $rp((float) $r->amount) }}</td>
                    <td class="px-5 py-3.5 text-right font-bold tabular-nums">{{ $rp($r->outstanding) }}</td>
                    <td class="px-5 py-3.5">@if($void)<x-pill>Dibatalkan</x-pill>@else<x-pill :tone="$pay[0]">{{ $pay[1] }}</x-pill>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="border-t border-[#E5E7EB] px-5 py-3">{{ $receivables->links() }}</div>
    @else
        <x-empty :title="$void ? 'Tidak ada piutang yang dibatalkan' : 'Belum ada piutang'" text="Catat uang yang belum diterima, misalnya pesanan katering atau titipan. Piutang yang jatuh tempo akan ditandai otomatis." icon="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8V6m0 12v-2m9-6a9 9 0 11-18 0 9 9 0 0118 0z">
            @unless($void)<a href="{{ route('receivables.create') }}" class="inline-flex h-10 items-center rounded-lg bg-[#16A34A] px-4 text-sm font-semibold text-white hover:bg-[#15803D]">Catat piutang</a>@endunless
        </x-empty>
    @endif
</x-panel>
<p class="mt-3 text-xs text-[#64748B]">Piutang dicatat manual. Kasir POS belum mendukung penjualan kredit, jadi tidak ada piutang otomatis dari transaksi.</p>
</div>
@endsection
