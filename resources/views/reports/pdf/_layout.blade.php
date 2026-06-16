<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size: 11px; color: #1f2937; background: #fff; }
  .page-header { background: #059669; color: #fff; padding: 14px 20px; margin-bottom: 16px; }
  .page-header h1 { font-size: 18px; margin: 0; }
  .page-header p  { font-size: 11px; opacity: 0.85; margin: 2px 0 0; }
  .meta { padding: 0 20px 12px; display: flex; gap: 20px; font-size: 11px; color: #6b7280; }
  .meta strong { color: #1f2937; }
  .content { padding: 0 20px; }
  .stat-row { display: flex; gap: 10px; margin-bottom: 16px; }
  .stat-box { flex: 1; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; text-align: center; }
  .stat-box .num { font-size: 16px; font-weight: 700; color: #059669; }
  .stat-box .lbl { font-size: 9px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; }
  table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 12px; }
  th { background: #059669; color: #fff; padding: 7px 10px; text-align: left; font-size: 10px; text-transform: uppercase; }
  td { padding: 6px 10px; border-bottom: 1px solid #f3f4f6; }
  tr:nth-child(even) td { background: #f9fafb; }
  tfoot td { background: #ecfdf5; font-weight: 700; border-top: 2px solid #059669; }
  .footer { margin-top: 20px; padding: 10px 20px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 9px; color: #9ca3af; }
</style>
</head>
<body>

<div class="page-header">
    <h1>@yield('title')</h1>
    <p>{{ $business->name ?? '' }} &nbsp;|&nbsp; Dicetak: {{ now()->format('d M Y H:i') }}</p>
</div>

<div class="meta">
    <span>Periode: <strong>{{ $f['date_from'] }} s/d {{ $f['date_to'] }}</strong></span>
    @isset($outletLabel)
    <span>Outlet: <strong>{{ $outletLabel }}</strong></span>
    @endisset
</div>

<div class="content">
    @yield('content')
</div>

<div class="footer">
    Laporan ini digenerate otomatis oleh FNB POS System — {{ config('app.url') }}
</div>
</body>
</html>
