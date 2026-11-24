@extends('layouts.app')
@section('title', 'Struk #' . $order->order_number)
@section('page-title', 'Detail Struk')

@section('content')
@php $inApp = \App\Support\InApp::check(); @endphp
<div class="max-w-sm mx-auto {{ $inApp ? 'pt-6' : '' }}">
    @if($inApp)
    {{-- Aplikasi: bar atas sudah punya tombol kembali; cukup dua aksi. --}}
    <div class="flex gap-3 mb-6">
        <a href="{{ route('receipt.print', $order) }}" class="flex-1 h-12 inline-flex items-center justify-center rounded-2xl bg-[#16A34A] active:bg-[#15803D] text-white text-[15px] font-semibold">Cetak</a>
        <a href="{{ route('receipt.pdf', $order) }}" class="h-12 px-6 inline-flex items-center justify-center rounded-2xl bg-[#F1F5F9] active:bg-[#E2E8F0] text-[#0B1B3F] text-[15px] font-semibold">PDF</a>
    </div>
    @else
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <a href="{{ route('orders.show', $order) }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Detail Order</a>
        <div class="flex gap-2">
            <a href="{{ route('receipt.print', $order) }}" target="_blank"
               class="inline-flex items-center gap-2 bg-[#16A34A] hover:bg-[#15803D] text-white text-sm font-semibold px-4 py-2 rounded-lg">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 9V4h10v5M7 17H5a2 2 0 01-2-2v-4a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2h-2M7 14h10v6H7z"/></svg>
                Cetak
            </a>
            <a href="{{ route('receipt.pdf', $order) }}"
               class="inline-flex items-center gap-2 border border-[#E5E7EB] bg-white hover:bg-gray-50 text-[#0B1B3F] text-sm font-semibold px-4 py-2 rounded-lg">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0l-4-4m4 4l4-4M5 19h14"/></svg>
                PDF
            </a>
            <a href="{{ route('pos.index') }}"
               class="text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg">
                Kembali ke POS
            </a>
        </div>
    </div>
    @endif
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="p-6 font-mono text-sm">
            @php $settings = $order->business->settings ?? []; @endphp
            <div class="text-center mb-4">
                <p class="text-base font-bold">{{ $settings['receipt_header'] ?? $order->business->name }}</p>
                @if($order->outlet->address)<p class="text-xs text-gray-500 mt-0.5">{{ $order->outlet->address }}</p>@endif
                @if($order->outlet->phone)<p class="text-xs text-gray-500">{{ $order->outlet->phone }}</p>@endif
            </div>
            <div class="border-t border-dashed border-gray-300 my-3"></div>
            <div class="space-y-0.5 text-xs text-gray-600">
                <div class="flex justify-between"><span>No. Order</span><span class="font-bold text-gray-900">{{ $order->order_number }}</span></div>
                <div class="flex justify-between"><span>Kasir</span><span>{{ $order->user->name }}</span></div>
                <div class="flex justify-between"><span>Tanggal</span><span>{{ $order->created_at->format('d/m/Y H:i') }}</span></div>
                @if($order->customer)<div class="flex justify-between"><span>Pelanggan</span><span>{{ $order->customer->name }}</span></div>@endif
            </div>
            <div class="border-t border-dashed border-gray-300 my-3"></div>
            <div class="space-y-2">
                @foreach($order->items as $item)
                <div>
                    <div class="flex justify-between text-xs">
                        <span class="flex-1 font-semibold text-gray-900">{{ $item->product_name }}</span>
                        <span class="ml-2">Rp {{ number_format($item->subtotal,0,',','.') }}</span>
                    </div>
                    @if($item->variant_name)<p class="text-xs text-gray-500 ml-2">{{ $item->variant_name }}</p>@endif
                    <p class="text-xs text-gray-400 ml-2">{{ number_format($item->qty,0) }} x Rp {{ number_format($item->price,0,',','.') }}</p>
                    @foreach($item->addons as $addon)
                    <p class="text-xs text-gray-500 ml-2">+ {{ $addon->addon_name }}: Rp {{ number_format($addon->price,0,',','.') }}</p>
                    @endforeach
                    @if($item->notes)<p class="text-xs text-orange-500 ml-2 italic">Catatan: {{ $item->notes }}</p>@endif
                </div>
                @endforeach
            </div>
            <div class="border-t border-dashed border-gray-300 my-3"></div>
            <div class="space-y-1 text-xs">
                <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>Rp {{ number_format($order->subtotal,0,',','.') }}</span></div>
                @if($order->discount_amount > 0)<div class="flex justify-between text-red-600"><span>Diskon</span><span>- Rp {{ number_format($order->discount_amount,0,',','.') }}</span></div>@endif
                @if($order->tax_amount > 0)<div class="flex justify-between text-gray-600"><span>Pajak</span><span>Rp {{ number_format($order->tax_amount,0,',','.') }}</span></div>@endif
                @if($order->service_amount > 0)<div class="flex justify-between text-gray-600"><span>Service</span><span>Rp {{ number_format($order->service_amount,0,',','.') }}</span></div>@endif
                <div class="flex justify-between font-bold text-gray-900 text-sm pt-1 border-t border-dashed border-gray-300"><span>TOTAL</span><span>Rp {{ number_format($order->grand_total,0,',','.') }}</span></div>
                <div class="flex justify-between text-gray-600"><span>{{ (['cash'=>'TUNAI','ewallet'=>'E-WALLET'][$order->payment_method] ?? strtoupper($order->payment_method)) }}</span><span>Rp {{ number_format($order->paid_amount,0,',','.') }}</span></div>
                @if($order->change_amount > 0)<div class="flex justify-between text-blue-700 font-semibold"><span>Kembalian</span><span>Rp {{ number_format($order->change_amount,0,',','.') }}</span></div>@endif
            </div>
            <div class="border-t border-dashed border-gray-300 my-3"></div>
            <p class="text-center text-xs text-gray-500">{{ $settings['receipt_footer'] ?? 'Terima kasih!' }}</p>
        </div>
    </div>
</div>
@endsection
