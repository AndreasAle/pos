@php
$slides = [
    ['tag' => 'Cashback',  'title' => 'Tebus cashback untuk bulan depan', 'cta' => 'Tebus sekarang', 'href' => (Route::has('saas.plans') ? route('saas.plans') : '#')],
    ['tag' => 'Hemat',     'title' => 'Bayar paket tahunan, hemat sampai 20%', 'cta' => 'Lihat paket', 'href' => (Route::has('saas.plans') ? route('saas.plans') : '#')],
    ['tag' => 'Referral',  'title' => 'Ajak teman pakai Yoka POS, dapat bonus', 'cta' => 'Pelajari', 'href' => (Route::has('saas.plans') ? route('saas.plans') : '#')],
];
@endphp
{{-- Kartu promo di bawah sidebar. Berganti otomatis tiap 5 detik; disembunyikan saat sidebar collapsed. --}}
<div class="px-3 pb-3 pt-2 shrink-0" :class="collapsed ? 'lg:hidden' : ''"
     x-data="{ i: 0, n: {{ count($slides) }}, t: null,
               start() { this.t = setInterval(() => this.i = (this.i + 1) % this.n, 5000) },
               stop() { clearInterval(this.t) } }"
     x-init="start()" @mouseenter="stop()" @mouseleave="start()">
    <div class="relative overflow-hidden rounded-2xl h-40 bg-slate-900 shadow-sm">
        <div class="absolute inset-0 bg-no-repeat transition-transform duration-[6000ms] ease-out"
             :style="`background-image:url('{{ asset('images/promo/kasir.webp') }}');background-size:225% auto;background-position:${[76,72,80][i]}% 16%;transform:scale(${1 + i * 0.03})`"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/90 via-slate-900/45 to-slate-900/10"></div>

        @foreach($slides as $k => $sl)
        <a href="{{ $sl['href'] }}" x-show="i === {{ $k }}" x-cloak
           x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-200 absolute inset-x-0 bottom-0" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
           class="absolute inset-x-0 bottom-0 p-3.5 text-white">
            <span class="inline-block text-[10px] font-bold uppercase tracking-wide bg-emerald-500 text-white rounded px-1.5 py-0.5">{{ $sl['tag'] }}</span>
            <p class="mt-1.5 text-[13px] font-bold leading-snug">{{ $sl['title'] }}</p>
            <span class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-300">{{ $sl['cta'] }}
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </span>
        </a>
        @endforeach

        <div class="absolute top-2.5 right-3 flex gap-1">
            @foreach($slides as $k => $sl)
            <button type="button" @click="i = {{ $k }}" class="h-1 rounded-full transition-all" :class="i === {{ $k }} ? 'w-4 bg-white' : 'w-1.5 bg-white/50'" aria-label="Promo {{ $k + 1 }}"></button>
            @endforeach
        </div>
    </div>
</div>
