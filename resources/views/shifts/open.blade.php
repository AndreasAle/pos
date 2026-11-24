@extends('layouts.app')
@section('title','Buka Shift')
@section('page-title','Buka Shift Baru')

@section('content')
@if(\App\Support\InApp::check())
{{-- Versi aplikasi: satu layar, tanpa kartu. Angka modal jadi fokus utama. --}}
<div class="max-w-md mx-auto pt-8 text-[#0B1B3F]"
     x-data="{
        cash: {{ (int) old('opening_cash', 0) }},
        get shown() { return this.cash ? Number(this.cash).toLocaleString('id-ID') : '' },
        type(e) { this.cash = parseInt(e.target.value.replace(/\D/g, '') || '0', 10); e.target.value = this.shown }
     }">
    <form method="POST" action="{{ route('shifts.store') }}">
        @csrf
        <input type="hidden" name="opening_cash" :value="cash">

        <p class="text-[13px] font-medium text-[#64748B]">Outlet</p>
        @if($outlets->count() > 1)
            <div class="relative mt-1 inline-flex items-center">
                <select name="outlet_id" required class="appearance-none bg-transparent pr-7 text-lg font-semibold focus:outline-none">
                    @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected(old('outlet_id', auth()->user()->outlet_id) == $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-0 w-5 h-5 text-[#94A3B8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </div>
        @else
            <input type="hidden" name="outlet_id" value="{{ $outlets->first()?->id }}">
            <p class="mt-1 text-lg font-semibold">{{ $outlets->first()?->name }}</p>
        @endif

        <label for="cash_display" class="block mt-10 text-[13px] font-medium text-[#64748B]">Uang tunai di laci saat ini</label>
        <div class="mt-2 flex items-baseline gap-2 border-b-2 border-[#E2E8F0] focus-within:border-[#16A34A] transition-colors pb-2">
            <span class="text-2xl font-semibold text-[#94A3B8]">Rp</span>
            <input id="cash_display" type="text" inputmode="numeric" autocomplete="off" placeholder="0"
                   :value="shown" @input="type($event)"
                   class="w-full min-w-0 bg-transparent border-0 p-0 text-[44px] leading-none font-semibold tracking-[-0.02em] tabular-nums placeholder:text-[#CBD5E1] focus:outline-none focus:ring-0">
        </div>

        <div class="mt-5 flex flex-wrap gap-x-5 gap-y-3">
            @foreach([100000 => '100 rb', 200000 => '200 rb', 500000 => '500 rb', 1000000 => '1 jt'] as $v => $l)
            <button type="button" @click="cash = {{ $v }}"
                    class="text-[15px] font-semibold transition-colors"
                    :class="cash === {{ $v }} ? 'text-[#15803D]' : 'text-[#64748B]'">{{ $l }}</button>
            @endforeach
            <button type="button" @click="cash = 0" class="text-[15px] font-semibold text-[#94A3B8]" x-show="cash > 0" x-cloak>Kosongkan</button>
        </div>

        <p class="mt-12 text-[14px] leading-relaxed text-[#64748B]">
            Hitung dulu uang di laci, lalu isi jumlahnya. Semua penjualan setelah ini masuk ke shift kamu, dan saat tutup shift kas akhirnya dicocokkan otomatis.
        </p>

        <button type="submit"
                class="mt-6 w-full h-14 rounded-2xl bg-[#16A34A] active:bg-[#15803D] text-white text-[16px] font-semibold shadow-[0_8px_20px_rgba(22,163,74,.25)] transition-colors">
            Buka shift
        </button>
    </form>
</div>
@else
<div class="page text-[#0B1B3F] pt-2 max-w-4xl mx-auto">
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
@endif
@endsection
