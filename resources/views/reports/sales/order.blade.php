@extends('reports.sales.layout')

@section('report')
@php
    $card = 'min-w-0 rounded-xl border border-[#E5E7EB] bg-white';
    $tz = $f->timezone;
    $net = (float) $order->subtotal - (float) $order->discount_amount;
    $lines = $order->is_split_payment ? $order->payments : collect([(object) ['payment_method' => $order->payment_method, 'amount' => (float) $order->grand_total, 'reference' => null]]);
@endphp

<a href="{{ $back }}" class="mb-3 inline-flex items-center text-sm font-semibold text-[#16A34A] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16A34A]">← Kembali ke daftar order</a>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
    <section class="{{ $card }} xl:col-span-2" aria-labelledby="od-h">
        <div class="flex flex-wrap items-start justify-between gap-2 border-b border-[#E5E7EB] px-4 py-3">
            <div>
                <h2 id="od-h" class="text-base font-bold text-[#0B1B3F]">{{ $order->order_number }}</h2>
                <p class="text-xs text-[#64748B]">{{ $order->created_at->setTimezone($tz)->locale('id')->isoFormat('dddd, D MMM Y HH:mm') }} · {{ $order->outlet?->name }} · kasir {{ $order->user?->name }}</p>
            </div>
            <span class="rounded px-2 py-0.5 text-xs font-semibold {{ ['paid' => 'bg-emerald-50 text-emerald-700', 'refunded' => 'bg-amber-50 text-amber-800', 'cancelled' => 'bg-slate-100 text-[#475569]'][$order->status] ?? 'bg-slate-100 text-[#475569]' }}">{{ ['paid' => 'Lunas', 'refunded' => 'Refund', 'cancelled' => 'Dibatalkan', 'pending' => 'Menunggu bayar'][$order->status] ?? $order->status }}</span>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[520px] text-sm">
            <caption class="sr-only">Item pada order</caption>
            <thead class="text-xs"><tr><th scope="col" class="px-4 py-2.5 text-left">Item</th><th scope="col" class="px-4 py-2.5 text-right">Qty</th><th scope="col" class="px-4 py-2.5 text-right">Harga</th><th scope="col" class="px-4 py-2.5 text-right">Subtotal</th></tr></thead>
            <tbody class="divide-y divide-[#E5E7EB]">
            @foreach($order->items as $i)
                <tr>
                    <th scope="row" class="px-4 py-2.5 text-left font-medium">{{ $i->product_name }}@if($i->variant_name) <span class="font-normal text-[#64748B]">· {{ $i->variant_name }}</span>@endif
                        @foreach($i->addons as $a)<span class="block text-xs font-normal text-[#64748B]">+ {{ $a->addon_name }} ({{ \App\Support\Money::rp((float) $a->price) }})</span>@endforeach
                        @if($i->notes)<span class="block text-xs font-normal text-[#64748B]">Catatan: {{ $i->notes }}</span>@endif</th>
                    <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::num((float) $i->qty, 1) }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::rp((float) $i->price) }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::rp((float) $i->subtotal) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @if($order->status === 'refunded')
        <p class="border-t border-[#E5E7EB] px-4 py-3 text-sm text-amber-900">Order ini di-refund penuh{{ $order->refunded_at ? ' pada '.$order->refunded_at->setTimezone($tz)->format('d M Y H:i') : '' }}{{ $order->refund_reason ? ': '.$order->refund_reason : '' }}. Dikecualikan dari penjualan.</p>
        @elseif($order->status === 'cancelled')
        <p class="border-t border-[#E5E7EB] px-4 py-3 text-sm text-[#334155]">Order dibatalkan{{ $order->cancel_reason ? ': '.$order->cancel_reason : '' }}. Dikecualikan dari penjualan.</p>
        @endif
    </section>

    <div class="space-y-4">
        <section class="{{ $card }} p-5" aria-labelledby="oc-h">
            <h2 id="oc-h" class="text-base font-semibold text-[#0B1B3F]">Rincian angka</h2>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-[#64748B]">Penjualan kotor item</dt><dd class="tabular-nums">{{ \App\Support\Money::rp((float) $order->subtotal) }}</dd></div>
                <div class="flex justify-between"><dt class="text-[#64748B]">Diskon{{ $order->promotion ? ' · '.$order->promotion->name : '' }}</dt><dd class="tabular-nums">−{{ \App\Support\Money::rp((float) $order->discount_amount) }}</dd></div>
                <div class="flex justify-between border-t border-[#E5E7EB] pt-1.5 font-semibold"><dt>Penjualan bersih</dt><dd class="tabular-nums">{{ \App\Support\Money::rp($net) }}</dd></div>
                <div class="flex justify-between"><dt class="text-[#64748B]">Pajak</dt><dd class="tabular-nums">{{ \App\Support\Money::rp((float) $order->tax_amount) }}</dd></div>
                <div class="flex justify-between"><dt class="text-[#64748B]">Service</dt><dd class="tabular-nums">{{ \App\Support\Money::rp((float) $order->service_amount) }}</dd></div>
                @if((float) $order->delivery_fee > 0)<div class="flex justify-between"><dt class="text-[#64748B]">Ongkir</dt><dd class="tabular-nums">{{ \App\Support\Money::rp((float) $order->delivery_fee) }}</dd></div>@endif
                <div class="flex justify-between border-t border-[#E5E7EB] pt-1.5 font-semibold"><dt>Total tagihan</dt><dd class="tabular-nums">{{ \App\Support\Money::rp((float) $order->grand_total) }}</dd></div>
            </dl>
        </section>

        <section class="{{ $card }} p-5" aria-labelledby="op-h">
            <h2 id="op-h" class="text-base font-semibold text-[#0B1B3F]">Pembayaran tercatat</h2>
            <ul class="mt-3 space-y-1.5 text-sm">
                @foreach($lines as $l)
                <li class="flex justify-between"><span>{{ \App\Support\Money::method($l->payment_method) }}@if(! empty($l->reference)) <span class="text-xs text-[#64748B]">· {{ $l->reference }}</span>@endif</span><span class="tabular-nums">{{ \App\Support\Money::rp((float) $l->amount) }}</span></li>
                @endforeach
            </ul>
            @if((float) $order->change_amount > 0)<p class="mt-2 text-xs text-[#64748B]">Kembalian {{ \App\Support\Money::rp((float) $order->change_amount) }}.</p>@endif
            <p class="mt-2 text-xs text-[#64748B]">Catatan POS, bukan settlement gateway.</p>
        </section>

        <section class="{{ $card }} p-5" aria-labelledby="om-h">
            <h2 id="om-h" class="text-base font-semibold text-[#0B1B3F]">Informasi</h2>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-[#64748B]">Tipe</dt><dd>{{ ['dine_in' => 'Dine-in', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery'][$order->order_type] ?? $order->order_type }}</dd></div>
                @if($order->customer)<div class="flex justify-between"><dt class="text-[#64748B]">Pelanggan</dt><dd>{{ $order->customer->name }}</dd></div>@endif
                @if($order->table_number)<div class="flex justify-between"><dt class="text-[#64748B]">Meja</dt><dd>{{ $order->table_number }}</dd></div>@endif
                @if($order->shift)<div class="flex justify-between"><dt class="text-[#64748B]">Shift dibuka</dt><dd>{{ $order->shift->opened_at->setTimezone($tz)->format('d M H:i') }}</dd></div>@endif
            </dl>
        </section>
    </div>
</div>
@endsection
