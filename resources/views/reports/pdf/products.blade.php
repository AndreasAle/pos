@extends('reports.pdf._layout')
@section('title', 'Laporan Produk Terlaris')

@section('content')
<table>
    <thead>
        <tr>
            <th style="width:30px">#</th>
            <th>Nama Produk</th>
            <th style="text-align:right;">Qty Terjual</th>
            <th style="text-align:right;">Revenue (Rp)</th>
            <th style="text-align:right;">HPP (Rp)</th>
            <th style="text-align:right;">Est. Profit (Rp)</th>
            <th style="text-align:right;">Margin</th>
        </tr>
    </thead>
    <tbody>
        @php $rank = $products->firstItem(); @endphp
        @forelse($products as $row)
        @php $profit = $row->total_revenue - $row->total_cost; $margin = $row->total_revenue > 0 ? round($profit / $row->total_revenue * 100, 1) : 0; @endphp
        <tr>
            <td style="text-align:center; color:#6b7280;">{{ $rank++ }}</td>
            <td style="font-weight:600;">{{ $row->product_name }}</td>
            <td style="text-align:right;">{{ number_format($row->total_qty,0) }}</td>
            <td style="text-align:right; color:#059669;">Rp {{ number_format($row->total_revenue,0,',','.') }}</td>
            <td style="text-align:right; color:#d97706;">Rp {{ number_format($row->total_cost,0,',','.') }}</td>
            <td style="text-align:right; color:{{ $profit >= 0 ? '#059669' : '#dc2626' }}; font-weight:700;">
                Rp {{ number_format($profit,0,',','.') }}
            </td>
            <td style="text-align:right;">{{ $margin }}%</td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center; color:#9ca3af;">Tidak ada data</td></tr>
        @endforelse
    </tbody>
    @if($products->count())
    <tfoot>
        <tr>
            <td colspan="2">TOTAL</td>
            <td style="text-align:right;">{{ number_format($products->sum('total_qty'),0) }}</td>
            <td style="text-align:right;">Rp {{ number_format($products->sum('total_revenue'),0,',','.') }}</td>
            <td style="text-align:right;">Rp {{ number_format($products->sum('total_cost'),0,',','.') }}</td>
            <td style="text-align:right;">Rp {{ number_format($products->sum('total_revenue') - $products->sum('total_cost'),0,',','.') }}</td>
            <td></td>
        </tr>
    </tfoot>
    @endif
</table>
@endsection
