@extends('layouts.app')
@section('title', $customer->name)
@section('page-title', $customer->name)

@section('content')
@php
    $rp    = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $label = 'text-xs font-semibold uppercase tracking-wider text-[#64748B]';
    $orders = $customer->orders->sortByDesc('created_at');
@endphp

<div class="page text-[#0B1B3F] pt-2">

    <x-page-head :title="$customer->name" :back="route('customers.index')" backLabel="Pelanggan" style="--i:0">
        <x-slot:badge><x-pill :tone="$customer->is_active ? 'green' : 'gray'">{{ $customer->is_active ? 'Aktif' : 'Nonaktif' }}</x-pill></x-slot:badge>
        <x-slot:actions>
            <x-btn :href="route('customers.edit', $customer)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15H9v-2.8l8.6-8.6z"/></svg>
                Edit
            </x-btn>
        </x-slot:actions>
    </x-page-head>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-4">
        <x-kpi class="fade-up" style="--i:1" label="Total Transaksi" :value="number_format($customer->total_transactions)" />
        <x-kpi class="fade-up" style="--i:2" tone="brand" label="Total Belanja" :value="$rp($customer->total_spending)" />
        <x-kpi class="fade-up" style="--i:3" label="Poin Loyalitas" :value="number_format($customer->loyalty_points) . ' poin'" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

        {{-- Kiri --}}
        <div class="space-y-4">
            <x-panel title="Info Pelanggan" class="fade-up" style="--i:2">
                <div class="mb-5 flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-[#ECFDF3] text-lg font-bold text-[#16A34A]">{{ strtoupper(mb_substr($customer->name, 0, 1)) }}</span>
                    <p class="font-semibold">{{ $customer->name }}</p>
                </div>
                <dl class="space-y-4 text-sm">
                    <div><dt class="{{ $label }}">No. HP</dt><dd class="mt-1 font-medium">{{ $customer->phone ?: '-' }}</dd></div>
                    <div><dt class="{{ $label }}">Email</dt><dd class="mt-1 break-all font-medium">{{ $customer->email ?: '-' }}</dd></div>
                    <div><dt class="{{ $label }}">Bergabung</dt><dd class="mt-1 font-medium">{{ $customer->created_at->format('d M Y') }}</dd></div>
                </dl>
            </x-panel>

            @if($customer->points->count())
            <x-panel title="Riwayat Poin" :flush="true" class="fade-up" style="--i:3">
                <ul class="max-h-72 divide-y divide-[#E5E7EB] overflow-y-auto">
                    @foreach($customer->points->sortByDesc('created_at')->take(20) as $pt)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $pt->description ?: ucfirst($pt->type) }}</p>
                            <p class="text-xs text-[#64748B]">{{ $pt->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <span class="shrink-0 text-sm font-bold tabular-nums {{ $pt->type === 'redeem' ? 'text-red-600' : 'text-[#15803D]' }}">{{ $pt->type === 'redeem' ? '-' : '+' }}{{ number_format($pt->points) }}</span>
                    </li>
                    @endforeach
                </ul>
            </x-panel>
            @endif
        </div>

        {{-- Kanan --}}
        <section class="fade-up lg:col-span-2 min-w-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white" style="--i:3">
            <div class="flex items-center justify-between gap-3 border-b border-[#E5E7EB] px-5 py-4">
                <h2 class="text-base font-semibold">Riwayat Transaksi</h2>
                <x-pill tone="green">{{ $orders->count() }} order</x-pill>
            </div>

            @if($orders->count())
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead>
                        <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">No. Order</th>
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Pembayaran</th>
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Status</th>
                            <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Total</th>
                            <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                        @foreach($orders as $order)
                        <tr class="hover:bg-[#ECFDF3]/50 transition-colors cursor-pointer" onclick="window.location='{{ route('orders.show', $order) }}'">
                            <td class="px-6 py-4"><a href="{{ route('orders.show', $order) }}" class="font-mono text-[13px] font-semibold text-[#16A34A] hover:underline">{{ $order->order_number }}</a></td>
                            <td class="px-6 py-4 capitalize text-slate-700">{{ $order->payment_method }}</td>
                            <td class="px-6 py-4"><x-order-status :status="$order->status" /></td>
                            <td class="px-6 py-4 text-right font-bold tabular-nums">{{ $rp($order->grand_total) }}</td>
                            <td class="px-6 py-4 text-right text-[#64748B]">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <ul class="md:hidden divide-y divide-[#E5E7EB]">
                @foreach($orders as $order)
                <li>
                    <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between gap-3 px-5 py-4 active:bg-[#ECFDF3]/60">
                        <div class="min-w-0">
                            <p class="font-mono text-[13px] font-semibold text-[#16A34A]">{{ $order->order_number }}</p>
                            <p class="mt-1 text-xs capitalize text-[#64748B]">{{ $order->payment_method }} &middot; {{ $order->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-bold tabular-nums">{{ $rp($order->grand_total) }}</p>
                            <div class="mt-1"><x-order-status :status="$order->status" /></div>
                        </div>
                    </a>
                </li>
                @endforeach
            </ul>
            @else
            <x-empty title="Belum ada transaksi" text="Transaksi pelanggan ini akan muncul di sini." />
            @endif
        </section>
    </div>
</div>
@endsection
