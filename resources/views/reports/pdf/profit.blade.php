@extends('reports.pdf._layout')
@section('title', 'Laporan Estimasi Profit')

@section('content')
<div class="stat-row">
    <div class="stat-box">
        <div class="num">Rp {{ number_format($totalRevenue,0,',','.') }}</div>
        <div class="lbl">Total Revenue</div>
    </div>
    <div class="stat-box">
        <div class="num" style="color:#d97706;">Rp {{ number_format($totalCogs,0,',','.') }}</div>
        <div class="lbl">Total HPP (COGS)</div>
    </div>
    <div class="stat-box">
        <div class="num" style="color:#1d4ed8;">Rp {{ number_format($totalProfit,0,',','.') }}</div>
        <div class="lbl">Estimasi Profit</div>
    </div>
    <div class="stat-box">
        <div class="num" style="color:#7c3aed;">{{ number_format($margin,1) }}%</div>
        <div class="lbl">Gross Margin</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Tanggal</th>
            <th style="text-align:right;">Revenue (Rp)</th>
            <th style="text-align:right;">HPP (Rp)</th>
            <th style="text-align:right;">Profit (Rp)</th>
            <th style="text-align:right;">Margin (%)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
        @php $p = $row->revenue - $row->cogs; $m = $row->revenue > 0 ? round($p/$row->revenue*100,1) : 0; @endphp
        <tr>
            <td>{{ \Carbon\Carbon::parse($row->date)->format('d/m/Y') }}</td>
            <td style="text-align:right; color:#059669;">Rp {{ number_format($row->revenue,0,',','.') }}</td>
            <td style="text-align:right; color:#d97706;">Rp {{ number_format($row->cogs,0,',','.') }}</td>
            <td style="text-align:right; font-weight:700; color:{{ $p >= 0 ? '#1d4ed8' : '#dc2626' }};">
                Rp {{ number_format($p,0,',','.') }}
            </td>
            <td style="text-align:right;">{{ $m }}%</td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center; color:#9ca3af;">Tidak ada data</td></tr>
        @endforelse
    </tbody>
    @if(count($rows))
    <tfoot>
        <tr>
            <td>TOTAL</td>
            <td style="text-align:right;">Rp {{ number_format($totalRevenue,0,',','.') }}</td>
            <td style="text-align:right;">Rp {{ number_format($totalCogs,0,',','.') }}</td>
            <td style="text-align:right;">Rp {{ number_format($totalProfit,0,',','.') }}</td>
            <td style="text-align:right;">{{ number_format($margin,1) }}%</td>
        </tr>
    </tfoot>
    @endif
</table>
@endsection
