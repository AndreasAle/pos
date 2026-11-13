@extends('layouts.app')
@section('title','Order #'.$order->order_number)
@section('page-title','Detail Order')

@section('content')
@php
    $rp         = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $type       = $order->order_type ?? 'dine_in';
    $typeLabels = ['dine_in' => 'Dine In', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery'];
    $typeTones  = ['dine_in' => 'green', 'takeaway' => 'blue', 'delivery' => 'amber'];
    $label      = 'text-xs font-semibold uppercase tracking-wider text-[#64748B]';
    $btn        = 'h-10 inline-flex items-center justify-center gap-2 rounded-lg border px-4 text-sm font-semibold transition-colors';
@endphp

<div class="page text-[#0B1B3F] pt-2">

    {{-- Header --}}
    <div class="fade-up flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-5" style="--i:0">
        <div class="min-w-0">
            <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#64748B] hover:text-[#16A34A] transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Riwayat Order
            </a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h2 class="font-mono text-xl sm:text-2xl font-bold tracking-tight">{{ $order->order_number }}</h2>
                <x-order-status :status="$order->status" />
            </div>
            <p class="mt-1 text-sm text-[#64748B]">{{ $order->created_at->format('d/m/Y H:i') }} &middot; {{ $order->outlet->name }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('receipt.show', $order) }}" class="{{ $btn }} border-[#BBF7D0] bg-[#ECFDF3] text-[#15803D] hover:bg-[#DDF8E8]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Struk
            </a>
            @if($order->status === 'paid')
            <button type="button" onclick="openModal('refund-modal')" class="{{ $btn }} border-sky-200 bg-white text-sky-700 hover:bg-sky-50">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 015 5v2M3 10l4-4M3 10l4 4"/></svg>
                Refund
            </button>
            <button type="button" onclick="openModal('void-modal')" class="{{ $btn }} border-red-200 bg-white text-red-600 hover:bg-red-50">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                Void / Batal
            </button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        <div class="lg:col-span-2 space-y-4 min-w-0">

            {{-- Info --}}
            <x-panel title="Informasi Order" class="fade-up" style="--i:1">
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-5 text-sm">
                    <div>
                        <dt class="{{ $label }}">Kasir</dt>
                        <dd class="mt-1.5 flex items-center gap-2 font-semibold">
                            <span class="w-7 h-7 rounded-full bg-[#ECFDF3] text-[#16A34A] text-xs font-bold flex items-center justify-center">{{ strtoupper(mb_substr($order->user->name ?? '-', 0, 1)) }}</span>
                            <span class="truncate">{{ $order->user->name ?? '-' }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="{{ $label }}">Tipe Order</dt>
                        <dd class="mt-1.5"><x-pill :tone="$typeTones[$type] ?? 'gray'">{{ $typeLabels[$type] ?? '-' }}</x-pill></dd>
                    </div>
                    <div>
                        <dt class="{{ $label }}">Pembayaran</dt>
                        <dd class="mt-1.5">
                            @if($order->is_split_payment)
                                <x-pill tone="blue">Split</x-pill>
                            @else
                                <span class="font-semibold capitalize">{{ $order->payment_method }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="{{ $label }}">Outlet</dt>
                        <dd class="mt-1.5 font-semibold">{{ $order->outlet->name }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $label }}">Waktu</dt>
                        <dd class="mt-1.5 font-semibold">{{ $order->created_at->format('d/m/Y H:i') }}</dd>
                    </div>
                    @if($order->customer)
                    <div>
                        <dt class="{{ $label }}">Pelanggan</dt>
                        <dd class="mt-1.5 font-semibold">
                            {{ $order->customer->name }}
                            @if($order->customer->phone)<span class="block text-xs font-medium text-[#64748B]">{{ $order->customer->phone }}</span>@endif
                        </dd>
                    </div>
                    @endif
                </dl>

                @if($order->cancel_reason)
                <div class="mt-5 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3.5">
                    <svg class="w-5 h-5 shrink-0 text-red-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-red-700">Alasan pembatalan</p>
                        <p class="mt-0.5 text-sm text-red-800">{{ $order->cancel_reason }}</p>
                    </div>
                </div>
                @endif
                @if($order->refund_reason)
                <div class="mt-5 flex items-start gap-3 rounded-lg border border-sky-200 bg-sky-50 p-3.5">
                    <svg class="w-5 h-5 shrink-0 text-sky-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 015 5v2M3 10l4-4M3 10l4 4"/></svg>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider text-sky-700">Alasan refund</p>
                        <p class="mt-0.5 text-sm text-sky-900">{{ $order->refund_reason }}</p>
                        @if($order->refunded_at)<p class="mt-0.5 text-xs text-sky-700">{{ $order->refunded_at->format('d/m/Y H:i') }}</p>@endif
                    </div>
                </div>
                @endif
            </x-panel>

            {{-- Delivery --}}
            @if($type === 'delivery')
            <x-panel title="Informasi Pengiriman" class="fade-up" style="--i:2">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5 text-sm">
                    @if($order->delivery_platform)
                    <div><dt class="{{ $label }}">Platform</dt><dd class="mt-1.5 font-semibold capitalize">{{ str_replace('_', ' ', $order->delivery_platform) }}</dd></div>
                    @endif
                    @if($order->platform_order_number)
                    <div><dt class="{{ $label }}">No. Order Platform</dt><dd class="mt-1.5 font-mono font-semibold">{{ $order->platform_order_number }}</dd></div>
                    @endif
                    @if($order->customer_address)
                    <div class="sm:col-span-2"><dt class="{{ $label }}">Alamat Pengiriman</dt><dd class="mt-1.5 font-semibold">{{ $order->customer_address }}</dd></div>
                    @endif
                    @if($order->delivery_notes)
                    <div class="sm:col-span-2"><dt class="{{ $label }}">Catatan Pengiriman</dt><dd class="mt-1.5 text-[#64748B]">{{ $order->delivery_notes }}</dd></div>
                    @endif
                </dl>
            </x-panel>
            @endif

            {{-- Items --}}
            <x-panel :flush="true" class="fade-up" style="--i:3">
                <div class="flex items-center justify-between gap-3 border-b border-[#E5E7EB] px-5 py-4">
                    <h2 class="text-base font-semibold">Item Pesanan</h2>
                    <x-pill tone="green">{{ $order->items->count() }} item</x-pill>
                </div>

                {{-- Tabel (tablet & desktop) --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-[#E5E7EB]">
                                <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Produk</th>
                                <th class="text-center px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Qty</th>
                                <th class="text-right px-4 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Harga</th>
                                <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E7EB]">
                            @foreach($order->items as $item)
                            <tr class="hover:bg-[#ECFDF3]/50 transition-colors">
                                <td class="px-6 py-4">
                                    <p class="font-semibold">{{ $item->product_name }}</p>
                                    @if($item->variant_name)<p class="mt-0.5 text-xs text-[#64748B]">{{ $item->variant_name }}</p>@endif
                                    @foreach($item->addons as $a)
                                        <p class="mt-0.5 text-xs font-medium text-[#15803D]">+ {{ $a->addon_name }} <span class="text-[#64748B]">({{ $rp($a->price) }})</span></p>
                                    @endforeach
                                    @if($item->notes)
                                        <p class="mt-1 inline-flex items-center gap-1 text-xs text-amber-700">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15H9v-2.8l8.6-8.6z"/></svg>
                                            {{ $item->notes }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex min-w-[2rem] justify-center rounded-md bg-slate-100 px-2 py-0.5 text-[13px] font-bold">{{ number_format($item->qty, 0) }}</span>
                                </td>
                                <td class="px-4 py-4 text-right text-[#64748B]">{{ $rp($item->price) }}</td>
                                <td class="px-6 py-4 text-right font-bold">{{ $rp($item->subtotal) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Daftar kartu (mobile) --}}
                <ul class="md:hidden divide-y divide-[#E5E7EB]">
                    @foreach($order->items as $item)
                    <li class="px-5 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $item->product_name }}</p>
                                @if($item->variant_name)<p class="mt-0.5 text-xs text-[#64748B]">{{ $item->variant_name }}</p>@endif
                                @foreach($item->addons as $a)
                                    <p class="mt-0.5 text-xs font-medium text-[#15803D]">+ {{ $a->addon_name }} <span class="text-[#64748B]">({{ $rp($a->price) }})</span></p>
                                @endforeach
                                @if($item->notes)<p class="mt-1 text-xs text-amber-700">Catatan: {{ $item->notes }}</p>@endif
                            </div>
                            <p class="shrink-0 font-bold">{{ $rp($item->subtotal) }}</p>
                        </div>
                        <p class="mt-1.5 text-xs text-[#64748B]">{{ number_format($item->qty, 0) }} &times; {{ $rp($item->price) }}</p>
                    </li>
                    @endforeach
                </ul>
            </x-panel>
        </div>

        {{-- Ringkasan --}}
        <div class="space-y-4 lg:sticky lg:top-20">
            <x-panel title="Ringkasan Pembayaran" class="fade-up" style="--i:2">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between text-[#64748B]"><dt>Subtotal</dt><dd class="font-semibold text-[#0B1B3F]">{{ $rp($order->subtotal) }}</dd></div>
                    @if($order->discount_amount > 0)<div class="flex justify-between text-[#64748B]"><dt>Diskon</dt><dd class="font-semibold text-red-600">- {{ $rp($order->discount_amount) }}</dd></div>@endif
                    @if($order->tax_amount > 0)<div class="flex justify-between text-[#64748B]"><dt>Pajak</dt><dd class="font-semibold text-[#0B1B3F]">{{ $rp($order->tax_amount) }}</dd></div>@endif
                    @if($order->service_amount > 0)<div class="flex justify-between text-[#64748B]"><dt>Service</dt><dd class="font-semibold text-[#0B1B3F]">{{ $rp($order->service_amount) }}</dd></div>@endif
                    @if($order->delivery_fee > 0)<div class="flex justify-between text-[#64748B]"><dt>Ongkos Kirim</dt><dd class="font-semibold text-[#0B1B3F]">{{ $rp($order->delivery_fee) }}</dd></div>@endif
                </dl>

                <div class="mt-4 -mx-5 px-5 py-4 bg-[#ECFDF3] border-y border-[#BBF7D0] flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#15803D]">Total</span>
                    <span class="text-2xl font-extrabold tracking-tight text-[#0B1B3F]">{{ $rp($order->grand_total) }}</span>
                </div>

                <dl class="mt-4 space-y-3 text-sm">
                    @if($order->is_split_payment && $order->payments->count())
                        <div>
                            <dt class="{{ $label }} mb-2">Pembayaran split</dt>
                            <div class="space-y-2">
                                @foreach($order->payments as $pay)
                                <div class="flex justify-between"><dd class="capitalize text-[#64748B]">{{ $pay->payment_method }}</dd><dd class="font-semibold">{{ $rp($pay->amount) }}</dd></div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="flex justify-between text-[#64748B]"><dt>Dibayar <span class="capitalize">({{ $order->payment_method }})</span></dt><dd class="font-semibold text-[#0B1B3F]">{{ $rp($order->paid_amount) }}</dd></div>
                    @endif
                    @if($order->change_amount > 0)
                    <div class="flex justify-between text-[#64748B]"><dt>Kembalian</dt><dd class="font-semibold text-sky-700">{{ $rp($order->change_amount) }}</dd></div>
                    @endif
                </dl>

                @if($order->notes)
                <div class="mt-5 rounded-lg bg-slate-50 border border-[#E5E7EB] p-3.5">
                    <p class="{{ $label }}">Catatan</p>
                    <p class="mt-1 text-sm text-slate-700">{{ $order->notes }}</p>
                </div>
                @endif
            </x-panel>
        </div>
    </div>
</div>

{{-- Void Modal --}}
<div id="void-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0B1B3F]/50 backdrop-blur-[2px]" onclick="if(event.target===this)closeModal('void-modal')">
    <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-2xl w-full max-w-md p-6">
        <div class="flex items-start gap-3 mb-4">
            <span class="w-10 h-10 shrink-0 rounded-lg bg-red-50 text-red-600 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            </span>
            <div>
                <h3 class="font-bold text-[#0B1B3F]">Batalkan Order</h3>
                <p class="mt-1 text-sm text-[#64748B]">Masukkan alasan pembatalan order <strong class="text-[#0B1B3F]">{{ $order->order_number }}</strong>. Stok akan dikembalikan.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('orders.void', $order) }}">
            @csrf
            <textarea name="reason" rows="3" required placeholder="Alasan pembatalan..."
                      class="w-full px-3.5 py-2.5 rounded-lg border border-[#E5E7EB] text-sm focus:outline-none focus:border-red-400 focus:ring-2 focus:ring-red-200 mb-4 transition"></textarea>
            <div class="flex gap-2">
                <button type="button" onclick="closeModal('void-modal')" class="flex-1 h-10 rounded-lg border border-[#E5E7EB] text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                <button type="submit" class="flex-1 h-10 rounded-lg bg-red-600 hover:bg-red-700 text-sm font-semibold text-white transition-colors">Batalkan Order</button>
            </div>
        </form>
    </div>
</div>

{{-- Refund Modal --}}
<div id="refund-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0B1B3F]/50 backdrop-blur-[2px]" onclick="if(event.target===this)closeModal('refund-modal')">
    <div class="bg-white rounded-xl border border-[#E5E7EB] shadow-2xl w-full max-w-md p-6">
        <div class="flex items-start gap-3 mb-4">
            <span class="w-10 h-10 shrink-0 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 015 5v2M3 10l4-4M3 10l4 4"/></svg>
            </span>
            <div>
                <h3 class="font-bold text-[#0B1B3F]">Refund Order</h3>
                <p class="mt-1 text-sm text-[#64748B]">
                    Refund order <strong class="text-[#0B1B3F]">{{ $order->order_number }}</strong> sebesar
                    <strong class="text-sky-700">{{ $rp($order->grand_total) }}</strong>. Stok dan poin akan dikembalikan otomatis.
                </p>
            </div>
        </div>
        <form method="POST" action="{{ route('orders.refund', $order) }}">
            @csrf
            <textarea name="reason" rows="3" required placeholder="Alasan refund (wajib)..."
                      class="w-full px-3.5 py-2.5 rounded-lg border border-[#E5E7EB] text-sm focus:outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-200 mb-3 transition"></textarea>
            <div class="flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 p-3 mb-4 text-xs text-amber-800">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <span>Proses refund <strong>tidak dapat diurungkan</strong>. Pastikan uang sudah dikembalikan ke pelanggan sebelum konfirmasi.</span>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="closeModal('refund-modal')" class="flex-1 h-10 rounded-lg border border-[#E5E7EB] text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Batal</button>
                <button type="submit" class="flex-1 h-10 rounded-lg bg-sky-600 hover:bg-sky-700 text-sm font-semibold text-white transition-colors">Konfirmasi Refund</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') ['void-modal', 'refund-modal'].forEach(closeModal);
});
</script>
@endsection
