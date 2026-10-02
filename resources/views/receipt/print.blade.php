@php
    $settings   = $order->business->settings ?? [];
    $o          = \App\Support\ReceiptOptions::for($order);
    // 58mm printers are the cheap, common ones; the layout has to follow the
    // setting instead of assuming 80mm, or the receipt prints clipped.
    $paperWidth = $o['narrow'] ? '58mm' : '80mm';
    $isNarrow   = $o['narrow'];
    $rp         = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $qty        = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
    $totalQty   = $order->items->sum('qty');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk {{ $order->order_number }}</title>
    <style>
        @page { size: {{ $paperWidth }} auto; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            /* Thermal heads print thin strokes faintly, so a plain sans at a
               readable size and weight prints far darker than Courier. */
            font-family: Arial, Helvetica, sans-serif;
            font-size: {{ $isNarrow ? '12px' : '13px' }};
            font-weight: 500;
            line-height: 1.35;
            color: #000;
            background: #fff;
            /* A 58mm roll only prints across ~48mm (72mm on 80mm rolls); laying
               out on the full paper width clips the right edge. */
            width: {{ $isNarrow ? '48mm' : '72mm' }};
            padding: {{ $isNarrow ? '2mm 0 4mm' : '3mm 0 5mm' }};
        }
        .center { text-align: center; }
        .b      { font-weight: 700; }
        .rule   { border-top: 1px dashed #000; margin: 6px 0; }
        .row    { display: flex; justify-content: space-between; gap: 6px; }
        .row > :last-child { text-align: right; white-space: nowrap; }
        .logo   { display: block; margin: 0 auto 4px; max-width: 60%; max-height: 22mm; filter: grayscale(1) contrast(1.4); }
        .shop   { font-size: {{ $isNarrow ? '17px' : '19px' }}; font-weight: 800; line-height: 1.15; }
        .item   { margin-bottom: 4px; }
        .item-name { font-weight: 700; }
        .item-sub  { padding-left: 10px; }
        .grand  { font-size: {{ $isNarrow ? '15px' : '16px' }}; font-weight: 800; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

{{-- Header --}}
<div class="center">
    @if($o['logo'])<img class="logo" src="{{ $o['logo'] }}" alt="">@endif
    <p class="shop">{{ $o['name'] }}</p>
    @if($o['subtitle'])<p>{{ $o['subtitle'] }}</p>@endif
    @if($o['address'])<p>{{ $o['address'] }}</p>@endif
    @if($o['phone'])<p>No. Telp {{ $o['phone'] }}</p>@endif
</div>

<div class="rule"></div>

{{-- Order info --}}
<div class="row">
    <span>{{ $order->created_at->format('d-m-Y') }}</span>
    @if($o['cashier'])<span>{{ $order->user->name }}</span>@endif
</div>
<div class="row">
    <span>{{ $order->created_at->format('H:i:s') }}</span>
    @if($o['customer'] && $order->customer)<span>{{ $order->customer->name }}</span>@endif
</div>
<div class="row">
    <span class="b">No. {{ $order->order_number }}</span>
    @if($o['order_type'] && ($type = \App\Support\ReceiptOptions::orderTypeLabel($order->order_type, $o['retail'])))<span>{{ $type }}</span>@endif
</div>

<div class="rule"></div>

{{-- Items --}}
@foreach($order->items as $i => $item)
<div class="item">
    <p class="item-name">{{ $i + 1 }}. {{ $item->product_name }}@if($item->variant_name) ({{ $item->variant_name }})@endif</p>
    <div class="row item-sub">
        <span>{{ $qty($item->qty) }} x {{ number_format((float) $item->price, 0, ',', '.') }}</span>
        <span>{{ $rp($item->subtotal) }}</span>
    </div>
    @foreach($item->addons as $addon)
    <p class="item-sub">+ {{ $addon->addon_name }} {{ number_format((float) $addon->price, 0, ',', '.') }}</p>
    @endforeach
    @if($item->notes)<p class="item-sub">* {{ $item->notes }}</p>@endif
</div>
@endforeach

<div class="rule"></div>

{{-- Totals --}}
@if($o['total_qty'])
<p>Total QTY : {{ $qty($totalQty) }}</p>
<div style="height:4px"></div>
@endif
<div class="row"><span>Sub Total</span><span>{{ $rp($order->subtotal) }}</span></div>
@if($order->discount_amount > 0)
<div class="row"><span>Diskon</span><span>- {{ $rp($order->discount_amount) }}</span></div>
@endif
@if($order->tax_amount > 0)
<div class="row"><span>Pajak ({{ $o['tax_percent'] }}%)</span><span>{{ $rp($order->tax_amount) }}</span></div>
@endif
@if($order->service_amount > 0)
<div class="row"><span>Service</span><span>{{ $rp($order->service_amount) }}</span></div>
@endif
@if(($order->delivery_fee ?? 0) > 0)
<div class="row"><span>Ongkos Kirim</span><span>{{ $rp($order->delivery_fee) }}</span></div>
@endif
<div class="row grand"><span>Total</span><span>{{ $rp($order->grand_total) }}</span></div>

<div class="rule"></div>

{{-- Payment --}}
<div class="row"><span>Bayar ({{ \App\Support\ReceiptOptions::paymentLabel($order->payment_method) }})</span><span>{{ $rp($order->paid_amount) }}</span></div>
@if($order->change_amount > 0)
<div class="row b"><span>Kembalian</span><span>{{ $rp($order->change_amount) }}</span></div>
@endif

<div class="rule"></div>

<p class="center">{{ $o['footer'] }}</p>

@php($via = in_array(request('via'), ['browser', 'rawbt'], true) ? request('via') : ($settings['print_method'] ?? 'browser'))
@if($via === 'rawbt')
@php($rawbt = 'rawbt:base64,' . base64_encode(app(\App\Services\EscPosReceipt::class)->render($order)))
{{-- Bluetooth thermal printer via the RawBT Android app; the browser print
     dialog cannot reach it. --}}
<div class="no-print center" style="margin-top:12px;">
    <a href="{{ $rawbt }}" style="display:inline-block;padding:8px 14px;border:1px solid #000;border-radius:6px;color:#000;text-decoration:none;">Cetak ulang (RawBT)</a>
</div>
<script>window.onload = function() { window.location.href = @json($rawbt); }</script>
@else
{{-- onload waits for the logo, so it is on the page before printing. --}}
<script>window.onload = function() { window.print(); }</script>
@endif
</body>
</html>
