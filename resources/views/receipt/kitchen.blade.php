@php
    $o          = \App\Support\ReceiptOptions::for($order);
    $paperWidth = $o['narrow'] ? '58mm' : '80mm';
    $type       = \App\Support\ReceiptOptions::orderTypeLabel($order->order_type, $o['retail']);
    $qty        = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dapur {{ $order->order_number }}</title>
    <style>
        @page { size: {{ $paperWidth }} auto; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 600; color: #000;
               width: {{ $paperWidth }}; padding: 4mm 3mm; }
        .center { text-align: center; }
        .title { font-size: 22px; font-weight: 800; letter-spacing: 2px; }
        .meta { font-size: 16px; font-weight: 800; margin: 2px 0 4px; }
        .rule { border-top: 2px dashed #000; margin: 6px 0; }
        .item { font-size: 17px; font-weight: 800; margin-top: 6px; }
        .sub { padding-left: 14px; font-size: 13px; }
        .note { padding-left: 14px; font-size: 14px; font-weight: 800; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
{{-- The tablet app reads this payload and prints it on the kitchen printer. --}}
@if($payload)
<a class="no-print" id="escpos" href="{{ $payload }}" style="display:none">escpos</a>
@endif

<p class="center title">DAPUR</p>
<p class="center meta">#{{ $short }} &nbsp; {{ $order->created_at->format('H:i') }}@if($type) &nbsp; {{ $type }}@endif</p>
@if($order->customer)<p>Pelanggan: {{ $order->customer->name }}</p>@endif
<p>No. {{ $order->order_number }}</p>
<div class="rule"></div>

@forelse($items as $item)
    <p class="item">{{ $qty($item->qty) }}x {{ $item->product_name }}</p>
    @if($item->variant_name)<p class="sub">({{ $item->variant_name }})</p>@endif
    @foreach($item->addons as $addon)<p class="sub">+ {{ $addon->addon_name }}</p>@endforeach
    @if($item->notes)<p class="note">* {{ $item->notes }}</p>@endif
@empty
    {{-- No-kitchen-items marker; the app relies on there being no payload. --}}
    <p class="center">Tidak ada item untuk dapur.</p>
@endforelse

<div class="rule"></div>
@if($order->notes)<p class="note" style="padding-left:0">Catatan: {{ $order->notes }}</p><div class="rule"></div>@endif

@if($payload)
<script>window.onload = function () { window.print(); };</script>
@endif
</body>
</html>
