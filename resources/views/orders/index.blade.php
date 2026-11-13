@extends('layouts.app')
@section('title','Riwayat Order')
@section('page-title','Riwayat Order')

@section('content')
@php
    $tabs        = ['' => 'Semua', 'paid' => 'Lunas', 'draft' => 'Draft', 'cancelled' => 'Dibatalkan', 'refunded' => 'Refund'];
    $current     = (string) request('status', '');
    $keep        = request()->except('status', 'page');
    $hasFilter   = request()->hasAny(['status', 'date_from', 'date_to']) && (request('status') || request('date_from') || request('date_to'));
    $rp          = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
@endphp

<div class="page text-[#0B1B3F] pt-2">

    {{-- Sub judul + jumlah --}}
    <div class="fade-up flex flex-wrap items-end justify-between gap-2 mb-4" style="--i:0">
        <p class="text-sm text-[#64748B]">Semua transaksi toko, lengkap dengan status dan metode pembayaran.</p>
        <span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
            <span class="w-1.5 h-1.5 rounded-full bg-[#16A34A]"></span>{{ number_format($orders->total()) }} order
        </span>
    </div>

    {{-- Filter --}}
    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white p-3 sm:p-4 mb-4" style="--i:1">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            {{-- Tab status --}}
            <div class="flex gap-1 p-1 rounded-lg bg-slate-50 border border-[#E5E7EB] overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="tablist">
                @foreach($tabs as $v => $l)
                <a href="{{ route('orders.index', $v === '' ? $keep : array_merge($keep, ['status' => $v])) }}" role="tab"
                   class="shrink-0 h-9 px-3.5 inline-flex items-center rounded-md text-[13px] font-semibold transition-all
                          {{ $current === $v ? 'bg-white text-[#16A34A] shadow-sm ring-1 ring-[#E5E7EB]' : 'text-[#64748B] hover:text-[#0B1B3F]' }}">{{ $l }}</a>
                @endforeach
            </div>

            {{-- Tanggal --}}
            <form method="GET" class="flex flex-wrap items-center gap-2 lg:ml-auto">
                @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                <input type="date" name="date_from" value="{{ request('date_from') }}" onchange="this.form.submit()"
                       class="h-10 w-full sm:w-auto rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30 transition">
                <span class="hidden sm:inline text-sm text-[#64748B]">s/d</span>
                <input type="date" name="date_to" value="{{ request('date_to') }}" onchange="this.form.submit()"
                       class="h-10 w-full sm:w-auto rounded-lg border border-[#E5E7EB] bg-white px-3 text-sm focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30 transition">
                @if($hasFilter)
                <a href="{{ route('orders.index') }}" class="h-10 inline-flex items-center gap-1.5 px-3 rounded-lg text-sm font-semibold text-[#64748B] hover:bg-slate-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>Reset
                </a>
                @endif
            </form>
        </div>
    </section>

    {{-- Daftar order --}}
    <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:2">
        @if($orders->count())
        {{-- Tabel (tablet & desktop) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">No. Order</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Kasir</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Pembayaran</th>
                        <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Status</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Total</th>
                        <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Waktu</th>
                        <th class="px-6 py-3.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5E7EB]">
                    @foreach($orders as $order)
                    <tr class="group hover:bg-[#ECFDF3]/50 transition-colors cursor-pointer" onclick="window.location='{{ route('orders.show', $order) }}'">
                        <td class="px-6 py-4">
                            <a href="{{ route('orders.show', $order) }}" class="font-mono text-[13px] font-semibold text-[#16A34A] hover:underline">{{ $order->order_number }}</a>
                        </td>
                        <td class="px-6 py-4">
                            <span class="flex items-center gap-2.5 text-slate-700">
                                <span class="w-7 h-7 rounded-full bg-[#ECFDF3] text-[#16A34A] text-xs font-bold flex items-center justify-center">{{ strtoupper(substr($order->user->name ?? '-', 0, 1)) }}</span>
                                {{ $order->user->name ?? '-' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 capitalize text-slate-600">{{ $order->payment_method ?: '-' }}</td>
                        <td class="px-6 py-4">
                            <x-order-status :status="$order->status"/>
                        </td>
                        <td class="px-6 py-4 text-right font-bold">{{ $rp($order->grand_total) }}</td>
                        <td class="px-6 py-4 text-right text-[#64748B]">{{ $order->created_at->format('d M, H:i') }}</td>
                        <td class="px-6 py-4 text-right">
                            <span class="inline-flex items-center gap-1 text-[13px] font-semibold text-[#16A34A] opacity-70 group-hover:opacity-100">Detail
                                <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Kartu (mobile) --}}
        <div class="md:hidden divide-y divide-[#E5E7EB]">
            @foreach($orders as $order)
            <a href="{{ route('orders.show', $order) }}" class="block px-4 py-3.5 active:bg-[#ECFDF3]/60">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-mono text-[13px] font-semibold text-[#16A34A] truncate">{{ $order->order_number }}</span>
                    <x-order-status :status="$order->status"/>
                </div>
                <div class="mt-1.5 flex items-end justify-between gap-2">
                    <div class="text-sm text-[#64748B] min-w-0">
                        <p class="truncate">{{ $order->user->name ?? '-' }} &middot; <span class="capitalize">{{ $order->payment_method ?: '-' }}</span></p>
                        <p class="text-xs">{{ $order->created_at->format('d M, H:i') }}</p>
                    </div>
                    <p class="text-base font-bold shrink-0">{{ $rp($order->grand_total) }}</p>
                </div>
            </a>
            @endforeach
        </div>
        @else
        {{-- Empty state --}}
        <div class="flex flex-col items-center text-center px-6 py-14">
            <svg class="w-28 h-28 float" viewBox="0 0 112 112" fill="none" aria-hidden="true">
                <ellipse cx="56" cy="100" rx="30" ry="5" fill="#16A34A" opacity=".12"/>
                <path d="M30 14h52a4 4 0 014 4v76l-8-5-7 5-7-5-7 5-7-5-7 5-7-5-8 5V18a4 4 0 014-4z" fill="#ECFDF3" stroke="#BBF7D0" stroke-width="2" stroke-linejoin="round"/>
                <path d="M40 34h32M40 46h32M40 58h18" stroke="#86EFAC" stroke-width="3.5" stroke-linecap="round"/>
                <path class="line-draw" d="M40 72l8 6 14-14" stroke="#16A34A" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" opacity=".9"/>
                <g class="twinkle"><path d="M92 22l2 4.6 4.6 2-4.6 2-2 4.6-2-4.6-4.6-2 4.6-2z" fill="#4ADE80"/></g>
                <g class="twinkle" style="animation-delay:1s"><path d="M18 52l1.5 3.5 3.5 1.5-3.5 1.5L18 62l-1.5-3.5L13 57l3.5-1.5z" fill="#BBF7D0"/></g>
            </svg>
            <p class="mt-3 text-base font-semibold">{{ $hasFilter ? 'Tidak ada order yang cocok' : 'Belum ada order' }}</p>
            <p class="mt-1 text-sm text-[#64748B] max-w-xs">{{ $hasFilter ? 'Coba ubah status atau rentang tanggal.' : 'Order akan muncul di sini setelah kasir menyelesaikan transaksi pertama.' }}</p>
            <div class="mt-5 flex flex-wrap justify-center gap-2">
                @if($hasFilter)
                <a href="{{ route('orders.index') }}" class="h-10 inline-flex items-center px-4 rounded-lg border border-[#E5E7EB] text-sm font-semibold hover:bg-slate-50 transition-colors">Reset filter</a>
                @endif
                @if(in_array(auth()->user()->role, ['owner','admin','cashier']))
                <a href="{{ route('pos.index') }}" class="h-10 inline-flex items-center gap-2 px-4 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors shadow-sm">Buka Kasir</a>
                @endif
            </div>
        </div>
        @endif
    </section>

    @if($orders->hasPages())
    <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
