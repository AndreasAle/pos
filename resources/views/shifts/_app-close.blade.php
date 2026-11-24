{{--
    Tutup shift versi aplikasi: satu layar seperti Buka Shift. Yang dihitung kasir
    (uang di laci) jadi fokus; selisih langsung terlihat saat mengetik.
--}}
@php
    $payLabel = ['cash' => 'Tunai', 'qris' => 'QRIS', 'debit' => 'Debit', 'credit' => 'Kartu kredit', 'transfer' => 'Transfer', 'ewallet' => 'E-wallet'];
    $nonCash  = collect($byMethod)->except('cash')->filter(fn ($v) => $v > 0);
@endphp

<div class="max-w-md mx-auto pt-6 text-[#0B1B3F]"
     x-data="{
        expected: {{ (int) $expectedCash }},
        actual: {{ (int) old('closing_cash_actual', $expectedCash) }},
        get shown() { return Number(this.actual || 0).toLocaleString('id-ID') },
        type(e) { this.actual = parseInt(e.target.value.replace(/\D/g, '') || '0', 10); e.target.value = this.shown },
        rp(v) { return 'Rp ' + Math.abs(v).toLocaleString('id-ID') },
        get diff() { return this.actual - this.expected }
     }">

    <p class="text-[15px] text-[#64748B]">{{ $shift->outlet->name ?? 'Shift' }} · {{ number_format($totalOrders) }} transaksi · total {{ $rp($totalSale) }}</p>

    <form method="POST" action="{{ route('shifts.close.store', $shift) }}">
        @csrf
        <input type="hidden" name="closing_cash_actual" :value="actual">

        <label for="cash_count" class="block mt-8 text-[13px] font-medium text-[#64748B]">Hitung uang tunai di laci sekarang</label>
        <div class="mt-2 flex items-baseline gap-2 border-b-2 border-[#E2E8F0] focus-within:border-[#16A34A] transition-colors pb-2">
            <span class="text-2xl font-semibold text-[#94A3B8]">Rp</span>
            <input id="cash_count" type="text" inputmode="numeric" autocomplete="off" placeholder="0"
                   :value="shown" @input="type($event)"
                   class="w-full min-w-0 bg-transparent border-0 p-0 text-[44px] leading-none font-semibold tracking-[-0.02em] tabular-nums placeholder:text-[#CBD5E1] focus:outline-none focus:ring-0">
        </div>

        <p class="mt-3 text-[15px] font-semibold"
           :class="diff === 0 ? 'text-[#15803D]' : (diff < 0 ? 'text-[#DC2626]' : 'text-[#B45309]')"
           x-text="diff === 0 ? 'Pas dengan catatan' : (rp(diff) + (diff < 0 ? ' kurang dari seharusnya' : ' lebih dari seharusnya'))"></p>

        {{-- Seharusnya --}}
        <dl class="mt-8 text-[15px]">
            <div class="flex justify-between py-2 text-[#64748B]"><dt>Kas awal</dt><dd class="tabular-nums">{{ $rp($shift->opening_cash) }}</dd></div>
            <div class="flex justify-between py-2 text-[#64748B]"><dt>Penjualan tunai</dt><dd class="tabular-nums">{{ $rp($byMethod['cash'] ?? 0) }}</dd></div>
            <div class="flex justify-between py-2 mt-1 pt-3 border-t border-[#F1F5F9] font-semibold">
                <dt>Seharusnya di laci</dt>
                <dd class="tabular-nums"><button type="button" @click="actual = expected" class="underline decoration-[#CBD5E1] underline-offset-4">{{ $rp($expectedCash) }}</button></dd>
            </div>
        </dl>

        @if($nonCash->count())
            <p class="mt-6 text-[13px] font-semibold text-[#64748B]">Tidak masuk laci</p>
            <dl class="mt-1 text-[15px] text-[#64748B]">
                @foreach($nonCash as $m => $v)
                    <div class="flex justify-between py-1.5"><dt>{{ $payLabel[$m] ?? ucfirst($m) }}</dt><dd class="tabular-nums">{{ $rp($v) }}</dd></div>
                @endforeach
            </dl>
            <p class="mt-1 text-[13px] text-[#94A3B8]">Cocokkan dengan mutasi di aplikasi bank atau e-wallet.</p>
        @endif

        <label for="notes" class="block mt-8 text-[13px] font-medium text-[#64748B]">Catatan <span class="font-normal text-[#94A3B8]">(opsional)</span></label>
        <textarea id="notes" name="notes" rows="2" maxlength="500" placeholder="Misalnya alasan selisih"
                  class="mt-1 w-full bg-transparent border-0 border-b border-[#E2E8F0] px-0 py-2 text-[15px] placeholder:text-[#CBD5E1] focus:outline-none focus:ring-0 focus:border-[#16A34A] resize-none">{{ old('notes') }}</textarea>

        <button type="submit"
                class="mt-10 w-full h-14 rounded-2xl bg-[#0B1B3F] active:bg-[#1E293B] text-white text-[16px] font-semibold">
            Tutup shift
        </button>
        <a href="{{ route('shifts.show', $shift) }}" class="block mt-4 text-center text-[15px] font-semibold text-[#64748B]">Batal</a>
    </form>
</div>
