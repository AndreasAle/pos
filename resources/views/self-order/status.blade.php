<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    @include('layouts._fonts') @vite(['resources/css/app.css'])
    <title>Pesanan {{ $order->order_number }}</title>
    <style>
        :root{--green:#16834a;--dark:#075e3d;--soft:#eaf7ef;--navy:#152238;--muted:#637083;--line:#e3e9e5}*{box-sizing:border-box}
        body{margin:0;background:#f3f6f4;color:var(--navy);font-family:inherit}.page{width:min(100%,620px);margin:auto;min-height:100vh;background:#fff;padding-bottom:calc(30px + env(safe-area-inset-bottom))}
        header{display:flex;align-items:center;gap:12px;background:var(--dark);color:#fff;padding:max(16px,env(safe-area-inset-top)) 18px 16px}header img{width:42px;height:42px;border-radius:13px;background:#fff;padding:5px}header b{display:block;font-size:17px}header small{display:block;margin-top:2px;color:#cce8da}
        main{padding:20px 18px}.success{text-align:center;padding:7px 0 20px}.check{width:64px;height:64px;display:grid;place-items:center;margin:auto;border-radius:50%;background:var(--soft);color:var(--green);font-size:34px;font-weight:900}.success h1{margin:13px 0 5px;font-size:24px}.success p{margin:0;color:var(--muted);font-size:14px}
        .number{border:1.5px dashed #a9cdb8;border-radius:16px;background:#f8fcfa;padding:15px;text-align:center}.number small{display:block;color:var(--muted);font-size:12px}.number strong{display:block;margin-top:5px;color:var(--dark);font-size:22px;letter-spacing:.02em}.hint{margin:9px 0 0;color:var(--muted);font-size:12px}
        .progress{display:flex;align-items:center;margin:22px 0}.progress div{flex:1;text-align:center;color:var(--muted);font-size:11px;font-weight:700}.progress span{width:28px;height:28px;display:grid;place-items:center;margin:0 auto 6px;border-radius:50%;background:#edf1ef}.progress .active{color:var(--dark)}.progress .active span{background:var(--green);color:#fff}.progress i{width:24px;height:1px;background:#dce2df}
        .panel{border:1px solid var(--line);border-radius:17px;overflow:hidden;margin-top:14px}.panel-title{padding:13px 15px;border-bottom:1px solid var(--line);font-size:14px;font-weight:900}.line{display:flex;justify-content:space-between;gap:12px;padding:11px 15px;font-size:14px}.line span:last-child{white-space:nowrap}.total{display:flex;justify-content:space-between;padding:14px 15px;border-top:1px solid var(--line);font-weight:900;font-size:17px}
        .payment{margin-top:15px;border-radius:17px;padding:16px;background:#fff8e7;border:1px solid #f2d697}.payment.green{background:var(--soft);border-color:#b9dfc8}.payment h2{margin:0;font-size:17px}.payment p{margin:6px 0 0;color:#66562e;font-size:13px;line-height:1.45}.payment.green p{color:#38604a}.qr{display:block;width:230px;height:230px;object-fit:contain;margin:15px auto 10px;padding:8px;border-radius:16px;background:#fff}.primary{display:flex;align-items:center;justify-content:center;width:100%;height:52px;margin-top:12px;border-radius:14px;background:var(--green);color:#fff;text-decoration:none;font-weight:900}.secondary{display:flex;align-items:center;justify-content:center;width:100%;height:50px;margin-top:13px;border:1.5px solid #cbd8d0;border-radius:14px;color:var(--dark);text-decoration:none;font-weight:800}
    </style>
</head>
<body><div class="page">
    <header><img src="{{ asset('images/brand/yoka-icon.png') }}" alt="Yoka"><div><b>{{ $session->table->business->name }}</b><small>Meja {{ $order->table_number }}</small></div></header>
    <main>
        <section class="success"><div class="check">✓</div><h1>Pesanan sudah dikirim</h1><p>Dapur dan kasir dapat melihat pesanan Anda.</p></section>
        <div class="number"><small>Nomor pesanan</small><strong>{{ $order->order_number }}</strong><p class="hint">Simpan halaman ini sampai pesanan selesai.</p></div>
        <div class="progress"><div class="active"><span>1</span>Dikirim</div><i></i><div><span>2</span>Diproses</div><i></i><div><span>3</span>Selesai</div></div>
        <section class="panel"><div class="panel-title">Rincian pesanan</div>
            @foreach($order->items as $item)<div class="line"><span><b>{{ (float)$item->qty }}×</b> {{ $item->product_name }}</span><span>Rp {{ number_format($item->subtotal,0,',','.') }}</span></div>@endforeach
            <div class="total"><span>Total</span><span>Rp {{ number_format($order->grand_total,0,',','.') }}</span></div>
        </section>
        @if($order->payment_method==='qris' && ($order->qris_url || $order->payment_token))
            <section class="payment green"><h2>Bayar dengan QRIS</h2><p>Scan kode berikut menggunakan aplikasi pembayaran Anda.</p>
                @if($order->payment_token)<img src="{{ route('self-order.payment-qr',[$session->guest_token,$order->order_number]) }}" class="qr" alt="QRIS pembayaran">@endif
                @if($order->qris_url)<a href="{{ $order->qris_url }}" class="primary">Buka pembayaran QRIS</a>@endif
            </section>
        @else
            <section class="payment"><h2>Selanjutnya: bayar di kasir</h2><p>Tunjukkan nomor pesanan <b>{{ $order->order_number }}</b>. Pembayaran dapat dilakukan dengan tunai, QRIS, kartu, atau metode yang tersedia.</p></section>
        @endif
        <a href="{{ route('self-order.menu',$session->table->public_token) }}" class="secondary">Tambah pesanan lagi</a>
    </main>
</div></body></html>
