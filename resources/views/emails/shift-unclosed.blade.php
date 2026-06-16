@extends('emails.layout')

@section('content')
<p style="font-size:16px; font-weight:600; color:#b45309; margin-bottom:16px;">
    ⚠ Shift Kasir Belum Ditutup
</p>

<div class="card">
    <table style="width:100%; font-size:14px;">
        <tr><td style="color:#6b7280; padding:6px 0;">Kasir</td><td style="font-weight:600;">{{ $shift->user->name }}</td></tr>
        <tr><td style="color:#6b7280; padding:6px 0;">Outlet</td><td>{{ $shift->outlet->name }}</td></tr>
        <tr><td style="color:#6b7280; padding:6px 0;">Dibuka pada</td><td>{{ $shift->opened_at->format('d M Y, H:i') }}</td></tr>
        <tr><td style="color:#6b7280; padding:6px 0;">Durasi terbuka</td><td style="color:#dc2626; font-weight:600;">{{ $shift->opened_at->diffForHumans(null, true) }}</td></tr>
        <tr><td style="color:#6b7280; padding:6px 0;">Modal awal</td><td>Rp {{ number_format($shift->opening_cash, 0, ',', '.') }}</td></tr>
    </table>
</div>

<p style="color:#4b5563; font-size:13px;">
    Shift ini sudah terbuka lebih dari <strong>{{ $shift->opened_at->diffInHours(now()) }} jam</strong>.
    Harap segera tutup shift untuk memastikan laporan keuangan akurat.
</p>

<a href="{{ config('app.url') }}/shifts" class="btn">
    Tutup Shift Sekarang →
</a>
@endsection
