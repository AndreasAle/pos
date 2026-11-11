
@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@php
    $sub   = $currentBusiness->activeSubscription;
    $user  = auth()->user();
    $role  = $user->role;
    $hour  = (int) now()->format('G');
    $greet = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
    $first = \Illuminate\Support\Str::of($user->name)->explode(' ')->first();

    $rev   = $salesChart['revenue'];
    $ord   = $salesChart['orders'];
    $n     = count($rev);
    $revY  = $n > 1 ? $rev[$n - 2] : 0;
    $ordY  = $n > 1 ? $ord[$n - 2] : 0;
    $delta = fn($now, $prev) => $prev > 0 ? round((($now - $prev) / $prev) * 100) : null;
    $revDelta = $delta($summary['todayRevenue'], $revY);
    $ordDelta = $delta($summary['todayOrders'], $ordY);
    $week     = array_sum($rev);
    $weekOrd  = array_sum($ord);
    $margin   = $summary['todayRevenue'] > 0 ? round($summary['todayProfit'] / $summary['todayRevenue'] * 100) : null;
    $rp       = fn($v) => 'Rp ' . number_format($v, 0, ',', '.');
@endphp

@push('styles')
<style>
    .dash { --navy:#0B1B3F; --green:#16A34A; --soft:#ECFDF3; --border:#E5E7EB; --muted:#64748B; }
    .anim-float { animation:dash-float 5s ease-in-out infinite }
    .anim-sway { animation:dash-sway 4s ease-in-out infinite }
    .anim-drift { animation:dash-drift 9s ease-in-out infinite alternate }
    .anim-twinkle { animation:dash-twinkle 2.4s ease-in-out infinite; transform-box:fill-box; transform-origin:center }
    .anim-coin { animation:dash-coin 3.2s ease-in-out infinite; transform-box:fill-box; transform-origin:center }
    .anim-sign { animation:dash-sign 2.8s ease-in-out infinite }
    .anim-awning { animation:dash-awning 6s ease-in-out infinite; transform-box:fill-box; transform-origin:top }
    .anim-bar { transform-box:fill-box; transform-origin:bottom; animation:dash-bar 3.6s ease-in-out infinite }
    .anim-line { stroke-dasharray:1; stroke-dashoffset:1; animation:dash-line 3.6s ease-in-out infinite }
    .anim-pop { animation:dash-pop 3.6s ease-in-out infinite }
    @keyframes dash-float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-5px)} }
    @keyframes dash-sway { 0%,100%{transform:rotate(-3deg)} 50%{transform:rotate(4deg)} }
    @keyframes dash-drift { from{transform:translateX(-6px)} to{transform:translateX(10px)} }
    @keyframes dash-twinkle { 0%,100%{opacity:.35;transform:scale(.7)} 50%{opacity:1;transform:scale(1.1)} }
    @keyframes dash-coin { 0%,100%{transform:translateY(0) rotateY(0)} 50%{transform:translateY(-9px) rotateY(180deg)} }
    @keyframes dash-sign { 0%,100%{opacity:1} 92%{opacity:1} 95%{opacity:.35} 98%{opacity:1} }
    @keyframes dash-awning { 0%,100%{transform:scaleY(1)} 50%{transform:scaleY(1.06)} }
    @keyframes dash-bar { 0%{transform:scaleY(0)} 25%,80%{transform:scaleY(1)} 100%{transform:scaleY(0)} }
    @keyframes dash-line { 0%,15%{stroke-dashoffset:1} 45%,85%{stroke-dashoffset:0} 100%{stroke-dashoffset:-1} }
    @keyframes dash-pop { 0%,45%{transform:scale(0)} 58%{transform:scale(1.2)} 66%,85%{transform:scale(1)} 100%{transform:scale(0)} }
    .dash .pulse-dot { animation:dash-dot 2s ease-out infinite }
    @keyframes dash-dot { 0%{box-shadow:0 0 0 0 currentColor} 70%{box-shadow:0 0 0 7px transparent} 100%{box-shadow:0 0 0 0 transparent} }
    @media (prefers-reduced-motion: reduce) { .dash *, .dash svg * { animation:none !important } .anim-bar{transform:none} .anim-line{stroke-dashoffset:0} }
</style>
@endpush

<div class="dash page text-[#0B1B3F] pt-5">
<a href="{{ route('dashboard') }}" class="lg:hidden inline-flex items-center gap-1.5 mb-4 text-sm font-semibold text-[#64748B] hover:text-[#0B1B3F] transition-colors">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Beranda
</a>

{{-- Banner trial --}}
@if(config('pos.features.subscription') && $sub && $sub->status === 'trial' && $sub->ends_at)
    <div x-data="{
            endsAt: new Date('{{ $sub->ends_at->copy()->endOfDay()->toIso8601String() }}').getTime(),
            remaining: '',
            tick() {
                const diff = this.endsAt - Date.now();
                if (diff <= 0) { this.remaining = 'Masa trial telah berakhir'; return; }
                const d = Math.floor(diff / 86400000);
                const h = Math.floor((diff % 86400000) / 3600000);
                const m = Math.floor((diff % 3600000) / 60000);
                const s = Math.floor((diff % 60000) / 1000);
                this.remaining = d + 'h ' + h + 'j ' + m + 'm ' + s + 'd';
            }
         }"
         x-init="tick(); setInterval(() => tick(), 1000)"
         class="fade-up mb-6 rounded-xl border border-[#BBF7D0] bg-[#ECFDF3] px-5 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-lg bg-[#16A34A] text-white flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <div>
                <p class="font-semibold text-sm">Trial gratis aktif, semua fitur terbuka penuh</p>
                <p class="text-xs text-[#166534]">Sisa waktu: <span class="font-mono font-semibold" x-text="remaining"></span> &middot; berakhir {{ $sub->ends_at->format('d M Y') }}</p>
            </div>
        </div>
        @if($user->isOwner())
        <a href="{{ route('saas.current') }}" class="shrink-0 h-10 inline-flex items-center px-4 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors">Upgrade Sekarang</a>
        @endif
    </div>
@endif

@php
    $hasSales    = (array_sum($rev) + array_sum($ord)) > 0;
    $isManager   = in_array($role, ['owner','admin']);
    $lowCount    = count($lowStock);
    $iconCash    = 'M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8V6m0 12v-2m9-6a9 9 0 11-18 0 9 9 0 0118 0z';
@endphp

<div class="grid grid-cols-1 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-6">

    {{-- ============ KOLOM KIRI ============ --}}
    <div class="space-y-4 min-w-0">

        {{-- Greeting --}}
        <section class="fade-up relative overflow-hidden rounded-xl border border-[#E5E7EB] bg-gradient-to-br from-white via-white to-[#ECFDF3] p-6" style="--i:0">
            <div class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_260px] gap-4 items-center">
                <div>
                    <p class="text-xs font-semibold text-[#16A34A] uppercase tracking-wide">{{ now()->isoFormat('dddd, D MMMM Y') }}</p>
                    <h2 class="mt-1 text-[28px] leading-9 font-semibold">{{ $greet }}, <span class="inline-block px-2.5 -mx-0.5 rounded-lg bg-[#16A34A] text-white">{{ $first }}</span></h2>
                    <p class="mt-1 text-sm text-[#64748B] max-w-sm">Berikut ringkasan yang terjadi di <span class="font-medium text-[#0B1B3F]">{{ $currentBusiness->name }}</span> hari ini.</p>

                    <div class="mt-5 flex flex-wrap gap-x-10 gap-y-4">
                        <div x-data="{ v: 0, t: {{ (int) $summary['todayRevenue'] }}, init() { const s = performance.now(); const f = n => { const p = Math.min((n - s) / 1100, 1); this.v = Math.round(this.t * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(f) }; requestAnimationFrame(f) } }">
                            <p class="text-sm font-semibold">Total Penjualan Hari Ini</p>
                            <p class="mt-0.5 text-[32px] leading-10 font-semibold tracking-tight">Rp <span x-text="v.toLocaleString('id-ID')">{{ number_format($summary['todayRevenue'], 0, ',', '.') }}</span></p>
                        </div>
                        <div x-data="{ v: 0, t: {{ (int) $summary['todayOrders'] }}, init() { const s = performance.now(); const f = n => { const p = Math.min((n - s) / 900, 1); this.v = Math.round(this.t * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(f) }; requestAnimationFrame(f) } }">
                            <p class="text-sm font-semibold">Total Transaksi</p>
                            <p class="mt-0.5 text-[32px] leading-10 font-semibold tracking-tight" x-text="v.toLocaleString('id-ID')">{{ number_format($summary['todayOrders']) }}</p>
                        </div>
                    </div>
                </div>
                <div class="hidden sm:block h-[210px]">@include('dashboard._store')</div>
            </div>
        </section>

        {{-- Pengingat singkat: chip yang bisa diklik --}}
        @php
            $hints = [];
            if (in_array($role, ['owner','admin','cashier'])) {
                $hints[] = $summary['todayOrders'] > 0
                    ? ['dot' => 'bg-[#16A34A]', 'text' => number_format($summary['todayOrders']) . ' transaksi hari ini', 'cta' => 'Lihat', 'href' => route('orders.index'), 'pulse' => false]
                    : ['dot' => 'bg-sky-500', 'text' => 'Belum ada transaksi hari ini', 'cta' => 'Buka Kasir', 'href' => route('pos.index'), 'pulse' => true];
            }
            if ($isManager && $lowCount > 0 && Route::has('ingredients.index')) {
                $hints[] = ['dot' => 'bg-amber-500', 'text' => $lowCount . ' bahan baku menipis', 'cta' => 'Cek stok', 'href' => route('ingredients.index'), 'pulse' => true];
            }
            if ($isManager && count($topProducts) === 0) {
                $hints[] = ['dot' => 'bg-slate-400', 'text' => 'Belum ada produk terjual', 'cta' => 'Tambah produk', 'href' => route('products.create'), 'pulse' => false];
            }
        @endphp
        @if(count($hints))
        <div class="fade-up grid grid-cols-1 sm:grid-cols-2 gap-3" style="--i:1">
            @foreach($hints as $h)
            <div class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] bg-white pl-4 pr-3 py-3 hover:border-[#16A34A]/50 hover:shadow-sm transition">
                <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $h['dot'] }} {{ $h['pulse'] ? 'pulse-dot' : '' }}"></span>
                <p class="flex-1 min-w-0 text-sm font-medium text-[#0B1B3F]">{{ $h['text'] }}</p>
                <a href="{{ $h['href'] }}" class="group shrink-0 h-10 inline-flex items-center gap-1 px-3.5 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors shadow-sm">
                    {{ $h['cta'] }}
                    <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Banner laporan --}}
        @if($isManager)
        <section class="fade-up relative overflow-hidden rounded-xl border border-[#BBF7D0] bg-gradient-to-r from-[#ECFDF3] via-[#F3FDF7] to-white px-6 py-4" style="--i:2">
            <div class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_150px] gap-4 items-center">
                <div>
                    <h3 class="text-lg font-semibold">Cek laporan penjualan Anda</h3>
                    <p class="mt-0.5 text-sm text-[#64748B] max-w-md">Performa toko, produk terlaris, dan laba usaha.</p>
                    <a href="{{ route('reports.sales') }}" class="group mt-3 h-10 inline-flex items-center gap-2 px-4 rounded-lg bg-[#16A34A] text-white text-sm font-semibold hover:bg-[#15803D] transition-colors shadow-sm">
                        Lihat Laporan
                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                </div>
                <div class="hidden sm:block h-[96px]">@include('dashboard._report')</div>
            </div>
        </section>
        @endif

        {{-- Transaksi terbaru --}}
        <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:3">
            <div class="px-5 sm:px-6 py-5 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-lg font-semibold">Transaksi Terbaru</h3>
                    <p class="text-sm text-[#64748B] mt-0.5">Aktivitas penjualan terakhir</p>
                </div>
                <a href="{{ route('orders.index') }}" class="text-sm font-semibold text-[#16A34A] hover:text-[#15803D]">Lihat Semua &rarr;</a>
            </div>
            @if(count($recentOrders))
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead>
                        <tr class="bg-slate-50 border-y border-[#E5E7EB]">
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">No. Order</th>
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Kasir</th>
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Status</th>
                            <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Total</th>
                            <th class="text-right px-6 py-3.5 text-xs font-semibold text-[#64748B] uppercase tracking-wider">Waktu</th>
                            <th class="px-6 py-3.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E7EB]">
                        @foreach($recentOrders as $order)
                        <tr class="group hover:bg-[#ECFDF3]/50 transition-colors cursor-pointer" onclick="window.location='{{ route('orders.show', $order['id']) }}'">
                            <td class="px-6 py-4"><a href="{{ route('orders.show', $order['id']) }}" class="font-mono text-[13px] font-semibold text-[#16A34A] hover:underline">{{ $order['order_number'] }}</a></td>
                            <td class="px-6 py-4">
                                <span class="flex items-center gap-2.5 text-slate-700">
                                    <span class="w-7 h-7 rounded-full bg-[#ECFDF3] text-[#16A34A] text-xs font-bold flex items-center justify-center">{{ strtoupper(substr($order['user']['name'] ?? '-', 0, 1)) }}</span>
                                    {{ $order['user']['name'] ?? '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4"><x-order-status :status="$order['status']"/></td>
                            <td class="px-6 py-4 text-right font-bold">{{ $rp($order['grand_total']) }}</td>
                            <td class="px-6 py-4 text-right text-[#64748B]">{{ \Carbon\Carbon::parse($order['created_at'])->format('d M, H:i') }}</td>
                            <td class="px-6 py-4 text-right">
                                <span class="inline-flex items-center gap-1 text-[13px] font-semibold text-[#16A34A] opacity-70 group-hover:opacity-100">Detail
                                    <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="md:hidden divide-y divide-[#E5E7EB] border-t border-[#E5E7EB]">
                @foreach($recentOrders as $order)
                <a href="{{ route('orders.show', $order['id']) }}" class="block px-4 py-3.5 active:bg-[#ECFDF3]/60">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-[13px] font-semibold text-[#16A34A] truncate">{{ $order['order_number'] }}</span>
                        <x-order-status :status="$order['status']"/>
                    </div>
                    <div class="mt-1.5 flex items-end justify-between gap-2">
                        <div class="text-sm text-[#64748B] min-w-0">
                            <p class="truncate">{{ $order['user']['name'] ?? '-' }}</p>
                            <p class="text-xs">{{ \Carbon\Carbon::parse($order['created_at'])->format('d M, H:i') }}</p>
                        </div>
                        <p class="text-base font-bold shrink-0">{{ $rp($order['grand_total']) }}</p>
                    </div>
                </a>
                @endforeach
            </div>
            @else
            <div class="border-t border-[#E5E7EB] px-6 py-12 text-center text-sm text-[#64748B]">Belum ada transaksi</div>
            @endif
        </section>

        @if(count($outletPerformance) > 1)
        <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white p-5 sm:p-6" style="--i:5">
            <h3 class="text-lg font-semibold mb-4">Performa Outlet Hari Ini</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($outletPerformance as $perf)
                <div class="rounded-lg border border-[#E5E7EB] bg-slate-50/70 p-4">
                    <p class="text-sm font-medium text-[#64748B]">{{ $perf['outlet_name'] }}</p>
                    <p class="text-xl font-semibold mt-1">{{ $rp($perf['revenue']) }}</p>
                    <p class="text-xs text-[#64748B]">{{ $perf['orders'] }} transaksi</p>
                </div>
                @endforeach
            </div>
        </section>
        @endif
    </div>

    {{-- ============ KOLOM KANAN ============ --}}
    <aside class="space-y-4 min-w-0">

        {{-- Filter --}}
        <div class="fade-up flex flex-wrap items-center gap-2" style="--i:1">
            <span class="h-10 inline-flex items-center gap-2 px-3.5 rounded-lg border border-[#E5E7EB] bg-white text-sm font-medium">
                <svg class="w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>
                Hari ini
            </span>
            @if(count($outlets) > 1)
            <div class="relative">
                <select onchange="window.location = this.value" class="appearance-none h-10 pl-3.5 pr-9 rounded-lg border border-[#E5E7EB] bg-white text-sm font-medium focus:outline-none focus:ring-2 focus:ring-[#16A34A]/40 focus:border-[#16A34A] cursor-pointer hover:border-[#16A34A] transition-colors">
                    <option value="{{ route('dashboard') }}" {{ !$outletId ? 'selected' : '' }}>Semua Outlet</option>
                    @foreach($outlets as $outlet)
                    <option value="{{ route('dashboard', ['outlet_id' => $outlet->id]) }}" {{ $outletId == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </div>
            @endif
        </div>

        {{-- Kartu bertab: Penjualan / Transaksi / Pembayaran --}}
        <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white overflow-hidden" style="--i:2"
                 x-data="{ tab: 'sales', set(t) { this.tab = t; this.$nextTick(() => window.dispatchEvent(new Event('resize'))) } }">
            <div class="p-1.5 bg-slate-50 border-b border-[#E5E7EB]">
                <div class="grid grid-cols-3 gap-1 relative" role="tablist">
                    @foreach(['sales' => 'Penjualan', 'orders' => 'Transaksi', 'pay' => 'Pembayaran'] as $k => $lbl)
                    <button type="button" role="tab" @click="set('{{ $k }}')" :aria-selected="tab === '{{ $k }}'"
                            class="h-9 rounded-lg text-[13px] font-semibold transition-all duration-200"
                            :class="tab === '{{ $k }}' ? 'bg-white text-[#16A34A] shadow-sm ring-1 ring-[#E5E7EB]' : 'text-[#64748B] hover:text-[#0B1B3F] hover:bg-white/60'">{{ $lbl }}</button>
                    @endforeach
                </div>
            </div>

            <div class="p-5">
                {{-- Penjualan --}}
                <div x-show="tab === 'sales'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold">Total Penjualan</p>
                @if($revDelta !== null)
                <span class="inline-flex items-center gap-0.5 text-xs font-semibold {{ $revDelta >= 0 ? 'text-[#16A34A]' : 'text-red-600' }}">
                    <svg class="w-3 h-3 {{ $revDelta >= 0 ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>{{ abs($revDelta) }}%
                    <span class="text-[#64748B] font-normal ml-1">vs hari sebelumnya</span>
                </span>
                @else
                <span class="text-xs text-[#64748B]">0% vs hari sebelumnya</span>
                @endif
            </div>
            <p class="mt-1 text-[32px] leading-10 font-semibold tracking-tight">{{ $rp($summary['todayRevenue']) }}</p>
                    @if($hasSales)
                    <div class="mt-3" style="position:relative;height:150px"><canvas id="miniRevenue"></canvas></div>
                    @else
                    <div class="mt-4 flex items-center gap-3 rounded-lg bg-slate-50 px-4 py-3 text-sm text-[#64748B]">
                        <svg class="w-9 h-9 shrink-0 text-[#16A34A]/70 anim-float" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8M14 7h7v7"/></svg>
                        <span>Belum ada data 7 hari terakhir. Grafik muncul setelah transaksi pertama.</span>
                    </div>
                    @endif
                </div>

                {{-- Transaksi --}}
                <div x-show="tab === 'orders'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-semibold">Total Transaksi</p>
                @if($ordDelta !== null)
                <span class="inline-flex items-center gap-0.5 text-xs font-semibold {{ $ordDelta >= 0 ? 'text-[#16A34A]' : 'text-red-600' }}">
                    <svg class="w-3 h-3 {{ $ordDelta >= 0 ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>{{ abs($ordDelta) }}%
                    <span class="text-[#64748B] font-normal ml-1">vs hari sebelumnya</span>
                </span>
                @else
                <span class="text-xs text-[#64748B]">0% vs hari sebelumnya</span>
                @endif
            </div>
            <p class="mt-1 text-[32px] leading-10 font-semibold tracking-tight">{{ number_format($summary['todayOrders']) }}</p>
                    @if($hasSales)
                    <div class="mt-3" style="position:relative;height:150px"><canvas id="miniOrders"></canvas></div>
                    @else
                    <div class="mt-4 flex items-center gap-3 rounded-lg bg-slate-50 px-4 py-3 text-sm text-[#64748B]">
                        <svg class="w-9 h-9 shrink-0 text-[#16A34A]/70 anim-float" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8M14 7h7v7"/></svg>
                        <span>Belum ada data 7 hari terakhir. Grafik muncul setelah transaksi pertama.</span>
                    </div>
                    @endif
                </div>

                {{-- Pembayaran --}}
                <div x-show="tab === 'pay'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <p class="text-sm font-semibold">Metode Pembayaran</p>
                    <p class="text-xs text-[#64748B] mt-0.5 mb-4">Hari ini</p>
            @if(count($paymentBreakdown) > 0)
            @php $payTotal = collect($paymentBreakdown)->sum('total'); $dots = ['bg-[#16A34A]','bg-blue-500','bg-[#A855F7]','bg-amber-500','bg-red-500','bg-slate-500']; @endphp
            <div class="relative mx-auto" style="height:160px;max-width:220px">
                <canvas id="paymentChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-xs text-[#64748B] uppercase tracking-wider">Total</span>
                    <span class="text-base font-semibold">{{ $rp($payTotal) }}</span>
                </div>
            </div>
            <div class="mt-4 space-y-2.5">
                @foreach($paymentBreakdown as $i => $p)
                <div class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2 text-slate-600 capitalize"><span class="w-2.5 h-2.5 rounded-full {{ $dots[$i % 6] }}"></span>{{ $p['payment_method'] }}</span>
                    <span class="text-right"><span class="font-semibold">{{ $rp($p['total']) }}</span><span class="text-xs text-[#64748B] ml-1">{{ $p['count'] }}x</span></span>
                </div>
                @endforeach
            </div>
            @else
            <div class="flex flex-col items-center justify-center py-8 text-[#64748B]">
                <div class="w-11 h-11 rounded-xl bg-slate-100 flex items-center justify-center mb-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <p class="text-sm">Belum ada transaksi hari ini</p>
            </div>
            @endif
                </div>
            </div>
        </section>

        {{-- Produk terlaris --}}
        <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white p-5" style="--i:5">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">Produk Terlaris</h3>
                @if($isManager)<a href="{{ route('reports.products') }}" class="text-xs font-semibold text-[#16A34A] hover:text-[#15803D]">Lihat Semua &rarr;</a>@endif
            </div>
            @php $maxRev = collect($topProducts)->max('total_revenue') ?: 1; @endphp
            @forelse($topProducts as $i => $p)
            <div class="py-3 {{ !$loop->last ? 'border-b border-[#E5E7EB]' : '' }}">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold shrink-0 {{ $i === 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500' }}">{{ $i + 1 }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate">{{ $p['product_name'] }}</p>
                        <p class="text-xs text-[#64748B]">{{ number_format($p['total_qty'], 0, ',', '.') }} terjual</p>
                    </div>
                    <span class="text-sm font-bold">{{ $rp($p['total_revenue']) }}</span>
                </div>
                <div class="mt-2 ml-10 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r from-[#4ADE80] to-[#16A34A]" style="width: {{ max(4, round($p['total_revenue'] / $maxRev * 100)) }}%"></div>
                </div>
            </div>
            @empty
            <div class="flex flex-col items-center text-center py-8">
                <svg class="w-20 h-20 anim-float" viewBox="0 0 80 80" fill="none" aria-hidden="true">
                    <ellipse cx="40" cy="70" rx="24" ry="4" fill="#16A34A" opacity=".12"/>
                    <path d="M40 14l22 11v26L40 62 18 51V25z" fill="#ECFDF3" stroke="#BBF7D0" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M18 25l22 11 22-11M40 36v26" stroke="#16A34A" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                    <path d="M29 19.5l22 11" stroke="#86EFAC" stroke-width="2" stroke-linecap="round"/>
                    <g class="anim-twinkle"><path d="M64 14l1.6 3.4 3.4 1.6-3.4 1.6L64 24l-1.6-3.4L59 19l3.4-1.6z" fill="#4ADE80"/></g>
                </svg>
                <p class="mt-2 text-sm font-semibold">Belum ada data</p>
                <p class="mt-1 text-xs text-[#64748B] max-w-[220px]">Data produk terlaris akan muncul setelah ada transaksi.</p>
            </div>
            @endforelse
        </section>
    </aside>
</div>
</div>


@push('scripts')
<script>
const salesData = @json($salesChart);
const salesCtx = document.getElementById('salesChart');
if (salesCtx) {
    const g = salesCtx.getContext('2d');
    const grad = g.createLinearGradient(0, 0, 0, 280);
    grad.addColorStop(0, 'rgba(22, 163, 74, 0.95)');
    grad.addColorStop(1, 'rgba(74, 222, 128, 0.55)');
    new Chart(salesCtx, {
        data: {
            labels: salesData.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Omzet (Rp)',
                    data: salesData.revenue,
                    backgroundColor: grad,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 38,
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Transaksi',
                    data: salesData.orders,
                    borderColor: 'rgb(168, 85, 247)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: 'rgb(168, 85, 247)',
                    pointBorderWidth: 2,
                    tension: 0.4,
                    order: 1,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 12,
                    cornerRadius: 10,
                    callbacks: {
                        label: function(ctx) {
                            if (ctx.dataset.label === 'Omzet (Rp)') {
                                return ' Omzet: Rp ' + Intl.NumberFormat('id').format(ctx.raw);
                            }
                            return ' Transaksi: ' + ctx.raw;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    border: { display: false },
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { size: 11 }, color: '#94a3b8',
                        callback: v => {
                            if (v >= 1000000) return (v/1000000).toFixed(1) + ' jt';
                            if (v >= 1000)    return (v/1000).toFixed(0) + ' rb';
                            return v;
                        }
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    border: { display: false },
                    grid: { drawOnChartArea: false },
                    ticks: { font: { size: 11 }, color: '#94a3b8', stepSize: 1, callback: v => Number.isInteger(v) ? v : '' }
                },
                x: {
                    border: { display: false },
                    grid: { display: false },
                    ticks: { font: { size: 11 }, color: '#94a3b8' }
                }
            }
        }
    });
}

@if(count($paymentBreakdown) > 0)
const payCtx = document.getElementById('paymentChart');
if (payCtx) {
    const payData = @json($paymentBreakdown);
    new Chart(payCtx, {
        type: 'doughnut',
        data: {
            labels: payData.map(p => p.payment_method.toUpperCase()),
            datasets: [{
                data: payData.map(p => p.total),
                backgroundColor: ['#16a34a','#3b82f6','#a855f7','#f59e0b','#ef4444','#64748b'],
                borderWidth: 3,
                borderColor: '#fff',
                hoverOffset: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            cutout: '72%'
        }
    });
}
@endif

function miniLine(id, data, color, fmt) {
    const el = document.getElementById(id);
    if (!el) return;
    const ctx = el.getContext('2d');
    const h = el.parentElement.clientHeight || 120;
    const g = ctx.createLinearGradient(0, 0, 0, h);
    g.addColorStop(0, color + '55');
    g.addColorStop(1, color + '00');
    new Chart(el, {
        type: 'line',
        data: { labels: salesData.labels, datasets: [{ data: data, borderColor: color, backgroundColor: g, fill: true, borderWidth: 2.2, tension: 0.4, pointRadius: 0, pointHoverRadius: 4, pointHoverBackgroundColor: color }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 1200, easing: 'easeOutCubic' },
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0B1B3F', padding: 10, cornerRadius: 8, displayColors: false, callbacks: { label: c => fmt(c.raw) } } },
            scales: {
                x: { border: { display: false }, grid: { display: false }, ticks: { font: { size: 12 }, color: '#64748b', maxTicksLimit: 4 } },
                y: { beginAtZero: true, border: { display: false }, grid: { color: '#f1f5f9' }, ticks: { font: { size: 12 }, color: '#64748b', maxTicksLimit: 3, callback: v => v >= 1000000 ? (v/1000000).toFixed(1) + ' jt' : v >= 1000 ? (v/1000).toFixed(0) + ' rb' : v } }
            }
        }
    });
}
miniLine('miniRevenue', salesData.revenue, '#16A34A', v => ' Omzet: Rp ' + Intl.NumberFormat('id').format(v));
miniLine('miniOrders', salesData.orders, '#A855F7', v => ' Transaksi: ' + v);
</script>
@endpush

