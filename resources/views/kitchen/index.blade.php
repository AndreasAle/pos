@extends('layouts.app')
@section('title','Kitchen Display')
@section('page-title','Kitchen Display System (KDS)')

@section('content')
@php
    $counts = [
        'pending'   => $orders->where('kitchen_status', 'pending')->count(),
        'preparing' => $orders->where('kitchen_status', 'preparing')->count(),
        'ready'     => $orders->where('kitchen_status', 'ready')->count(),
    ];
    $statusTone  = ['pending' => 'amber', 'preparing' => 'blue', 'ready' => 'green', 'completed' => 'gray'];
    $statusLabel = ['pending' => 'Menunggu', 'preparing' => 'Dimasak', 'ready' => 'Siap', 'completed' => 'Selesai'];
@endphp

<div class="page text-[#0B1B3F] pt-2" x-data="kds()" x-init="init()">

    {{-- Header --}}
    <div class="fade-up flex flex-wrap items-end justify-between gap-3 mb-4" style="--i:0">
        <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm text-[#64748B]">Pesanan masuk dari kasir, urut dari yang paling lama.</p>
            <span class="inline-flex items-center gap-2 h-8 px-3 rounded-full bg-[#ECFDF3] text-[#15803D] text-sm font-semibold">
                <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#16A34A] opacity-60"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-[#16A34A]"></span></span>
                Auto-refresh 15 detik
            </span>
        </div>
        <x-btn variant="secondary" @click="refresh()">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Refresh
        </x-btn>
    </div>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-4">
        <x-kpi class="fade-up" style="--i:1" tone="warn" label="Menunggu" :value="$counts['pending']" />
        <x-kpi class="fade-up" style="--i:2" label="Sedang dimasak" :value="$counts['preparing']" />
        <x-kpi class="fade-up" style="--i:3" tone="brand" label="Siap diantar" :value="$counts['ready']" />
    </div>

    <div id="kitchen-orders" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-4">
        @forelse($orders as $order)
        @php
            $elapsed = (int) floor(abs($order->created_at->diffInMinutes(now())));
            $late    = $elapsed > 20;
            $warn    = $elapsed > 10 && ! $late;
            $status  = $order->kitchen_status;
        @endphp
        <article id="order-{{ $order->id }}" class="fade-up flex flex-col overflow-hidden rounded-xl border-2 bg-white
                    {{ $late ? 'border-red-300' : ($warn ? 'border-amber-300' : 'border-[#E5E7EB]') }}" style="--i:{{ min($loop->index, 8) + 3 }}">

            <div class="border-b px-4 py-3 {{ $late ? 'border-red-200 bg-red-50' : ($warn ? 'border-amber-200 bg-amber-50' : 'border-[#E5E7EB] bg-slate-50') }}">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-mono text-base font-bold">#{{ substr($order->order_number, -6) }}</p>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 text-xs font-bold tabular-nums {{ $late ? 'text-red-700' : ($warn ? 'text-amber-700' : 'text-[#64748B]') }}">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $elapsed }} mnt
                        </span>
                        <x-pill :tone="$statusTone[$status] ?? 'gray'">{{ $statusLabel[$status] ?? ucfirst($status) }}</x-pill>
                    </div>
                </div>
                @if($order->outlet)<p class="mt-1 text-xs text-[#64748B]">{{ $order->outlet->name }}</p>@endif
                @if($order->notes)
                <p class="mt-2 flex items-start gap-1.5 text-xs font-semibold text-amber-800">
                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15H9v-2.8l8.6-8.6z"/></svg>
                    {{ $order->notes }}
                </p>
                @endif
            </div>

            <ul class="flex-1 space-y-3 p-4">
                @foreach($order->items as $item)
                <li class="flex items-start gap-3">
                    <span class="flex h-8 min-w-[2rem] shrink-0 items-center justify-center rounded-lg bg-[#0B1B3F] px-1.5 text-sm font-bold text-white">{{ number_format($item->qty, 0) }}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold leading-tight">{{ $item->product_name }}</p>
                        @if($item->variant_name)<p class="mt-0.5 text-xs text-[#64748B]">{{ $item->variant_name }}</p>@endif
                        @foreach($item->addons as $addon)<p class="mt-0.5 text-xs font-medium text-[#15803D]">+ {{ $addon->addon_name }}</p>@endforeach
                        @if($item->notes)<p class="mt-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs font-semibold text-amber-800">Catatan: {{ $item->notes }}</p>@endif
                    </div>
                </li>
                @endforeach
            </ul>

            <div class="flex gap-2 px-4 pb-4">
                @if($status === 'pending')
                <button type="button" onclick="updateKitchenStatus({{ $order->id }}, 'preparing')"
                        class="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 text-[13px] font-semibold text-sky-700 transition-colors hover:bg-sky-100">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                    Masak
                </button>
                @endif
                @if(in_array($status, ['pending','preparing']))
                <button type="button" onclick="updateKitchenStatus({{ $order->id }}, 'ready')"
                        class="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-lg bg-[#16A34A] text-[13px] font-semibold text-white shadow-sm transition-colors hover:bg-[#15803D]">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Siap
                </button>
                @endif
                <button type="button" onclick="updateKitchenStatus({{ $order->id }}, 'completed')"
                        class="flex h-10 flex-1 items-center justify-center gap-1.5 rounded-lg border border-[#E5E7EB] bg-white text-[13px] font-semibold text-[#64748B] transition-colors hover:bg-slate-50 hover:text-[#0B1B3F]">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                    Selesai
                </button>
            </div>
        </article>
        @empty
        <section class="fade-up col-span-full rounded-xl border border-[#E5E7EB] bg-white" style="--i:3">
            <x-empty title="Tidak ada order masuk" text="Semua pesanan sudah selesai. Pesanan baru dari kasir akan muncul di sini otomatis."
                     icon="M5 13l4 4L19 7" class="py-20" />
        </section>
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
