@php
    $amount = (float) ($sub->amount ?? $sub->plan?->price ?? 0);
    $months = $sub->months ?: 1;
    $unit = $months > 0 ? $amount / $months : $amount;
    [$stamp, $stampColor, $statusText] = match ($sub->status) {
        'active', 'expired' => ['LUNAS', '#15803D', 'Lunas'],
        'trial' => ['MASA COBA', '#B45309', 'Masa coba (tanpa biaya)'],
        'pending' => ['BELUM DIBAYAR', '#B45309', 'Menunggu pembayaran'],
        'cancelled' => ['DIBATALKAN', '#B91C1C', 'Dibatalkan / gagal'],
        default => [strtoupper($sub->status), '#475569', ucfirst($sub->status)],
    };
    $rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.');
    $period = $sub->ends_at
        ? ($months === 1 ? '30 hari' : $months.' bulan').', berlaku s.d. '.$sub->ends_at->translatedFormat('d F Y')
        : '—';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice {{ $number }}</title>
<style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Helvetica Neue",Arial,sans-serif;color:#1F2937;background:#EEF0F3;font-size:13px;line-height:1.5}
    .bar{max-width:820px;margin:24px auto 0;display:flex;justify-content:space-between;align-items:center}
    .bar a,.bar button{font:inherit;font-size:13px;color:#0B1B3F;text-decoration:none;background:none;border:0;cursor:pointer}
    .bar button{background:#0B1B3F;color:#fff;padding:8px 16px;border-radius:6px;font-weight:600}
    .sheet{position:relative;max-width:820px;margin:12px auto 40px;background:#fff;padding:56px 60px;border:1px solid #D9DDE3;overflow:hidden}
    .head{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:24px;border-bottom:2px solid #0B1B3F}
    .head img{height:34px}
    .issuer{margin-top:10px;font-size:12px;color:#475569}
    .doc{text-align:right}
    .doc h1{font-size:26px;letter-spacing:4px;color:#0B1B3F;font-weight:700}
    .doc table{margin-left:auto;margin-top:8px;font-size:12px}
    .doc td{padding:1px 0 1px 14px}
    .doc td:first-child{color:#64748B}
    .parties{display:flex;gap:40px;margin-top:28px}
    .parties>div{flex:1}
    .label{font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:#64748B;font-weight:700;margin-bottom:6px}
    .strong{font-weight:700;color:#0B1B3F;font-size:14px}
    table.items{width:100%;border-collapse:collapse;margin-top:32px}
    table.items th{background:#0B1B3F;color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:1px;text-align:left;padding:9px 12px}
    table.items td{padding:12px;border-bottom:1px solid #E5E7EB;vertical-align:top}
    .r{text-align:right}
    .num{font-variant-numeric:tabular-nums;white-space:nowrap}
    .totals{width:300px;margin-left:auto;margin-top:12px;border-collapse:collapse}
    .totals td{padding:6px 12px}
    .totals tr.grand td{border-top:2px solid #0B1B3F;font-weight:700;font-size:15px;color:#0B1B3F;padding-top:10px}
    .terbilang{margin-top:14px;padding:10px 12px;background:#F8FAFC;border-left:3px solid #0B1B3F;font-style:italic;font-size:12px}
    .foot{display:flex;justify-content:space-between;gap:40px;margin-top:40px}
    .notes{flex:1;font-size:12px;color:#475569}
    .notes p+p{margin-top:4px}
    .sign{width:220px;text-align:center;font-size:12px}
    .sign .space{height:70px}
    .sign .name{font-weight:700;color:#0B1B3F;border-top:1px solid #1F2937;padding-top:4px}
    .stamp{position:absolute;top:210px;right:70px;transform:rotate(-14deg);border:3px solid {{ $stampColor }};color:{{ $stampColor }};font-weight:800;font-size:24px;letter-spacing:3px;padding:6px 18px;border-radius:6px;opacity:.55}
    .legal{margin-top:36px;padding-top:12px;border-top:1px solid #E5E7EB;font-size:10.5px;color:#94A3B8;text-align:center}
    @media print{
        body{background:#fff}
        .bar{display:none}
        .sheet{margin:0;border:0;padding:24px 8px;max-width:none}
        @page{size:A4;margin:14mm}
    }
</style>
</head>
<body>
<div class="bar">
    <a href="{{ route('saas.plans') }}">&larr; Kembali ke Langganan</a>
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
</div>

<div class="sheet">
    <div class="stamp">{{ $stamp }}</div>

    <div class="head">
        <div>
            <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka POS">
            <div class="issuer">
                <div class="strong" style="font-size:13px">{{ config('yoka_invoice.issuer_name') }}</div>
                Layanan Aplikasi Kasir &amp; Manajemen Usaha<br>
                @if(config('yoka_invoice.address')){{ config('yoka_invoice.address') }}<br>@endif
                yokapos.com @if(config('yoka_invoice.email'))&middot; {{ config('yoka_invoice.email') }}@endif
                @if(config('yoka_invoice.nib') || config('yoka_invoice.npwp'))<br>
                    @if(config('yoka_invoice.nib'))NIB {{ config('yoka_invoice.nib') }}@endif
                    @if(config('yoka_invoice.nib') && config('yoka_invoice.npwp')) &middot; @endif
                    @if(config('yoka_invoice.npwp'))NPWP {{ config('yoka_invoice.npwp') }}@endif
                @endif
            </div>
        </div>
        <div class="doc">
            <h1>INVOICE</h1>
            <table>
                <tr><td>Nomor</td><td><strong>{{ $number }}</strong></td></tr>
                <tr><td>Tanggal terbit</td><td>{{ $sub->created_at->translatedFormat('d F Y') }}</td></tr>
                @if($sub->paid_at)
                    <tr><td>Tanggal bayar</td><td>{{ $sub->paid_at->translatedFormat('d F Y, H:i') }}</td></tr>
                @endif
                <tr><td>Status</td><td><strong style="color:{{ $stampColor }}">{{ $statusText }}</strong></td></tr>
            </table>
        </div>
    </div>

    <div class="parties">
        <div>
            <div class="label">Ditagihkan kepada</div>
            <div class="strong">{{ $business->name }}</div>
            <div>{{ $owner->name }}</div>
            @if($business->address)<div>{{ $business->address }}{{ $business->city ? ', '.$business->city : '' }}</div>@endif
            @if($business->phone)<div>{{ $business->phone }}</div>@endif
            @if($business->email ?? $owner->email)<div>{{ $business->email ?? $owner->email }}</div>@endif
        </div>
        <div>
            <div class="label">Rincian transaksi</div>
            <div>ID Pesanan: <span class="num">{{ $sub->payment_token ?: '—' }}</span></div>
            <div>Metode: {{ $amount > 0 ? 'Midtrans (pembayaran online)' : 'Tanpa biaya' }}</div>
            <div>Masa layanan: {{ $sub->starts_at?->translatedFormat('d M Y') ?? '—' }} &ndash; {{ $sub->ends_at?->translatedFormat('d M Y') ?? '—' }}</div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr><th style="width:40px">No</th><th>Deskripsi</th><th class="r">Jumlah</th><th class="r">Harga satuan</th><th class="r">Subtotal</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>
                    <strong>Langganan Yoka POS &ndash; Paket {{ $sub->plan?->name ?? 'Yoka' }}</strong><br>
                    <span style="color:#64748B;font-size:12px">{{ $period }}</span>
                </td>
                <td class="r num">{{ $months }} bulan</td>
                <td class="r num">{{ $rp($unit) }}</td>
                <td class="r num">{{ $rp($amount) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="r num">{{ $rp($amount) }}</td></tr>
        <tr><td>Diskon</td><td class="r num">{{ $rp(0) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="r num">{{ $rp($amount) }}</td></tr>
        <tr><td>{{ in_array($sub->status, ['active','expired']) ? 'Telah dibayar' : 'Sisa tagihan' }}</td><td class="r num">{{ in_array($sub->status, ['active','expired']) ? $rp($amount) : ($sub->status === 'cancelled' ? $rp(0) : $rp($amount)) }}</td></tr>
    </table>

    <div class="terbilang">Terbilang: {{ \App\Support\Terbilang::rupiah($amount) }}</div>

    <div class="foot">
        <div class="notes">
            <div class="label">Catatan</div>
            @if($sub->status === 'pending')
                <p>Tagihan ini belum dibayar. Selesaikan pembayaran melalui halaman Langganan di aplikasi Yoka POS.</p>
            @elseif($sub->status === 'cancelled')
                <p>Transaksi ini dibatalkan atau gagal diproses. Tidak ada dana yang ditagihkan untuk invoice ini.</p>
            @else
                <p>Pembayaran telah diterima. Terima kasih telah menggunakan Yoka POS.</p>
            @endif
            <p>Harga sudah termasuk seluruh biaya layanan.</p>
        </div>
        <div class="sign">
            <div>Hormat kami,</div>
            <div class="space"></div>
            <div class="name">{{ config('yoka_invoice.signer') }}</div>
            <div style="color:#64748B">{{ config('yoka_invoice.issuer_name') }}</div>
        </div>
    </div>

    <div class="legal">Dokumen ini diterbitkan secara elektronik oleh sistem Yoka POS dan sah tanpa tanda tangan basah.</div>
</div>
</body>
</html>
