{{-- $orders (paginator of Order), optional $compact --}}
@php $tz = $f->timezone; $compact = $compact ?? false; @endphp
<div class="overflow-x-auto">
    <table class="w-full min-w-[640px] text-sm">
        <caption class="sr-only">Daftar order</caption>
        <thead class="sticky top-0 bg-gray-50 text-left text-xs font-semibold text-[#64748B]">
            <tr>
                <th scope="col" class="px-4 py-2.5">No. order</th>
                <th scope="col" class="px-4 py-2.5">Waktu</th>
                @unless($compact)<th scope="col" class="px-4 py-2.5">Outlet</th><th scope="col" class="px-4 py-2.5">Kasir</th>@endunless
                <th scope="col" class="px-4 py-2.5">Bayar</th>
                <th scope="col" class="px-4 py-2.5 text-right">Penjualan bersih</th>
                @unless($compact)<th scope="col" class="px-4 py-2.5 text-right">Total tagihan</th><th scope="col" class="px-4 py-2.5">Status</th>@endunless
            </tr>
        </thead>
        <tbody class="divide-y divide-[#E5E7EB]">
        @forelse($orders as $o)
            @php
                $methods = $o->is_split_payment ? $o->payments->pluck('payment_method')->unique()->map(fn ($m) => \App\Support\Money::method($m))->implode(' + ') : \App\Support\Money::method($o->payment_method);
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2.5"><a href="{{ route('reports.sales.order', ['orderId' => $o->id, 'back' => request()->fullUrl()]) }}" class="font-semibold text-[#16A34A] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#16A34A]">{{ $o->order_number }}</a></td>
                <td class="whitespace-nowrap px-4 py-2.5 text-[#475569]">{{ $o->created_at->setTimezone($tz)->format('d M H:i') }}</td>
                @unless($compact)<td class="px-4 py-2.5">{{ $o->outlet?->name }}</td><td class="px-4 py-2.5">{{ $o->user?->name }}</td>@endunless
                <td class="px-4 py-2.5">{{ $methods }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums">{{ \App\Support\Money::rp((float) $o->subtotal - (float) $o->discount_amount) }}</td>
                @unless($compact)
                <td class="px-4 py-2.5 text-right tabular-nums text-[#64748B]">{{ \App\Support\Money::rp((float) $o->grand_total) }}</td>
                <td class="px-4 py-2.5">
                    <span class="rounded px-2 py-0.5 text-xs font-semibold {{ ['paid' => 'bg-emerald-50 text-emerald-700', 'refunded' => 'bg-amber-50 text-amber-800', 'cancelled' => 'bg-slate-100 text-[#475569]'][$o->status] ?? 'bg-slate-100 text-[#475569]' }}">{{ ['paid' => 'Lunas', 'refunded' => 'Refund', 'cancelled' => 'Dibatalkan'][$o->status] ?? $o->status }}</span>
                </td>
                @endunless
            </tr>
        @empty
            <tr><td colspan="{{ $compact ? 4 : 7 }}" class="px-4 py-8 text-center text-[#64748B]">Tidak ada transaksi untuk filter ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
