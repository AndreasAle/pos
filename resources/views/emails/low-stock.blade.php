@extends('emails.layout')

@section('content')
<p style="font-size:16px; font-weight:600; color:#b91c1c; margin-bottom:16px;">
    ⚠ Stok Bahan Baku Menipis
</p>
<p style="color:#4b5563; margin-bottom:20px;">
    Berikut adalah daftar bahan baku yang stoknya sudah di bawah batas minimum dan perlu segera ditambah:
</p>

<div class="card">
    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>Nama Bahan</th>
                <th>Stok Saat Ini</th>
                <th>Stok Minimum</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ingredients as $i => $ing)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td><strong>{{ $ing['name'] }}</strong></td>
                <td style="color:#dc2626; font-weight:600;">
                    {{ number_format($ing['current_stock'],2) }} {{ $ing['unit'] }}
                </td>
                <td>{{ number_format($ing['minimum_stock'],2) }} {{ $ing['unit'] }}</td>
                <td>
                    @if((float)$ing['current_stock'] <= 0)
                        <span class="badge badge-red">HABIS</span>
                    @else
                        <span class="badge badge-orange">MENIPIS</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<p style="color:#6b7280; font-size:13px;">
    Total <strong>{{ count($ingredients) }} bahan</strong> perlu segera ditambah stoknya.
    Segera lakukan pembelian atau penyesuaian stok.
</p>

<a href="{{ config('app.url') }}/inventory/ingredients" class="btn">
    Kelola Stok Sekarang →
</a>
@endsection
