<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: sans-serif; font-size: 10.5px; color: #102C35; }
  .head { background: #0C7856; color: #fff; padding: 12px 18px; } .head h1 { font-size: 16px; } .head p { font-size: 10px; margin-top: 2px; opacity: .9; }
  .meta { padding: 8px 18px; color: #4b5563; border-bottom: 1px solid #e5e7eb; } .meta strong { color: #102C35; }
  .wrap { padding: 12px 18px; } table { width: 100%; border-collapse: collapse; }
  th { background: #f3f6f5; text-align: left; padding: 5px 7px; font-size: 9px; border-bottom: 1px solid #d1d9d6; }
  td { padding: 4px 7px; border-bottom: 1px solid #eef1f0; } .r { text-align: right; } .b td { font-weight: bold; background: #f6f8f7; } .sec td { font-size: 9px; color: #6b7280; padding-top: 9px; text-transform: uppercase; }
  .warn { margin-bottom: 6px; padding: 6px 9px; background: #fffbeb; border: 1px solid #fde68a; color: #78350f; } .foot { margin-top: 12px; color: #6b7280; font-size: 9px; border-top: 1px solid #e5e7eb; padding-top: 6px; }
</style></head>
<body>
@php
    $rp = fn ($v) => \App\Support\Money::rp($v);
    $row = function ($label, $cur, $pri = null, $bold = false, $in = 0) use ($rp, $prior) {
        return '<tr class="'.($bold ? 'b' : '').'"><td style="padding-left:'.(7 + $in * 12).'px">'.e($label).'</td><td class="r">'.$rp($cur).'</td>'.($prior ? '<td class="r">'.$rp($pri).'</td>' : '').'</tr>';
    };
@endphp
<div class="head"><h1>Laporan Laba Rugi - {{ $business->name }}</h1><p>Dibuat {{ $generated_at->format('d M Y H:i') }} ({{ $f->timezone }})</p></div>
<div class="meta">Periode <strong>{{ $f->dateFrom() }} s/d {{ $f->dateTo() }}</strong> | Outlet <strong>{{ implode(', ', $outlet_names) }}</strong> | Versi metrik <strong>{{ $r['evidence']['metric_version'] }}</strong></div>
<div class="wrap">
@foreach($r['warnings'] as $w)<div class="warn">{{ $w }}</div>@endforeach
<table>
    <tr><th>Pos</th><th class="r">{{ $prior ? 'Periode ini' : 'Jumlah' }}</th>@if($prior)<th class="r">Pembanding</th>@endif</tr>
    {!! $row('Penjualan kotor item', $r['revenue']['gross_item_sales'], $prior['revenue']['gross_item_sales'] ?? null) !!}
    {!! $row('Diskon', -$r['revenue']['discounts'], $prior ? -$prior['revenue']['discounts'] : null, false, 1) !!}
    {!! $row('Penjualan bersih', $r['revenue']['net_sales'], $prior['revenue']['net_sales'] ?? null, true) !!}
    {!! $row('Harga pokok penjualan (HPP)', -$r['cogs'], $prior ? -$prior['cogs'] : null, false, 1) !!}
    {!! $row('LABA KOTOR', $r['gross_profit'], $prior['gross_profit'] ?? null, true) !!}
    <tr class="sec"><td colspan="{{ $prior ? 3 : 2 }}">Beban operasional</td></tr>
    @foreach($r['opex'] as $i => $o){!! $row($o['code'].'  '.$o['name'], -$o['amount'], $prior ? -($prior['opex'][$i]['amount'] ?? 0) : null, false, 1) !!}@endforeach
    {!! $row('Total beban operasional', -$r['opex_total'], $prior ? -$prior['opex_total'] : null, true) !!}
    {!! $row('LABA OPERASIONAL', $r['operating_profit'], $prior['operating_profit'] ?? null, true) !!}
    {!! $row('Beban lain-lain', -$r['other_total'], $prior ? -$prior['other_total'] : null) !!}
    {!! $row('LABA SEBELUM PAJAK PENGHASILAN', $r['profit_before_tax'], $prior['profit_before_tax'] ?? null, true) !!}
</table>
<div class="foot">
    Memo: pajak dipungut {{ $rp($r['memo']['tax_collected']) }}; service {{ $rp($r['memo']['service_amount']) }}; ongkir {{ $rp($r['memo']['delivery_fees']) }}; pembelian diterima {{ $rp($r['memo']['purchases_received']) }} (persediaan, bukan beban).
    Basis kas untuk beban. HPP dari snapshot harga pokok item terjual. Pajak penghasilan tidak dihitung. Laporan manajemen, bukan laporan keuangan teraudit.
</div>
</div></body></html>
