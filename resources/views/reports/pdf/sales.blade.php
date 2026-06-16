@extends('reports.pdf._layout')
@section('title', 'Laporan Penjualan')

@section('content')
{{-- Summary stats --}}
<div class="stat-row">
    <div class="stat-box">
        <div class="num">Rp {{ number_format($summary['total_revenue'],0,',','.') }}</div>
        <div class="lbl">Total Omzet</div>
    </div>
    <div class="stat-box">
        <div class="num" style="color:#1d4ed8;">{{ $summary['total_orders'] }}</div>
        <div class="lbl">Total Transaksi</div>
    </div>
    <div class="stat-box">
        <div class="num" style="color:#7c3aed;">Rp {{ number_format($summary['avg_order'],0,',','.') }}</div>
        <div class="lbl">Rata-rata Order</div>
    </div>
    <div class="stat-box">
        <div class="num" style="color:#d97706;">Rp {{ number_format($summary['total_discount'],0,',','.') }}</div>
        <div class="lbl">Total Diskon</div>
    </div>
</div>

{{-- Payment Breakdown --}}
@if(count($paymentBreakdown))
<h3 style="font-size:12px; margin-bottom:6px; color:#374151;">Breakdown Pembayaran</h3>
<table>
    <thead><tr><th>Metode</th><th>Transaksi</th><th style="text-align:right;">Total (Rp)</th><th style="text-align:right;">%</th></tr></thead>
    <tbody>
        @php $grandTotal = $paymentBreakdown->sum('total'); @endphp
        @foreach($paymentBreakdown as $p)
        <tr>
            <td style="text-transform:uppercase; font-weight:600;">{{ $p->payment_method }}</td>
            <td>{{ $p->count }}x</td>
            <td style="text-align:right;">Rp {{ number_format($p->total,0,',','.') }}</td>
            <td style="text-align:right;">{{ $grandTotal > 0 ? number_format($p->total/$grandTotal*100,1) : 0 }}%</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Daily table --}}
<h3 style="font-size:12px; margin-bottom:6px; color:#374151; margin-top:12px;">Ringkasan Harian</h3>
<table>
    <thead>
        <tr><th>Tanggal</th><th>Hari</th><th style="text-align:right;">Transaksi</th><th style="text-align:right;">Omzet (Rp)</th></tr>
    </thead>
    <tbody>
        @forelse($daily as $row)
        <tr>
            <td>{{ \Carbon\Carbon::parse($row->date)->format('d/m/Y') }}</td>
            <td>{{ \Carbon\Carbon::parse($row->date)->isoFormat('dddd') }}</td>
            <td style="text-align:right;">{{ $row->orders }}</td>
            <td style="text-align:right; font-weight:600;">Rp {{ number_format($row->revenue,0,',','.') }}</td>
        </tr>
        @empty
        <tr><td colspan="4" style="text-align:center; color:#9ca3af;">Tidak ada data</td></tr>
        @endforelse
    </tbody>
    @if(count($daily))
    <tfoot>
        <tr>
            <td colspan="2">TOTAL</td>
            <td style="text-align:right;">{{ $summary['total_orders'] }}</td>
            <td style="text-align:right;">Rp {{ number_format($summary['total_revenue'],0,',','.') }}</td>
        </tr>
    </tfoot>
    @endif
</table>
@endsection
