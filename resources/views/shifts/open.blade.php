@extends('layouts.app')
@section('title','Buka Shift')
@section('page-title','Buka Shift Baru')

@push('styles')
<style>
    .pg .fade-up { opacity:0; transform:translateY(12px); animation:pg-up .55s cubic-bezier(.2,.7,.2,1) forwards; animation-delay:calc(var(--i,0) * 80ms); }
    @keyframes pg-up { to { opacity:1; transform:none } }
    .pg .float { animation:pg-float 4.5s ease-in-out infinite }
    .pg .twinkle { animation:pg-tw 2.4s ease-in-out infinite; transform-box:fill-box; transform-origin:center }
    .pg .sweep { transform-origin:56px 56px; animation:pg-sweep 10s linear infinite }
    .pg .coin { animation:pg-coin 3s ease-in-out infinite; transform-box:fill-box; transform-origin:center }
    @keyframes pg-float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
    @keyframes pg-tw { 0%,100%{opacity:.3;transform:scale(.7)} 50%{opacity:1;transform:scale(1.1)} }
    @keyframes pg-sweep { to { transform:rotate(360deg) } }
    @keyframes pg-coin { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
    @media (prefers-reduced-motion: reduce) { .pg *, .pg svg * { animation:none !important } .pg .fade-up { opacity:1; transform:none } }
</style>
@endpush

@section('content')
<div class="pg text-[#0B1B3F] pt-2 max-w-4xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(0,340px)] gap-5">

        {{-- Form --}}
        <section class="fade-up rounded-xl border border-[#E5E7EB] bg-white p-5 sm:p-6" style="--i:0"
                 x-data="{ cash: {{ (int) old('opening_cash', 0) }}, fmt(v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID') } }">
            <h2 class="text-lg font-semibold">Buka Shift Kasir</h2>
            <p class="mt-1 text-sm text-[#64748B]">Pilih outlet dan catat uang tunai di laci kasir sebagai modal awal.</p>

            <form method="POST" action="{{ route('shifts.store') }}" class="mt-5 space-y-5">
                @csrf

                <div>
                    <label for="outlet_id" class="block text-sm font-semibold mb-1.5">Outlet <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <select id="outlet_id" name="outlet_id" required
                                class="appearance-none w-full h-11 pl-3.5 pr-10 rounded-lg border border-[#E5E7EB] bg-white text-sm font-medium focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30 transition">
                            @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}" {{ (old('outlet_id', auth()->user()->outlet_id) == $outlet->id) ? 'selected' : '' }}>{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>

                <div>
                    <label for="opening_cash" class="block text-sm font-semibold mb-1.5">Modal Awal (uang tunai) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-[#64748B]">Rp</span>
                        <input id="opening_cash" type="number" name="opening_cash" min="0" step="1" required x-model.number="cash" inputmode="numeric"
                               class="w-full h-11 pl-11 pr-3.5 rounded-lg border border-[#E5E7EB] bg-white text-base font-semibold focus:outline-none focus:border-[#16A34A] focus:ring-2 focus:ring-[#16A34A]/30 transition">
                    </div>
                    <p class="mt-1.5 text-sm text-[#64748B]">Terhitung: <span class="font-semibold text-[#0B1B3F]" x-text="fmt(cash)"></span></p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach([0 => 'Rp 0', 100000 => '100 rb', 200000 => '200 rb', 500000 => '500 rb', 1000000 => '1 jt'] as $v => $l)
                        <button type="button" @click="cash = {{ $v }}"
                                class="h-8 px-3 rounded-full border text-[13px] font-semibold transition-colors"
                                :class="cash === {{ $v }} ? 'border-[#16A34A] bg-[#ECFDF3] text-[#15803D]' : 'border-[#E5E7EB] text-[#64748B] hover:border-[#16A34A]/60 hover:text-[#0B1B3F]'">{{ $l }}</button>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="group w-full h-12 inline-flex items-center justify-center gap-2 rounded-lg bg-[#16A34A] hover:bg-[#15803D] text-white text-sm font-semibold shadow-sm transition-colors">
                    Buka Shift &amp; Mulai Transaksi
                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </button>
                <a href="{{ route('shifts.index') }}" class="block text-center text-sm font-semibold text-[#64748B] hover:text-[#0B1B3F]">Batal</a>
            </form>
        </section>

        {{-- Panel info --}}
        <aside class="fade-up relative overflow-hidden rounded-xl border border-[#BBF7D0] bg-gradient-to-br from-[#ECFDF3] via-[#F3FDF7] to-white p-5 sm:p-6" style="--i:1">
            <div class="w-32 h-32 mx-auto">
                <svg viewBox="0 0 112 112" class="w-full h-full float" fill="none" aria-hidden="true">
                    <ellipse cx="56" cy="102" rx="30" ry="5" fill="#16A34A" opacity=".12"/>
                    <circle cx="56" cy="56" r="40" fill="#fff" stroke="#BBF7D0" stroke-width="2"/>
                    <circle cx="56" cy="56" r="32" fill="#F3FDF7" stroke="#D1FAE5" stroke-width="2"/>
                    <path d="M56 30v4M56 78v4M30 56h4M78 56h4" stroke="#86EFAC" stroke-width="3" stroke-linecap="round"/>
                    <g class="sweep"><path d="M56 56V38" stroke="#16A34A" stroke-width="3.5" stroke-linecap="round"/></g>
                    <path d="M56 56l11 7" stroke="#0B1B3F" stroke-width="3.5" stroke-linecap="round"/>
                    <circle cx="56" cy="56" r="3.5" fill="#16A34A"/>
                    <g class="coin"><circle cx="96" cy="36" r="9" fill="#FBBF24"/><circle cx="96" cy="36" r="5.8" stroke="#F59E0B" stroke-width="1.8"/><path d="M96 32.5v7" stroke="#F59E0B" stroke-width="1.8" stroke-linecap="round"/></g>
                    <g class="twinkle"><path d="M16 30l1.8 4.2 4.2 1.8-4.2 1.8L16 42l-1.8-4.2L10 36l4.2-1.8z" fill="#4ADE80"/></g>
                </svg>
            </div>

            <h3 class="mt-3 text-base font-semibold text-center">Sebelum mulai transaksi</h3>
            <ol class="mt-4 space-y-3.5 text-sm">
                @foreach([
                    ['Hitung uang di laci', 'Pastikan jumlah tunai sama dengan modal awal yang kamu isi.'],
                    ['Buka shift', 'Semua penjualan setelah ini tercatat di shift kamu.'],
                    ['Tutup shift saat selesai', 'Sistem mencocokkan kas akhir dengan penjualan tunai.'],
                ] as $i => [$t, $d])
                <li class="flex gap-3">
                    <span class="w-6 h-6 rounded-full bg-[#16A34A] text-white text-xs font-bold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                    <div>
                        <p class="font-semibold leading-tight">{{ $t }}</p>
                        <p class="text-[#64748B] mt-0.5">{{ $d }}</p>
                    </div>
                </li>
                @endforeach
            </ol>
        </aside>
    </div>
</div>
@endsection
