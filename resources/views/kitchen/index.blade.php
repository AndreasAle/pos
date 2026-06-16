@extends('layouts.app')
@section('title','Kitchen Display')
@section('page-title','Kitchen Display System (KDS)')

@section('content')
<div x-data="kds()" x-init="init()">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="text-sm text-gray-500">Auto-refresh setiap 15 detik</span>
        </div>
        <button @click="refresh()" class="text-sm font-medium text-emerald-600 hover:text-emerald-700 border border-emerald-200 px-4 py-2 rounded-xl hover:bg-emerald-50">
            🔄 Refresh
        </button>
    </div>

    <div id="kitchen-orders" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse($orders as $order)
        @php
        $elapsed = $order->created_at->diffInMinutes(now());
        $urgencyClass = $elapsed > 20 ? 'border-red-400 bg-red-50' : ($elapsed > 10 ? 'border-yellow-400 bg-yellow-50' : 'border-gray-200 bg-white');
        $statusColors = ['pending' => 'yellow', 'preparing' => 'blue', 'ready' => 'green', 'completed' => 'gray'];
        @endphp
        <div class="rounded-2xl border-2 {{ $urgencyClass }} shadow-sm overflow-hidden" id="order-{{ $order->id }}">
            <div class="px-4 py-3 border-b {{ $elapsed > 20 ? 'border-red-300 bg-red-100' : ($elapsed > 10 ? 'border-yellow-300 bg-yellow-100' : 'border-gray-100 bg-gray-50') }}">
                <div class="flex items-center justify-between">
                    <p class="font-bold text-gray-900 font-mono">#{{ substr($order->order_number, -6) }}</p>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500">{{ $elapsed }}m ago</span>
                        @php $sc = $statusColors[$order->kitchen_status] ?? 'gray'; @endphp
                        <x-badge :color="$sc">{{ ucfirst($order->kitchen_status) }}</x-badge>
                    </div>
                </div>
                @if($order->outlet)
                <p class="text-xs text-gray-500 mt-0.5">{{ $order->outlet->name }}</p>
                @endif
                @if($order->notes)
                <p class="text-xs text-orange-600 mt-0.5 font-medium">📝 {{ $order->notes }}</p>
                @endif
            </div>

            <div class="p-4 space-y-2">
                @foreach($order->items as $item)
                <div class="flex items-start gap-2">
                    <span class="w-7 h-7 rounded-lg bg-gray-200 text-gray-700 text-xs font-bold flex items-center justify-center flex-shrink-0">
                        {{ number_format($item->qty,0) }}
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $item->product_name }}</p>
                        @if($item->variant_name)<p class="text-xs text-gray-500">↳ {{ $item->variant_name }}</p>@endif
                        @foreach($item->addons as $addon)
                        <p class="text-xs text-emerald-600">+ {{ $addon->addon_name }}</p>
                        @endforeach
                        @if($item->notes)<p class="text-xs text-orange-500 italic font-medium">📝 {{ $item->notes }}</p>@endif
                    </div>
                </div>
                @endforeach
            </div>

            <div class="px-4 pb-4 grid grid-cols-{{ $order->kitchen_status === 'pending' ? '3' : '2' }} gap-2">
                @if($order->kitchen_status === 'pending')
                <button onclick="updateKitchenStatus({{ $order->id }}, 'preparing')"
                        class="text-xs font-bold text-blue-700 bg-blue-50 border border-blue-200 hover:bg-blue-100 py-2 rounded-xl transition-colors">
                    👨‍🍳 Masak
                </button>
                @endif
                @if(in_array($order->kitchen_status, ['pending','preparing']))
                <button onclick="updateKitchenStatus({{ $order->id }}, 'ready')"
                        class="text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 py-2 rounded-xl transition-colors">
                    ✅ Siap
                </button>
                @endif
                <button onclick="updateKitchenStatus({{ $order->id }}, 'completed')"
                        class="text-xs font-bold text-gray-600 bg-gray-100 border border-gray-200 hover:bg-gray-200 py-2 rounded-xl transition-colors">
                    🏁 Selesai
                </button>
            </div>
        </div>
        @empty
        <div class="col-span-4 flex flex-col items-center justify-center py-24 text-gray-300">
            <svg class="w-20 h-20 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            <p class="text-xl font-medium">Tidak ada order masuk</p>
            <p class="text-sm mt-1">Semua pesanan sudah selesai 🎉</p>
        </div>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
function kds() {
    return {
        timer: null,
        init() {
            this.timer = setInterval(() => this.refresh(), 15000);
        },
        async refresh() {
            window.location.reload();
        },
    };
}

async function updateKitchenStatus(orderId, status) {
    try {
        const r = await fetch('/kitchen/orders/' + orderId + '/status', {
            method: 'PATCH',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
            body: JSON.stringify({ status }),
        });
        const d = await r.json();
        if (d.success) {
            if (status === 'completed') {
                document.getElementById('order-' + orderId)?.remove();
            } else {
                window.location.reload();
            }
        }
    } catch(e) { alert('Gagal update status.'); }
}
</script>
@endpush
@endsection
