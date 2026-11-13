@extends('layouts.app')
@section('title','Detail Shift')
@section('page-title','Detail Shift')

@section('content')
@php
    $rp       = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $isOpen   = $shift->status === 'open';
    $label    = 'text-xs font-semibold uppercase tracking-wider text-[#64748B]';
    $methodSum = max(1, (float) collect($byMethod)->sum());
    $diff     = (float) $shift->cash_difference;
    $diffTone = $diff < 0 ? 'danger' : ($diff > 0 ? 'warn' : 'brand');
    $duration = null;
    if ($shift->opened_at) {
        $end = $shift->closed_at ?? now();
        $mins = $shift->opened_at->diffInMinutes($end);
        $duration = intdiv($mins, 60) . ' jam ' . ($mins % 60) . ' menit';
    }
@endphp

<div class="page text-[#0B1B3F] pt-2">

    {{-- Header --}}
    <div class="fade-up flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-5" style="--i:0">
        <div class="min-w-0">
            <a href="{{ route('shifts.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#64748B] hover:text-[#16A34A] transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Riwayat Shift
            </a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h2 class="text-xl sm:text-2xl font-bold tracking-tight">{{ $shift->outlet->name ?? 'Shift Kasir' }}</h2>
                <x-pill :tone="$isOpen ? 'green' : 'gray'">{{ $isOpen ? 'Sedang berjalan' : 'Selesai' }}</x-pill>
            </div>
            <p class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-[#64748B]">
                @if($shift->user)
                <span class="inline-flex items-center gap-1.5 font-semibold text-[#0B1B3F]">
                    <span class="w-6 h-6 rounded-full bg-[#ECFDF3] text-[#16A34A] text-[11px] font-bold flex items-center justify-center">{{ strtoupper(mb_substr($shift->user->name, 0, 1)) }}</span>
                    {{ $shift->user->name }}
                </span>
                <span>&middot;</span>
                @endif
                <span>{{ $shift->opened_at?->format('d M Y, H:i') }}{{ $shift->closed_at ? ' – ' . $shift->closed_at->format('H:i') : '' }}</span>
                @if($duration)<span>&middot;</span><span>{{ $duration }}</span>@endif
            </p>
        </div>

        @if($isOpen && $shift->user_id === auth()->id())
        <a href="{{ route('shifts.close', $shift) }}" class="h-10 inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-red-700 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Tutup Shift
        </a>
        @endif
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-4">
        <x-kpi class="fade-up" style="--i:1" tone="brand" label="Total Penjualan" :value="$rp($totalSales)" />
        <x-kpi class="fade-up" style="--i:2" label="Total Transaksi" :value="number_format($totalOrders)" />
        <x-kpi class="fade-up" style="--i:3" label="Total Cash" :value="$rp($totalCash)" />
        <x-kpi class="fade-up" style="--i:4" label="Modal Awal" :value="$rp($shift->opening_cash)" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
        {{-- Per metode --}}
        <x-panel title="Penjualan per Metode Bayar" class="fade-up" style="--i:5">
            @if(count($byMethod))
            <ul class="space-y-4">
                @foreach($byMethod as $method => $amount)
                <li>
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="font-semibold">{{ \App\Support\ReceiptOptions::paymentLabel($method) }}</span>
                        <span class="font-bold tabular-nums">{{ $rp($amount) }}</span>
                    </div>
                    <div class="mt-2 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full bg-[#16A34A]" style="width: {{ max(3, round($amount / $methodSum * 100)) }}%"></div>
                    </div>
                </li>
                @endforeach
            </ul>
            @else
            <x-empty title="Belum ada pembayaran" text="Rincian metode bayar muncul setelah ada transaksi lunas." class="py-8" />
            @endif
        </x-panel>

        {{-- Rekap penutupan --}}
        <x-panel title="Rekap Penutupan" class="fade-up" style="--i:6">
            @if($shift->status === 'closed')
            <dl class="grid grid-cols-2 gap-x-6 gap-y-5 text-sm">
                <div>
                    <dt class="{{ $label }}">Cash Expected</dt>
                    <dd class="mt-1.5 text-lg font-bold tabular-nums">{{ $rp($shift->closing_cash_expected) }}</dd>
                </div>
                <div>
                    <dt class="{{ $label }}">Cash Actual</dt>
                    <dd class="mt-1.5 text-lg font-bold tabular-nums">{{ $rp($shift->closing_cash_actual) }}</dd>
                </div>
            </dl>
            <div class="mt-5 flex items-center justify-between rounded-lg border px-4 py-3.5
                        {{ $diff < 0 ? 'border-red-200 bg-red-50' : ($diff > 0 ? 'border-amber-200 bg-amber-50' : 'border-[#BBF7D0] bg-[#ECFDF3]') }}">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider {{ $diff < 0 ? 'text-red-700' : ($diff > 0 ? 'text-amber-800' : 'text-[#15803D]') }}">Selisih kas</p>
                    <p class="mt-0.5 text-xs {{ $diff < 0 ? 'text-red-600' : ($diff > 0 ? 'text-amber-700' : 'text-[#15803D]') }}">
                        {{ $diff < 0 ? 'Kas kurang dari seharusnya' : ($diff > 0 ? 'Kas lebih dari seharusnya' : 'Kas sesuai dengan catatan') }}
                    </p>
                </div>
                <p class="text-xl font-extrabold tabular-nums {{ $diff < 0 ? 'text-red-700' : ($diff > 0 ? 'text-amber-800' : 'text-[#15803D]') }}">
                    {{ $diff > 0 ? '+' : ($diff < 0 ? '-' : '') }}{{ $rp(abs($diff)) }}
                </p>
            </div>
            @if($shift->notes)
            <div class="mt-4 rounded-lg bg-slate-50 border border-[#E5E7EB] p-3.5">
                <p class="{{ $label }}">Catatan</p>
                <p class="mt-1 text-sm text-slate-700">{{ $shift->notes }}</p>
            </div>
            @endif
            @else
            <x-empty title="Shift belum ditutup" text="Rekap kas muncul setelah shift ditutup." icon="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" class="py-8" />
            @endif
        </x-panel>
    </div>

    {{-- Transaksi --}}
    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:7">
        <div class="flex items-center justify-between gap-3 border-b border-[#E5E7EB] px-5 py-4">
            <h2 class="text-base font-semibold">Transaksi dalam Shift</h2>
            <x-pill tone="green">{{ $shift->orders->count() }} order</x-pill>
        </div>

        @if($shift->orders->count())
        {{-- Tabel (tablet & desktop) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">No. Order</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Pembayaran</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Status</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Total</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Waktu</th>
                        <th class="px-6 py-3.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                    @foreach($shift->orders as $order)
                    <tr class="group hover:bg-[#ECFDF3]/50 transition-colors cursor-pointer" onclick="window.location='{{ route('orders.show', $order) }}'">
                        <td class="px-6 py-4"><a href="{{ route('orders.show', $order) }}" class="font-mono text-[13px] font-semibold text-[#16A34A] hover:underline">{{ $order->order_number }}</a></td>
                        <td class="px-6 py-4 capitalize text-slate-700">{{ $order->payment_method }}</td>
                        <td class="px-6 py-4"><x-order-status :status="$order->status" /></td>
                        <td class="px-6 py-4 text-right font-bold tabular-nums">{{ $rp($order->grand_total) }}</td>
                        <td class="px-6 py-4 text-right text-[#64748B]">{{ $order->created_at->format('H:i') }}</td>
                        <td class="px-6 py-4 text-right">
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-[#64748B] group-hover:text-[#16A34A] transition-colors">
                                Detail <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Daftar kartu (mobile) --}}
        <ul class="md:hidden divide-y divide-[#E5E7EB]">
            @foreach($shift->orders as $order)
            <li>
                <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between gap-3 px-5 py-4 active:bg-[#ECFDF3]/60">
                    <div class="min-w-0">
                        <p class="font-mono text-[13px] font-semibold text-[#16A34A]">{{ $order->order_number }}</p>
                        <p class="mt-1 text-xs text-[#64748B] capitalize">{{ $order->payment_method }} &middot; {{ $order->created_at->format('H:i') }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="font-bold tabular-nums">{{ $rp($order->grand_total) }}</p>
                        <div class="mt-1"><x-order-status :status="$order->status" /></div>
                    </div>
                </a>
            </li>
            @endforeach
        </ul>
        @else
        <x-empty title="Belum ada transaksi" text="Transaksi yang tercatat selama shift ini akan muncul di sini." />
        @endif
    </section>
</div>
@endsection
