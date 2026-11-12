<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: sans-serif; font-size: 10px; color: #102C35; }
  .head { background: #0C7856; color: #fff; padding: 12px 18px; }
  .head h1 { font-size: 16px; }
  .head p { font-size: 10px; opacity: .9; margin-top: 2px; }
  .meta { padding: 8px 18px; color: #4b5563; font-size: 10px; border-bottom: 1px solid #e5e7eb; }
  .meta strong { color: #102C35; }
  .wrap { padding: 12px 18px; }
  h2 { font-size: 12px; margin: 14px 0 5px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #f3f6f5; text-align: left; font-size: 9px; padding: 5px 7px; border-bottom: 1px solid #d1d9d6; }
  td { padding: 4px 7px; border-bottom: 1px solid #eef1f0; }
  .r { text-align: right; }
  .kpi td { width: 20%; border: 1px solid #d1d9d6; padding: 8px; vertical-align: top; }
  .kpi .n { font-size: 14px; font-weight: bold; margin-top: 2px; }
  .kpi .l { font-size: 9px; color: #6b7280; }
  .warn { margin-top: 8px; padding: 6px 9px; background: #fffbeb; border: 1px solid #fde68a; color: #78350f; }
  .foot { margin-top: 14px; padding-top: 6px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 9px; }
</style>
</head>
<body>
@php
    $m = $summary['metrics'];
    $rp = fn ($v) => \App\Support\Money::rp($v);
@endphp
<div class="head">
    <h1>Laporan Penjualan - {{ $business->name }}</h1>
    <p>Dibuat {{ $generated_at->format('d M Y H:i') }} ({{ $f->timezone }})</p>
</div>
<div class="meta">
    Periode <strong>{{ $f->dateFrom() }} s/d {{ $f->dateTo() }}</strong> &nbsp;|&nbsp;
    Outlet <strong>{{ implode(', ', $outlet_names) }}</strong> &nbsp;|&nbsp;
    Versi metrik <strong>{{ $summary['evidence']['metric_version'] }}</strong> &nbsp;|&nbsp; Filter <strong>{{ $summary['evidence']['filter_hash'] }}</strong>
</div>

<div class="wrap">
<table class="kpi"><tr>
    <td><div class="l">Penjualan kotor item</div><div class="n">{{ $rp($m['gross_item_sales']) }}</div></td>
    <td><div class="l">Penjualan bersih (diskon {{ $rp($m['discounts']) }})</div><div class="n">{{ $rp($m['net_sales']) }}</div></td>
    <td><div class="l">Pembayaran tercatat (pajak {{ $rp($m['tax_amount']) }})</div><div class="n">{{ $rp($m['payment_collected']) }}</div></td>
    <td><div class="l">Transaksi</div><div class="n">{{ \App\Support\Money::num($m['transactions']) }}</div></td>
    <td><div class="l">Rata-rata transaksi</div><div class="n">{{ $rp($m['aov']) }}</div></td>
</tr></table>

@foreach($summary['warnings'] as $w)<div class="warn">{{ $w }}</div>@endforeach

<h2>Metode pembayaran (tercatat di POS)</h2>
<table>
    <tr><th>Metode</th><th class="r">Order</th><th class="r">Jumlah</th><th class="r">%</th></tr>
    @foreach($summary['payments'] as $p)
    <tr><td>{{ \App\Support\Money::method($p['method']) }}</td><td class="r">{{ $p['orders'] }}</td><td class="r">{{ $rp($p['amount']) }}</td><td class="r">{{ \App\Support\Money::num($p['share_pct'], 1) }}%</td></tr>
    @endforeach
</table>

@if(count($trend['points']))
<h2>Tren penjualan bersih</h2>
<table>
    <tr><th>Periode</th><th class="r">Transaksi</th><th class="r">Kotor</th><th class="r">Diskon</th><th class="r">Bersih</th></tr>
    @foreach($trend['points'] as $p)
    <tr><td>{{ $p['label'] }}</td><td class="r">{{ $p['transactions'] }}</td><td class="r">{{ $rp($p['gross_item_sales']) }}</td><td class="r">{{ $rp($p['discounts']) }}</td><td class="r">{{ $rp($p['net_sales']) }}</td></tr>
    @endforeach
</table>
@endif

@if(count($categories))
<h2>Kategori</h2>
<table>
    <tr><th>Kategori</th><th class="r">Qty</th><th class="r">Kotor</th><th class="r">Bersih (diskon dialokasikan)</th></tr>
    @foreach($categories as $c)
    <tr><td>{{ $c['name'] }}</td><td class="r">{{ \App\Support\Money::num($c['quantity'], 1) }}</td><td class="r">{{ $rp($c['gross_item_sales']) }}</td><td class="r">{{ $rp($c['net_sales']) }}</td></tr>
    @endforeach
</table>
@endif

@if(count($products))
<h2>Produk teratas</h2>
<table>
    <tr><th>Produk</th><th class="r">Qty</th><th class="r">Kotor</th><th class="r">Bersih</th><th class="r">Cakupan HPP</th></tr>
    @foreach($products->take(40) as $p)
    <tr><td>{{ $p['name'] }}{{ $p['variant'] ? ' - '.$p['variant'] : '' }}</td><td class="r">{{ \App\Support\Money::num($p['quantity'], 1) }}</td><td class="r">{{ $rp($p['gross_item_sales']) }}</td><td class="r">{{ $rp($p['net_sales']) }}</td><td class="r">{{ $p['cost_coverage_pct'] === null ? 'N/A' : \App\Support\Money::num($p['cost_coverage_pct'], 0).'%' }}</td></tr>
    @endforeach
</table>
@endif

<h2>Daftar order{{ $orders_truncated ? ' ('.count($orders).' dari '.$orders_total.' order, unduh Excel untuk seluruhnya)' : '' }}</h2>
<table>
    <tr><th>No. order</th><th>Waktu</th><th>Outlet</th><th>Kasir</th><th class="r">Bersih</th><th class="r">Total tagihan</th><th>Bayar</th></tr>
    @foreach($orders as $o)
    <tr>
        <td>{{ $o->order_number }}</td><td>{{ $o->created_at->setTimezone($f->timezone)->format('d M H:i') }}</td><td>{{ $o->outlet?->name }}</td><td>{{ $o->user?->name }}</td>
        <td class="r">{{ $rp((float) $o->subtotal - (float) $o->discount_amount) }}</td><td class="r">{{ $rp((float) $o->grand_total) }}</td>
        <td>{{ $o->is_split_payment ? $o->payments->pluck('payment_method')->unique()->map(fn ($x) => \App\Support\Money::method($x))->implode(' + ') : \App\Support\Money::method($o->payment_method) }}</td>
    </tr>
    @endforeach
</table>

<div class="foot">
    Order berstatus paid; refund ({{ $summary['excluded']['refunded']['orders'] }} order) dan batal ({{ $summary['excluded']['cancelled']['orders'] }} order) dilaporkan terpisah.
    Pembayaran adalah catatan POS, bukan settlement gateway. Estimasi laba kotor {{ $rp($m['gross_profit_estimate']) }} bukan laba bersih.
</div>
</div>
</body>
</html>
