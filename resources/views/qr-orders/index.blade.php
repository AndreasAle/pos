@extends('layouts.app')
@section('title','Pesanan QR')
@section('page-title','Pesanan QR Masuk')
@section('content')
<div class="page pt-2 text-[#0B1B3F]">
    <div class="mb-5"><h1 class="text-2xl font-bold">Pesanan dari meja</h1><p class="mt-1 text-sm text-[#64748B]">Konfirmasi pesanan sebelum stok berkurang dan tiket masuk ke dapur.</p></div>
    <div class="space-y-4">
    @forelse($orders as $order)
        <article class="rounded-xl border border-[#E5E7EB] bg-white p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><div class="flex items-center gap-2"><h2 class="font-bold">{{ $order->order_number }}</h2><x-pill :tone="$order->acceptance_status==='pending'?'amber':($order->acceptance_status==='accepted'?'green':'red')">{{ ['pending'=>'Menunggu konfirmasi','accepted'=>'Diterima','rejected'=>'Ditolak'][$order->acceptance_status] ?? $order->acceptance_status }}</x-pill></div><p class="mt-1 text-sm text-[#64748B]">Meja {{ $order->table_number }} · {{ $order->outlet->name }} · {{ $order->created_at->diffForHumans() }}</p></div>
                <div class="text-right"><p class="text-xl font-black">Rp {{ number_format($order->grand_total,0,',','.') }}</p><x-pill :tone="$order->payment_status==='paid'?'green':'amber'">{{ $order->payment_status==='paid'?'Lunas':'Belum dibayar' }}</x-pill></div>
            </div>
            <div class="mt-4 rounded-lg bg-[#F8FAFC] p-4 text-sm">@foreach($order->items as $item)<div class="flex justify-between gap-3 py-1"><span><b>{{ (float)$item->qty }}×</b> {{ $item->product_name }} @if($item->variant_name)<span class="text-[#64748B]">· {{ $item->variant_name }}</span>@endif</span><span>Rp {{ number_format($item->subtotal,0,',','.') }}</span></div>@endforeach</div>
            <div class="mt-4 flex flex-wrap justify-end gap-2">
                @if($order->acceptance_status==='pending')
                <form method="POST" action="{{ route('qr-orders.reject',$order) }}">@csrf<input type="hidden" name="reason" value="Tidak dapat diproses"><x-btn type="submit" variant="soft">Tolak</x-btn></form>
                <form method="POST" action="{{ route('qr-orders.accept',$order) }}">@csrf<x-btn type="submit">Terima pesanan</x-btn></form>
                @endif
                @if($order->acceptance_status==='accepted' && $order->payment_status!=='paid')
                @if($order->payment_method==='qris' && $order->qris_url)
                    <a href="{{ $order->qris_url }}" target="_blank" rel="noopener" class="inline-flex h-10 items-center rounded-lg border border-[#16A34A] px-4 text-sm font-bold text-[#15803D]">Buka QR pembayaran</a>
                @endif
                @if($order->payment_method==='qris' && $order->payment_token)
                    <a href="{{ route('qr-orders.payment-qr',$order) }}" target="_blank" class="inline-flex h-10 items-center rounded-lg border border-[#16A34A] px-4 text-sm font-bold text-[#15803D]">Tampilkan QRIS</a>
                @endif
                <form method="POST" action="{{ route('qr-orders.pay',$order) }}" class="flex flex-wrap gap-2">@csrf
                    <select name="payment_method" class="h-10 rounded-lg border-[#D0D5DD] text-sm"><option value="cash">Tunai</option><option value="qris">QRIS di kasir</option><option value="debit">Debit / EDC</option></select>
                    <input type="number" name="paid_amount" value="{{ (int)$order->grand_total }}" min="{{ (int)$order->grand_total }}" class="h-10 w-36 rounded-lg border-[#D0D5DD] text-sm"><x-btn type="submit">Konfirmasi bayar</x-btn>
                </form>
                @endif
            </div>
        </article>
    @empty
        <x-panel><x-empty title="Belum ada pesanan QR" text="Pesanan pelanggan akan muncul di halaman ini." /></x-panel>
    @endforelse
    </div><div class="mt-5">{{ $orders->links() }}</div>
</div>
@endsection
