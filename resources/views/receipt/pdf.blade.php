<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 11px; color: #1f2937; background: #fff; width: 80mm; }
  .center { text-align: center; }
  .right  { text-align: right; }
  .bold   { font-weight: bold; }
  .dashed { border-top: 1px dashed #9ca3af; margin: 6px 0; }
  .row    { display: flex; justify-content: space-between; margin-bottom: 2px; }
  .row .lbl { flex: 1; color: #6b7280; }
  .row .val { font-weight: 600; }
  .item   { margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px dotted #e5e7eb; }
  .item:last-child { border-bottom: none; }
  .item-name { font-weight: 600; font-size: 12px; }
  .item-meta { color: #6b7280; font-size: 10px; padding-left: 8px; }
  .total-section { background: #f9fafb; border-radius: 4px; padding: 8px; margin: 8px 0; }
  .grand-total { font-size: 16px; font-weight: 700; color: #059669; }
  .kembalian   { font-size: 14px; font-weight: 700; color: #1d4ed8; }
  .badge { display: inline-block; background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
</style>
</head>
<body>
@php $settings = $order->business->settings ?? []; @endphp

<div class="center" style="margin-bottom: 10px; padding: 10px 0;">
    <div class="bold" style="font-size: 15px;">{{ $settings['receipt_header'] ?? $order->business->name }}</div>
    @if($order->outlet->address)
    <div style="color:#6b7280; font-size:10px; margin-top:2px;">{{ $order->outlet->address }}</div>
    @endif
    @if($order->outlet->phone)
    <div style="color:#6b7280; font-size:10px;">Telp: {{ $order->outlet->phone }}</div>
    @endif
</div>

<div class="dashed"></div>

<div style="font-size:10px; margin-bottom:8px;">
    <div class="row"><span class="lbl">No. Order</span><span class="val bold">{{ $order->order_number }}</span></div>
    <div class="row"><span class="lbl">Tanggal</span><span class="val">{{ $order->created_at->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span class="lbl">Kasir</span><span class="val">{{ $order->user->name }}</span></div>
    <div class="row"><span class="lbl">Outlet</span><span class="val">{{ $order->outlet->name }}</span></div>
    @if($order->customer)
    <div class="row"><span class="lbl">Pelanggan</span><span class="val">{{ $order->customer->name }}</span></div>
    @endif
    <div class="row"><span class="lbl">Pembayaran</span>
        <span class="val"><span class="badge">{{ strtoupper($order->payment_method) }}</span></span>
    </div>
</div>

<div class="dashed"></div>

<div style="margin-bottom: 8px;">
    @foreach($order->items as $item)
    <div class="item">
        <div style="display:flex; justify-content:space-between;">
            <span class="item-name">{{ $item->product_name }}</span>
            <span class="bold">Rp {{ number_format($item->subtotal,0,',','.') }}</span>
        </div>
        @if($item->variant_name)
        <div class="item-meta">↳ {{ $item->variant_name }}</div>
        @endif
        <div class="item-meta">
            {{ number_format($item->qty,0) }} × Rp {{ number_format($item->price,0,',','.') }}
        </div>
        @foreach($item->addons as $addon)
        <div class="item-meta">+ {{ $addon->addon_name }}: Rp {{ number_format($addon->price,0,',','.') }}</div>
        @endforeach
        @if($item->notes)
        <div class="item-meta" style="color:#d97706; font-style:italic;">📝 {{ $item->notes }}</div>
        @endif
    </div>
    @endforeach
</div>

<div class="total-section">
    <div class="row" style="font-size:11px; color:#6b7280;">
        <span>Subtotal</span>
        <span>Rp {{ number_format($order->subtotal,0,',','.') }}</span>
    </div>
    @if($order->discount_amount > 0)
    <div class="row" style="font-size:11px; color:#dc2626;">
        <span>Diskon</span>
        <span>- Rp {{ number_format($order->discount_amount,0,',','.') }}</span>
    </div>
    @endif
    @if($order->tax_amount > 0)
    <div class="row" style="font-size:11px; color:#6b7280;">
        <span>Pajak ({{ $settings['tax_percent'] ?? 10 }}%)</span>
        <span>Rp {{ number_format($order->tax_amount,0,',','.') }}</span>
    </div>
    @endif
    @if($order->service_amount > 0)
    <div class="row" style="font-size:11px; color:#6b7280;">
        <span>Service</span>
        <span>Rp {{ number_format($order->service_amount,0,',','.') }}</span>
    </div>
    @endif
    <div class="dashed"></div>
    <div class="row">
        <span style="font-weight:700; font-size:13px;">TOTAL</span>
        <span class="grand-total">Rp {{ number_format($order->grand_total,0,',','.') }}</span>
    </div>
    @if($order->payment_method === 'cash')
    <div class="row" style="font-size:11px; margin-top:4px;">
        <span style="color:#6b7280;">Bayar (CASH)</span>
        <span>Rp {{ number_format($order->paid_amount,0,',','.') }}</span>
    </div>
    @if($order->change_amount > 0)
    <div class="row" style="margin-top:2px;">
        <span style="font-weight:600;">Kembalian</span>
        <span class="kembalian">Rp {{ number_format($order->change_amount,0,',','.') }}</span>
    </div>
    @endif
    @endif
</div>

@if($order->notes)
<div style="font-size:10px; color:#6b7280; margin-bottom:8px;">📝 {{ $order->notes }}</div>
@endif

<div class="dashed"></div>

<div class="center" style="font-size:10px; color:#6b7280; padding: 6px 0 12px;">
    <p>{{ $settings['receipt_footer'] ?? 'Terima kasih atas kunjungan Anda!' }}</p>
    <p style="margin-top:4px; font-size:9px; color:#d1d5db;">Powered by FNB POS System</p>
</div>
</body>
</html>
