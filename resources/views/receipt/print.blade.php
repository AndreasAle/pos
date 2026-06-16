<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk {{ $order->order_number }}</title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            color: #000;
            background: #fff;
            width: 80mm;
            padding: 4mm 4mm;
        }
        .center  { text-align: center; }
        .right   { text-align: right; }
        .bold    { font-weight: bold; }
        .dashed  { border-top: 1px dashed #000; margin: 6px 0; }
        .row     { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2px; }
        .row .label { flex: 1; }
        .row .value { text-align: right; margin-left: 8px; flex-shrink: 0; }
        .item-name { font-weight: bold; font-size: 11px; }
        .item-sub  { font-size: 10px; color: #444; margin-left: 8px; }
        .total-row { font-weight: bold; font-size: 12px; }
        .logo-area { margin-bottom: 6px; }
        @media print {
            body { width: 80mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
@php $settings = $order->business->settings ?? []; @endphp

{{-- Header --}}
<div class="center logo-area">
    <p class="bold" style="font-size:13px;">{{ $settings['receipt_header'] ?? $order->business->name }}</p>
    @if($order->outlet->name !== $order->business->name)
    <p>{{ $order->outlet->name }}</p>
    @endif
    @if($order->outlet->address)<p>{{ $order->outlet->address }}</p>@endif
    @if($order->outlet->phone)<p>Telp: {{ $order->outlet->phone }}</p>@endif
</div>

<div class="dashed"></div>

<div class="row"><span class="label">No. Order</span><span class="value bold">{{ $order->order_number }}</span></div>
<div class="row"><span class="label">Tanggal</span><span class="value">{{ $order->created_at->format('d/m/Y H:i') }}</span></div>
<div class="row"><span class="label">Kasir</span><span class="value">{{ $order->user->name }}</span></div>
@if($order->customer)
<div class="row"><span class="label">Pelanggan</span><span class="value">{{ $order->customer->name }}</span></div>
@endif

<div class="dashed"></div>

{{-- Items --}}
@foreach($order->items as $item)
<div class="row">
    <span class="label item-name">{{ $item->product_name }}</span>
    <span class="value bold">Rp {{ number_format($item->subtotal,0,',','.') }}</span>
</div>
@if($item->variant_name)
<div class="item-sub">↳ {{ $item->variant_name }}</div>
@endif
<div class="item-sub">{{ number_format($item->qty,0) }} × Rp {{ number_format($item->price,0,',','.') }}</div>
@foreach($item->addons as $addon)
<div class="item-sub">+ {{ $addon->addon_name }}: Rp {{ number_format($addon->price,0,',','.') }}</div>
@endforeach
@if($item->notes)
<div class="item-sub">📝 {{ $item->notes }}</div>
@endif
@endforeach

<div class="dashed"></div>

<div class="row"><span class="label">Subtotal</span><span class="value">Rp {{ number_format($order->subtotal,0,',','.') }}</span></div>
@if($order->discount_amount > 0)
<div class="row"><span class="label">Diskon</span><span class="value">- Rp {{ number_format($order->discount_amount,0,',','.') }}</span></div>
@endif
@if($order->tax_amount > 0)
<div class="row"><span class="label">Pajak ({{ $settings['tax_percent'] ?? 10 }}%)</span><span class="value">Rp {{ number_format($order->tax_amount,0,',','.') }}</span></div>
@endif
@if($order->service_amount > 0)
<div class="row"><span class="label">Service</span><span class="value">Rp {{ number_format($order->service_amount,0,',','.') }}</span></div>
@endif
<div class="dashed"></div>
<div class="row total-row"><span class="label">TOTAL</span><span class="value">Rp {{ number_format($order->grand_total,0,',','.') }}</span></div>
<div class="row"><span class="label">{{ strtoupper($order->payment_method) }}</span><span class="value">Rp {{ number_format($order->paid_amount,0,',','.') }}</span></div>
@if($order->change_amount > 0)
<div class="row bold"><span class="label">Kembalian</span><span class="value">Rp {{ number_format($order->change_amount,0,',','.') }}</span></div>
@endif

<div class="dashed"></div>
<p class="center" style="font-size:10px;">{{ $settings['receipt_footer'] ?? 'Terima kasih atas kunjungan Anda!' }}</p>
<p class="center" style="font-size:9px; color:#666; margin-top:4px;">Powered by FNB POS System</p>

<script>window.onload = function() { window.print(); }</script>
</body>
</html>
