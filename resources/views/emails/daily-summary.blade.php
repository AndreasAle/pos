@extends('emails.layout')

@section('content')
<p style="font-size:16px; font-weight:600; color:#111827; margin-bottom:4px;">
    📊 Ringkasan Penjualan Hari Ini
</p>
<p style="color:#6b7280; font-size:13px; margin-bottom:20px;">
    {{ now()->subDay()->format('d M Y') }}
</p>

{{-- Stats --}}
<div style="text-align:center; margin-bottom:20px;">
    <div class="stat">
        <div class="num">Rp {{ number_format($summary['todayRevenue'],0,',','.') }}</div>
        <div class="lbl">Total Omzet</div>
    </div>
    <div class="stat">
        <div class="num">{{ $summary['todayOrders'] }}</div>
        <div class="lbl">Transaksi</div>
    </div>
    <div class="stat" style="background:#eff6ff;">
        <div class="num" style="color:#1d4ed8;">Rp {{ number_format($summary['todayProfit'],0,',','.') }}</div>
        <div class="lbl">Est. Profit</div>
    </div>
    <div class="stat" style="background:#fefce8;">
        <div class="num" style="color:#a16207;">Rp {{ number_format($summary['avgOrder'],0,',','.') }}</div>
        <div class="lbl">Avg Order</div>
    </div>
</div>

{{-- Top Products --}}
@if(count($topProducts))
<div class="card">
    <p style="font-weight:600; margin:0 0 10px;">🏆 Menu Terlaris Hari Ini</p>
    <table class="data">
        <thead><tr><th>#</th><th>Produk</th><th>Qty</th><th>Revenue</th></tr></thead>
        <tbody>
            @foreach($topProducts as $i => $p)
            <tr>
                <td>{{ $i+1 }}</td>
                <td><strong>{{ $p['product_name'] }}</strong></td>
                <td>{{ number_format($p['total_qty'],0) }}</td>
                <td>Rp {{ number_format($p['total_revenue'],0,',','.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Low Stock Warning --}}
@if(count($lowStock))
<div class="card" style="border-color:#fecaca; background:#fff5f5;">
    <p style="font-weight:600; color:#b91c1c; margin:0 0 10px;">⚠ Stok Menipis ({{ count($lowStock) }} bahan)</p>
    @foreach($lowStock as $ing)
    <div style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid #fee2e2; font-size:13px;">
        <span>{{ $ing['name'] }}</span>
        <span style="color:#dc2626; font-weight:600;">{{ number_format($ing['current_stock'],2) }} {{ $ing['unit'] }}</span>
    </div>
    @endforeach
    <a href="{{ config('app.url') }}/inventory/ingredients" style="color:#059669; font-size:12px; display:block; margin-top:8px;">Kelola stok →</a>
</div>
@endif

<a href="{{ config('app.url') }}/dashboard" class="btn">
    Lihat Dashboard Lengkap →
</a>
@endsection
