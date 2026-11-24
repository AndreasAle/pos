{{--
    Detail order versi aplikasi: dibaca seperti struk, tanpa kartu.
    Total di atas, item, rincian, lalu tombol cetak. Modal void/refund tetap dari show.blade.php.
--}}
@php
    $tz       = config('app.timezone');
    $payLabel = ['cash' => 'Tunai', 'qris' => 'QRIS', 'debit' => 'Debit', 'credit' => 'Kartu kredit', 'transfer' => 'Transfer', 'ewallet' => 'E-wallet', 'split' => 'Gabungan'];
    $pay      = fn ($m) => $payLabel[$m] ?? ucfirst((string) $m);
    $statusTone = ['draft' => ['Draft', '#94A3B8'], 'pending' => ['Menunggu pembayaran', '#D97706'], 'cancelled' => ['Dibatalkan', '#DC2626'], 'refunded' => ['Direfund', '#9333EA']];
    $canReverse = $order->status === 'paid' && in_array(auth()->user()->role, ['owner', 'admin'], true);
    $row = 'flex justify-between gap-4 py-1.5';
@endphp

<div class="max-w-xl mx-auto pt-6 pb-4 text-[#0B1B3F]">

    {{-- Total --}}
    @if($tone = $statusTone[$order->status] ?? null)
        <p class="inline-flex items-center gap-2 text-[13px] font-semibold" style="color: {{ $tone[1] }}">
            <span class="w-1.5 h-1.5 rounded-full" style="background: {{ $tone[1] }}"></span>{{ $tone[0] }}
        </p>
    @endif
    <p class="mt-1 text-[40px] leading-none font-semibold tracking-[-0.02em] tabular-nums {{ in_array($order->status, ['cancelled', 'refunded']) ? 'text-[#94A3B8] line-through decoration-2' : '' }}">{{ $rp($order->grand_total) }}</p>
    <p class="mt-3 text-[15px] text-[#64748B]">
        {{ $pay($order->payment_method) }} · {{ $typeLabels[$type] ?? 'Dine In' }} ·
        {{ $order->created_at->timezone($tz)->locale('id')->translatedFormat('l j M, H:i') }}
    </p>

    @if($order->offline_review)
        <p class="mt-4 text-[14px] text-[#B45309]">Transaksi offline ini perlu dicek pemilik.</p>
    @endif

    {{-- Item --}}
    <ul class="mt-8 border-t border-[#F1F5F9]">
        @foreach($order->items as $item)
            <li class="flex gap-4 py-4 border-b border-[#F1F5F9]">
                <span class="w-8 shrink-0 text-[15px] font-semibold tabular-nums text-[#94A3B8]">{{ number_format($item->qty, 0) }}×</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[16px] font-medium">{{ $item->product_name }}</span>
                    @if($item->variant_name)<span class="block text-[13px] text-[#94A3B8]">{{ $item->variant_name }}</span>@endif
                    @foreach($item->addons as $addon)
                        <span class="block text-[13px] text-[#94A3B8]">+ {{ $addon->addon_name }} · {{ $rp($addon->price) }}</span>
                    @endforeach
                    @if($item->notes)<span class="block text-[13px] text-[#B45309]">{{ $item->notes }}</span>@endif
                    @if($item->qty > 1)<span class="block text-[13px] text-[#94A3B8] tabular-nums">@ {{ $rp($item->price) }}</span>@endif
                </span>
                <span class="shrink-0 text-[16px] tabular-nums">{{ $rp($item->subtotal) }}</span>
            </li>
        @endforeach
    </ul>

    {{-- Rincian --}}
    <dl class="mt-4 text-[15px]">
        <div class="{{ $row }} text-[#64748B]"><dt>Subtotal</dt><dd class="tabular-nums">{{ $rp($order->subtotal) }}</dd></div>
        @if($order->discount_amount > 0)<div class="{{ $row }} text-[#64748B]"><dt>Diskon</dt><dd class="tabular-nums text-[#DC2626]">−{{ $rp($order->discount_amount) }}</dd></div>@endif
        @if($order->tax_amount > 0)<div class="{{ $row }} text-[#64748B]"><dt>Pajak</dt><dd class="tabular-nums">{{ $rp($order->tax_amount) }}</dd></div>@endif
        @if($order->service_amount > 0)<div class="{{ $row }} text-[#64748B]"><dt>Service</dt><dd class="tabular-nums">{{ $rp($order->service_amount) }}</dd></div>@endif
        @if($order->delivery_fee > 0)<div class="{{ $row }} text-[#64748B]"><dt>Ongkos kirim</dt><dd class="tabular-nums">{{ $rp($order->delivery_fee) }}</dd></div>@endif
        <div class="{{ $row }} pt-3 mt-2 border-t border-[#F1F5F9] text-[17px] font-semibold"><dt>Total</dt><dd class="tabular-nums">{{ $rp($order->grand_total) }}</dd></div>
        @if($order->is_split_payment && $order->payments->count())
            @foreach($order->payments as $p)
                <div class="{{ $row }} text-[#64748B]"><dt>{{ $pay($p->payment_method) }}</dt><dd class="tabular-nums">{{ $rp($p->amount) }}</dd></div>
            @endforeach
        @elseif($order->paid_amount > 0)
            <div class="{{ $row }} text-[#64748B]"><dt>Dibayar · {{ $pay($order->payment_method) }}</dt><dd class="tabular-nums">{{ $rp($order->paid_amount) }}</dd></div>
        @endif
        @if($order->change_amount > 0)<div class="{{ $row }} text-[#64748B]"><dt>Kembalian</dt><dd class="tabular-nums">{{ $rp($order->change_amount) }}</dd></div>@endif
    </dl>

    {{-- Keterangan --}}
    <dl class="mt-8 pt-5 border-t border-[#F1F5F9] text-[14px] space-y-2">
        <div class="flex justify-between gap-4"><dt class="text-[#94A3B8]">Kasir</dt><dd class="text-right">{{ $order->user?->name ?? 'Self order' }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-[#94A3B8]">Outlet</dt><dd class="text-right">{{ $order->outlet->name }}</dd></div>
        @if($order->customer)<div class="flex justify-between gap-4"><dt class="text-[#94A3B8]">Pelanggan</dt><dd class="text-right">{{ $order->customer->name }}</dd></div>@endif
        @if($order->customer_address)<div class="flex justify-between gap-4"><dt class="text-[#94A3B8]">Alamat</dt><dd class="text-right">{{ $order->customer_address }}</dd></div>@endif
        <div class="flex justify-between gap-4"><dt class="text-[#94A3B8]">No. order</dt><dd class="text-right tabular-nums">{{ $order->order_number }}</dd></div>
        @if($order->notes)<div class="flex justify-between gap-4"><dt class="text-[#94A3B8]">Catatan</dt><dd class="text-right">{{ $order->notes }}</dd></div>@endif
    </dl>

    {{-- Aksi --}}
    <div class="mt-10 flex items-center gap-3">
        <a href="{{ route('receipt.print', $order) }}" class="flex-1 h-14 inline-flex items-center justify-center rounded-2xl bg-[#16A34A] active:bg-[#15803D] text-white text-[16px] font-semibold shadow-[0_8px_20px_rgba(22,163,74,.22)]">Cetak struk</a>
        <a href="{{ route('receipt.pdf', $order) }}" class="h-14 px-6 inline-flex items-center justify-center rounded-2xl bg-[#F1F5F9] active:bg-[#E2E8F0] text-[16px] font-semibold">PDF</a>
    </div>
    @if($canReverse)
        <div class="mt-6 flex justify-center gap-8 text-[15px] font-semibold">
            <button type="button" onclick="openModal('refund-modal')" class="text-[#475569]">Refund</button>
            <button type="button" onclick="openModal('void-modal')" class="text-[#DC2626]">Batalkan order</button>
        </div>
    @endif
</div>
