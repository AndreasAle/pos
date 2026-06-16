@extends('layouts.app')
@section('title','Order #'.$order->order_number)
@section('page-title','Detail Order')

@section('content')
<div class="flex items-center justify-between mb-5">
    <a href="{{ route('orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Riwayat Order</a>
    <div class="flex items-center gap-2">
        <a href="{{ route('receipt.show', $order) }}"
           class="text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 px-4 py-2 rounded-xl">
            🖨 Struk
        </a>
        @if($order->status === 'paid')
        <button onclick="document.getElementById('refund-modal').classList.remove('hidden')"
                class="text-sm font-medium text-blue-600 border border-blue-200 hover:bg-blue-50 px-4 py-2 rounded-xl">
            💰 Refund
        </button>
        <button onclick="document.getElementById('void-modal').classList.remove('hidden')"
                class="text-sm font-medium text-red-600 border border-red-200 hover:bg-red-50 px-4 py-2 rounded-xl">
            Void / Batal
        </button>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-4">
        {{-- Info --}}
        <x-card title="Informasi Order">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                <div><p class="text-gray-500 text-xs">No. Order</p><p class="font-bold font-mono text-gray-900">{{ $order->order_number }}</p></div>
                <div><p class="text-gray-500 text-xs">Status</p>
                    @php
                        $sc = ['paid'=>'green','draft'=>'yellow','cancelled'=>'red','refunded'=>'blue'];
                        $sl = ['paid'=>'Lunas','draft'=>'Draft','cancelled'=>'Batal','refunded'=>'Refund'];
                    @endphp
                    <x-badge :color="$sc[$order->status]??'gray'">{{ $sl[$order->status]??ucfirst($order->status) }}</x-badge>
                </div>
                <div><p class="text-gray-500 text-xs">Kasir</p><p class="font-semibold text-gray-900">{{ $order->user->name }}</p></div>
                <div><p class="text-gray-500 text-xs">Outlet</p><p class="font-semibold text-gray-900">{{ $order->outlet->name }}</p></div>
                <div>
                    <p class="text-gray-500 text-xs">Tipe Order</p>
                    @php
                        $typeLabels = ['dine_in'=>'Dine In','takeaway'=>'Takeaway','delivery'=>'Delivery'];
                        $typeColors = ['dine_in'=>'bg-green-100 text-green-700','takeaway'=>'bg-blue-100 text-blue-700','delivery'=>'bg-orange-100 text-orange-700'];
                    @endphp
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $typeColors[$order->order_type ?? 'dine_in'] ?? '' }}">
                        {{ $typeLabels[$order->order_type ?? 'dine_in'] ?? '-' }}
                    </span>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Pembayaran</p>
                    @if($order->is_split_payment)
                        <span class="text-xs font-semibold text-purple-700 bg-purple-100 px-2 py-0.5 rounded-full">Split</span>
                    @else
                        <p class="font-semibold capitalize text-gray-900">{{ $order->payment_method }}</p>
                    @endif
                </div>
                <div><p class="text-gray-500 text-xs">Waktu</p><p class="font-semibold text-gray-900">{{ $order->created_at->format('d/m/Y H:i') }}</p></div>
                @if($order->customer)
                <div class="col-span-2 sm:col-span-3">
                    <p class="text-gray-500 text-xs">Pelanggan</p>
                    <p class="font-semibold text-gray-900">{{ $order->customer->name }}{{ $order->customer->phone ? ' ('.$order->customer->phone.')' : '' }}</p>
                </div>
                @endif
                @if($order->cancel_reason)
                <div class="col-span-2 sm:col-span-3 bg-red-50 rounded-xl p-3">
                    <p class="text-xs text-red-700 font-medium">Alasan Pembatalan: {{ $order->cancel_reason }}</p>
                </div>
                @endif
                @if($order->refund_reason)
                <div class="col-span-2 sm:col-span-3 bg-blue-50 rounded-xl p-3">
                    <p class="text-xs text-blue-700 font-medium">Alasan Refund: {{ $order->refund_reason }}</p>
                    <p class="text-xs text-blue-500 mt-0.5">{{ $order->refunded_at?->format('d/m/Y H:i') }}</p>
                </div>
                @endif
            </div>
        </x-card>

        {{-- Delivery Info --}}
        @if(($order->order_type ?? 'dine_in') === 'delivery')
        <x-card title="Informasi Pengiriman">
            <div class="grid grid-cols-2 gap-4 text-sm">
                @if($order->delivery_platform)
                <div>
                    <p class="text-gray-500 text-xs">Platform</p>
                    <p class="font-semibold capitalize text-gray-900">{{ str_replace('_', ' ', $order->delivery_platform) }}</p>
                </div>
                @endif
                @if($order->platform_order_number)
                <div>
                    <p class="text-gray-500 text-xs">No. Order Platform</p>
                    <p class="font-semibold font-mono text-gray-900">{{ $order->platform_order_number }}</p>
                </div>
                @endif
                @if($order->customer_address)
                <div class="col-span-2">
                    <p class="text-gray-500 text-xs">Alamat Pengiriman</p>
                    <p class="font-semibold text-gray-900">{{ $order->customer_address }}</p>
                </div>
                @endif
                @if($order->delivery_notes)
                <div class="col-span-2">
                    <p class="text-gray-500 text-xs">Catatan Pengiriman</p>
                    <p class="text-gray-700">{{ $order->delivery_notes }}</p>
                </div>
                @endif
            </div>
        </x-card>
        @endif

        {{-- Items --}}
        <x-card title="Item Pesanan" :padding="false">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 border-b border-gray-100">
                    <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500">Produk</th>
                    <th class="text-center px-3 py-2.5 text-xs font-semibold text-gray-500">Qty</th>
                    <th class="text-right px-3 py-2.5 text-xs font-semibold text-gray-500">Harga</th>
                    <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500">Subtotal</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($order->items as $item)
                    <tr>
                        <td class="px-5 py-3">
                            <p class="font-semibold text-gray-900">{{ $item->product_name }}</p>
                            @if($item->variant_name)<p class="text-xs text-gray-500">↳ {{ $item->variant_name }}</p>@endif
                            @foreach($item->addons as $a)<p class="text-xs text-emerald-600">+ {{ $a->addon_name }} (Rp {{ number_format($a->price,0,',','.') }})</p>@endforeach
                            @if($item->notes)<p class="text-xs text-orange-500 italic">📝 {{ $item->notes }}</p>@endif
                        </td>
                        <td class="px-3 py-3 text-center text-gray-700">{{ number_format($item->qty,0) }}</td>
                        <td class="px-3 py-3 text-right text-gray-700">Rp {{ number_format($item->price,0,',','.') }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-gray-900">Rp {{ number_format($item->subtotal,0,',','.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    </div>

    {{-- Right: Summary --}}
    <div class="space-y-4">
        <x-card title="Ringkasan Pembayaran">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between text-gray-600"><dt>Subtotal</dt><dd>Rp {{ number_format($order->subtotal,0,',','.') }}</dd></div>
                @if($order->discount_amount > 0)<div class="flex justify-between text-red-600"><dt>Diskon</dt><dd>- Rp {{ number_format($order->discount_amount,0,',','.') }}</dd></div>@endif
                @if($order->tax_amount > 0)<div class="flex justify-between text-gray-600"><dt>Pajak</dt><dd>Rp {{ number_format($order->tax_amount,0,',','.') }}</dd></div>@endif
                @if($order->service_amount > 0)<div class="flex justify-between text-gray-600"><dt>Service</dt><dd>Rp {{ number_format($order->service_amount,0,',','.') }}</dd></div>@endif
                @if($order->delivery_fee > 0)<div class="flex justify-between text-orange-600"><dt>Ongkos Kirim</dt><dd>Rp {{ number_format($order->delivery_fee,0,',','.') }}</dd></div>@endif
                <div class="flex justify-between font-bold text-gray-900 text-base border-t pt-2"><dt>TOTAL</dt><dd>Rp {{ number_format($order->grand_total,0,',','.') }}</dd></div>
                @if($order->is_split_payment && $order->payments->count())
                    <div class="pt-1 border-t">
                        <p class="text-xs font-semibold text-gray-500 mb-1">Detail Pembayaran Split:</p>
                        @foreach($order->payments as $pay)
                        <div class="flex justify-between text-gray-600 text-xs">
                            <dt class="capitalize">{{ $pay->payment_method }}</dt>
                            <dd>Rp {{ number_format($pay->amount,0,',','.') }}</dd>
                        </div>
                        @endforeach
                    </div>
                @else
                <div class="flex justify-between text-gray-600"><dt>Dibayar ({{ ucfirst($order->payment_method) }})</dt><dd>Rp {{ number_format($order->paid_amount,0,',','.') }}</dd></div>
                @endif
                @if($order->change_amount > 0)<div class="flex justify-between text-blue-700 font-semibold"><dt>Kembalian</dt><dd>Rp {{ number_format($order->change_amount,0,',','.') }}</dd></div>@endif
                @if($order->notes)
                <div class="pt-2 border-t"><p class="text-xs text-gray-500 font-medium">Catatan:</p><p class="text-sm text-gray-700">{{ $order->notes }}</p></div>
                @endif
            </dl>
        </x-card>
    </div>
</div>

{{-- Void Modal --}}
<div id="void-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-5">
        <h3 class="font-bold text-gray-900 mb-3">Batalkan Order</h3>
        <p class="text-sm text-gray-500 mb-4">Masukkan alasan pembatalan order <strong>{{ $order->order_number }}</strong>. Stok akan dikembalikan.</p>
        <form method="POST" action="{{ route('orders.void', $order) }}">
            @csrf
            <textarea name="reason" rows="3" required placeholder="Alasan pembatalan..."
                      class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500 mb-3"></textarea>
            <div class="flex gap-2">
                <button type="button" onclick="document.getElementById('void-modal').classList.add('hidden')"
                        class="flex-1 text-sm font-medium text-gray-700 bg-gray-100 py-2.5 rounded-xl">Batal</button>
                <button type="submit" class="flex-1 text-sm font-bold text-white bg-red-600 hover:bg-red-700 py-2.5 rounded-xl">
                    Batalkan Order
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Refund Modal --}}
<div id="refund-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-5">
        <h3 class="font-bold text-gray-900 mb-1">Refund Order</h3>
        <p class="text-sm text-gray-500 mb-4">
            Refund order <strong>{{ $order->order_number }}</strong> sebesar
            <strong class="text-blue-700">Rp {{ number_format($order->grand_total, 0, ',', '.') }}</strong>.
            Stok dan poin akan dikembalikan otomatis.
        </p>
        <form method="POST" action="{{ route('orders.refund', $order) }}">
            @csrf
            <textarea name="reason" rows="3" required placeholder="Alasan refund (wajib)..."
                      class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-3"></textarea>
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-3 text-xs text-blue-700">
                ⚠️ Proses refund <strong>tidak dapat diurungkan</strong>. Pastikan uang sudah dikembalikan ke pelanggan sebelum konfirmasi.
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="document.getElementById('refund-modal').classList.add('hidden')"
                        class="flex-1 text-sm font-medium text-gray-700 bg-gray-100 py-2.5 rounded-xl">Batal</button>
                <button type="submit" class="flex-1 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 py-2.5 rounded-xl">
                    Konfirmasi Refund
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
