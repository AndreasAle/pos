{{--
    Detail shift versi aplikasi: satu kolom tenang. Angka utama di atas, lalu
    rincian per metode, hitung kas, dan transaksi. Tanpa kartu dan bar.
--}}
@php
    $tz       = config('app.timezone');
    $payLabel = ['cash' => 'Tunai', 'qris' => 'QRIS', 'debit' => 'Debit', 'credit' => 'Kartu kredit', 'transfer' => 'Transfer', 'ewallet' => 'E-wallet'];
    $orders   = $shift->orders->sortBy('created_at');
    $dot      = ['draft' => '#94A3B8', 'pending' => '#D97706', 'cancelled' => '#DC2626', 'refunded' => '#9333EA'];
    $word     = ['draft' => 'Draft', 'pending' => 'Menunggu', 'cancelled' => 'Dibatalkan', 'refunded' => 'Refund'];
    $row      = 'flex justify-between gap-4 py-2';
    $expected = $isOpen ? (float) $shift->opening_cash + (float) ($byMethod['cash'] ?? 0) : (float) $shift->closing_cash_expected;
@endphp

<div class="max-w-xl mx-auto pt-6 pb-4 text-[#0B1B3F]">

    {{-- Ringkas --}}
    <p class="inline-flex items-center gap-2 text-[13px] font-semibold {{ $isOpen ? 'text-[#15803D]' : 'text-[#94A3B8]' }}">
        <span class="w-1.5 h-1.5 rounded-full {{ $isOpen ? 'bg-[#16A34A]' : 'bg-[#CBD5E1]' }}"></span>{{ $isOpen ? 'Sedang berjalan' : 'Selesai' }}
    </p>
    <p class="mt-1 text-[40px] leading-none font-semibold tracking-[-0.02em] tabular-nums">{{ $rp($totalSales) }}</p>
    <p class="mt-3 text-[15px] text-[#64748B]">
        {{ $totalOrders }} transaksi ·
        {{ $shift->opened_at->timezone($tz)->locale('id')->translatedFormat('j M, H:i') }}–{{ $shift->closed_at ? $shift->closed_at->timezone($tz)->format('H:i') : 'sekarang' }}
        @if($duration)· {{ $duration }}@endif
    </p>
    <p class="mt-1 text-[15px] text-[#64748B]">{{ \Illuminate\Support\Str::title(mb_strtolower($shift->user->name ?? '-')) }} · {{ $shift->outlet->name ?? '-' }}</p>

    {{-- Per metode --}}
    <h2 class="mt-10 text-[13px] font-semibold text-[#64748B]">Penjualan per metode</h2>
    <dl class="mt-1 text-[15px] divide-y divide-[#F1F5F9]">
        @foreach($byMethod as $method => $amount)
            @if($amount > 0)
            <div class="{{ $row }} py-3"><dt>{{ $payLabel[$method] ?? ucfirst($method) }}</dt><dd class="tabular-nums">{{ $rp($amount) }}</dd></div>
            @endif
        @endforeach
        @if(collect($byMethod)->sum() <= 0)
            <p class="py-3 text-[#94A3B8]">Belum ada penjualan.</p>
        @endif
    </dl>

    {{-- Kas --}}
    <h2 class="mt-10 text-[13px] font-semibold text-[#64748B]">Uang tunai di laci</h2>
    <dl class="mt-1 text-[15px]">
        <div class="{{ $row }} text-[#64748B]"><dt>Kas awal</dt><dd class="tabular-nums">{{ $rp($shift->opening_cash) }}</dd></div>
        <div class="{{ $row }} text-[#64748B]"><dt>Penjualan tunai</dt><dd class="tabular-nums">{{ $rp($totalCash) }}</dd></div>
        <div class="{{ $row }} pt-3 mt-1 border-t border-[#F1F5F9] font-semibold"><dt>Seharusnya</dt><dd class="tabular-nums">{{ $rp($expected) }}</dd></div>
        @unless($isOpen)
            <div class="{{ $row }} font-semibold"><dt>Dihitung</dt><dd class="tabular-nums">{{ $rp($shift->closing_cash_actual) }}</dd></div>
            <div class="{{ $row }}">
                <dt class="text-[#64748B]">Selisih</dt>
                <dd class="tabular-nums font-semibold {{ $diff < 0 ? 'text-[#DC2626]' : ($diff > 0 ? 'text-[#15803D]' : 'text-[#94A3B8]') }}">
                    {{ $diff == 0 ? 'Pas' : (($diff > 0 ? '+' : '−') . $rp(abs($diff)) . ($diff > 0 ? ' lebih' : ' kurang')) }}
                </dd>
            </div>
        @endunless
    </dl>

    @if($isOpen && $shift->user_id === auth()->id())
        <a href="{{ route('shifts.close', $shift) }}" class="mt-8 w-full h-14 inline-flex items-center justify-center rounded-2xl bg-[#0B1B3F] active:bg-[#1E293B] text-white text-[16px] font-semibold">Tutup shift</a>
    @endif

    {{-- Transaksi --}}
    <h2 class="mt-10 text-[13px] font-semibold text-[#64748B]">Transaksi · {{ $orders->count() }}</h2>
    @if($orders->count())
        <ul class="mt-1 divide-y divide-[#F1F5F9]">
            @foreach($orders as $o)
                <li>
                    <a href="{{ route('orders.show', $o) }}" class="flex items-center gap-4 py-3.5 active:bg-[#F8FAFC]">
                        <span class="w-12 shrink-0 text-[15px] tabular-nums text-[#64748B]">{{ $o->created_at->timezone($tz)->format('H:i') }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[16px] font-semibold tabular-nums {{ in_array($o->status, ['cancelled', 'refunded']) ? 'text-[#94A3B8] line-through decoration-1' : '' }}">{{ $rp($o->grand_total) }}</span>
                            <span class="block truncate text-[13px] text-[#94A3B8]">{{ $payLabel[$o->payment_method] ?? ucfirst((string) $o->payment_method) }} · {{ $o->order_number }}</span>
                        </span>
                        @if($dot[$o->status] ?? null)
                            <span class="shrink-0 inline-flex items-center gap-1.5 text-[13px] font-medium" style="color: {{ $dot[$o->status] }}">
                                <span class="w-1.5 h-1.5 rounded-full" style="background: {{ $dot[$o->status] }}"></span>{{ $word[$o->status] }}
                            </span>
                        @endif
                        <svg class="w-4 h-4 shrink-0 text-[#CBD5E1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </li>
            @endforeach
        </ul>
    @else
        <p class="mt-2 text-[15px] text-[#94A3B8]">Belum ada transaksi di shift ini.</p>
    @endif
</div>
