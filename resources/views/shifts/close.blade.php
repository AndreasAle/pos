@extends('layouts.app')
@section('title','Tutup Shift')
@section('page-title','Tutup Shift')

@section('content')
@php
    $rp        = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $label     = 'text-xs font-semibold uppercase tracking-wider text-[#64748B]';
    $totalSale = (float) array_sum($byMethod);
    $methodSum = max(1, $totalSale);
@endphp

<div class="page text-[#0B1B3F] pt-2 max-w-5xl mx-auto">

    {{-- Header --}}
    <div class="fade-up mb-5" style="--i:0">
        <a href="{{ route('shifts.show', $shift) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#64748B] hover:text-[#16A34A] transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Detail Shift
        </a>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <h2 class="text-xl sm:text-2xl font-bold tracking-tight">Tutup Shift Kasir</h2>
            <x-pill tone="green">{{ $shift->outlet->name ?? 'Shift' }}</x-pill>
        </div>
        <p class="mt-1 text-sm text-[#64748B]">Hitung uang tunai di laci, lalu cocokkan dengan catatan penjualan sebelum menutup shift.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)] gap-4 items-start">

        {{-- Kiri: ringkasan --}}
        <div class="space-y-4 min-w-0">
            {{-- Per metode --}}
            <x-panel title="Penjualan per Metode Bayar" :sub="number_format($totalOrders) . ' transaksi dalam shift ini'" class="fade-up" style="--i:1">
                @if(count($byMethod))
                <ul class="space-y-4">
                    @foreach($byMethod as $method => $amount)
                    <li>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="flex items-center gap-2 font-semibold">
                                {{ \App\Support\ReceiptOptions::paymentLabel($method) }}
                                @if($method === 'cash')<x-pill tone="green">Masuk laci</x-pill>@endif
                            </span>
                            <span class="font-bold tabular-nums">{{ $rp($amount) }}</span>
                        </div>
                        <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full {{ $method === 'cash' ? 'bg-[#16A34A]' : 'bg-slate-300' }}" style="width: {{ $amount > 0 ? max(3, round($amount / $methodSum * 100)) : 0 }}%"></div>
                        </div>
                    </li>
                    @endforeach
                </ul>
                <div class="mt-5 flex items-center justify-between border-t border-[#E5E7EB] pt-4">
                    <span class="text-sm font-semibold text-[#64748B]">Total Penjualan</span>
                    <span class="text-xl font-extrabold tabular-nums">{{ $rp($totalSale) }}</span>
                </div>
                @else
                <x-empty title="Belum ada penjualan" text="Belum ada transaksi lunas di shift ini." class="py-8" />
                @endif
                <p class="mt-4 text-xs text-[#64748B]">Cocokkan QRIS dan transfer dengan mutasi di aplikasi bank atau e-wallet.</p>
            </x-panel>

            {{-- Uang di laci --}}
            <section class="fade-up rounded-xl border border-[#BBF7D0] bg-gradient-to-br from-[#ECFDF3] to-white p-5 sm:p-6" style="--i:2">
                <p class="text-xs font-semibold uppercase tracking-wider text-[#15803D]">Uang di Laci</p>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-[#64748B]">Modal awal</dt><dd class="font-semibold tabular-nums">{{ $rp($shift->opening_cash) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#64748B]">+ Penjualan tunai</dt><dd class="font-semibold tabular-nums text-[#15803D]">{{ $rp($byMethod['cash'] ?? 0) }}</dd></div>
                </dl>
                <div class="mt-4 flex items-center justify-between border-t border-[#BBF7D0] pt-4">
                    <span class="text-sm font-semibold">Seharusnya di laci</span>
                    <span class="text-2xl font-extrabold tracking-tight tabular-nums">{{ $rp($expectedCash) }}</span>
                </div>
                <p class="mt-3 text-xs text-[#64748B]">QRIS, transfer, dan metode non-tunai tidak masuk laci.</p>
            </section>
        </div>

        {{-- Kanan: form --}}
        <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white p-5 sm:p-6 lg:sticky lg:top-20" style="--i:2"
                 x-data="{ expected: {{ (int) $expectedCash }}, actual: {{ (int) old('closing_cash_actual', $expectedCash) }},
                           get diff() { return Number(this.actual || 0) - this.expected },
                           fmt(v) { return 'Rp ' + Math.abs(Number(v || 0)).toLocaleString('id-ID') } }">
            <h2 class="text-lg font-semibold">Hitung Kas Akhir</h2>
            <p class="mt-1 text-sm text-[#64748B]">Masukkan jumlah uang tunai yang benar-benar ada di laci.</p>

            <form method="POST" action="{{ route('shifts.close.store', $shift) }}" class="mt-5 space-y-5">
                @csrf
                <div>
                    <label for="closing_cash_actual" class="block text-sm font-semibold mb-1.5">Cash Aktual di Laci <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-[#64748B]">Rp</span>
                        <input id="closing_cash_actual" type="number" name="closing_cash_actual" min="0" step="1" required inputmode="numeric" x-model.number="actual"
                               class="w-full h-11 pl-11 pr-3.5 rounded-lg border border-[#E5E7EB] bg-white text-base font-semibold focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30 transition">
                    </div>
                    <button type="button" @click="actual = expected" class="mt-2 text-[13px] font-semibold text-[#16A34A] hover:text-[#15803D] transition-colors">Isi sesuai seharusnya</button>
                </div>

                {{-- Selisih langsung --}}
                <div class="rounded-lg border px-4 py-3.5 transition-colors"
                     :class="diff === 0 ? 'border-[#BBF7D0] bg-[#ECFDF3]' : (diff < 0 ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50')">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider"
                               :class="diff === 0 ? 'text-[#15803D]' : (diff < 0 ? 'text-red-700' : 'text-amber-800')">Selisih kas</p>
                            <p class="mt-0.5 text-xs"
                               :class="diff === 0 ? 'text-[#15803D]' : (diff < 0 ? 'text-red-600' : 'text-amber-700')"
                               x-text="diff === 0 ? 'Kas sesuai dengan catatan' : (diff < 0 ? 'Kas kurang dari seharusnya' : 'Kas lebih dari seharusnya')"></p>
                        </div>
                        <p class="text-xl font-extrabold tabular-nums"
                           :class="diff === 0 ? 'text-[#15803D]' : (diff < 0 ? 'text-red-700' : 'text-amber-800')"
                           x-text="(diff > 0 ? '+' : (diff < 0 ? '-' : '')) + fmt(diff)"></p>
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-semibold mb-1.5">Catatan <span class="font-normal text-[#64748B]">(opsional)</span></label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Misalnya alasan selisih atau kejadian selama shift"
                              class="w-full px-3.5 py-2.5 rounded-lg border border-[#E5E7EB] text-sm focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30 transition">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="w-full h-12 inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold shadow-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Tutup Shift Sekarang
                </button>
                <a href="{{ route('shifts.show', $shift) }}" class="block text-center text-sm font-semibold text-[#64748B] hover:text-[#0B1B3F]">Batal</a>
            </form>
        </section>
    </div>
</div>
@endsection
