{{-- Header situs bersama (landing, harga, bundling, panduan, artikel).
     $home = '' di landing, route('landing') di halaman lain. $solid = header putih sejak awal (halaman berlatar putih).
     Bar navy tipis di atas (promo, WhatsApp, bahasa) menghilang saat halaman digulir. --}}
@php
    $home = $home ?? '';
    $waLink = $waLink ?? 'https://wa.me/6285891111795';
    $solid = $solid ?? false;
    $locale = $appLocale ?? app()->getLocale();
    $produk = [
        [route('features.show', 'transaksi-kasir'), 'Transaksi kasir', 'Proses pesanan lebih cepat'],
        [route('features.show', 'pembayaran-qris'), 'Pembayaran & QRIS', 'Tunai, QRIS, dan split payment'],
        [route('features.show', 'hold-order'), 'Hold order', 'Simpan pesanan dan nomor meja'],
        [route('features.show', 'struk-printer'), 'Struk & printer', 'Browser, PDF, Bluetooth, RawBT'],
        [route('features.show', 'stok-resep'), 'Stok & resep', 'Stok bahan berkurang otomatis'],
        [route('features.show', 'kitchen-display'), 'Layar dapur', 'Pesanan dapur lebih teratur'],
        [route('features.show', 'shift-karyawan'), 'Shift & karyawan', 'Rekap kas dan akses setiap role'],
        [route('features.show', 'pelanggan-promosi'), 'Pelanggan & promosi', 'Poin, bundle, dan promo'],
    ];
    $bantuan = [
        [route('guides.index'), 'Panduan', 'Cara pakai langkah demi langkah', false],
        [route('articles.index'), 'Artikel', 'Tips usaha kuliner dan kasir', false],
        [$home.'#faq', 'Tanya jawab', 'Pertanyaan yang sering muncul', false],
        [$waLink, 'Chat WhatsApp', 'Ngobrol langsung dengan tim Yoka', true],
    ];
@endphp
<header
    x-data="{ top: true, solid: {{ $solid ? 'true' : 'false' }}, open: null, mobileOpen: false, mobileGroup: null, get scrolled() { return this.solid || ! this.top } }"
    x-init="top = window.scrollY <= 40; window.addEventListener('scroll', () => top = window.scrollY <= 40, { passive: true })"
    @keydown.escape.window="open = null"
    class="fixed inset-x-0 top-0 z-40 pt-[env(safe-area-inset-top)] [transform:translate3d(0,0,0)]"
>
    {{-- Bar atas --}}
    <div class="overflow-hidden bg-[#0B1B3F] text-white transition-[height] duration-300" :class="top && ! mobileOpen ? 'h-9' : 'h-0'">
        <div class="mx-auto flex h-9 max-w-[1200px] items-center gap-5 px-5 text-[13px] sm:px-6">
            <p class="truncate text-white/80"><span class="font-semibold text-white">Coba gratis 7 hari</span><span class="hidden sm:inline">, tanpa kartu kredit dan tanpa biaya pemasangan</span></p>
            <a href="{{ $waLink }}" target="_blank" rel="noopener" class="ml-auto hidden shrink-0 items-center gap-1.5 font-medium text-white/90 hover:text-white sm:inline-flex">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.79.47 3.47 1.36 4.95L2 22l5.27-1.38a9.9 9.9 0 0 0 4.77 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2z"/></svg>
                Chat WhatsApp
            </a>
            <div class="ml-auto flex shrink-0 items-center gap-1.5 font-semibold sm:ml-0" role="group" aria-label="Bahasa">
                @foreach(['id' => 'ID', 'en' => 'EN'] as $code => $label)
                    @if(! $loop->first)<span class="text-white/30" aria-hidden="true">|</span>@endif
                    <a href="{{ route('lang.switch', $code) }}" hreflang="{{ $code }}" class="{{ $locale === $code ? 'text-white' : 'text-white/55 hover:text-white' }}" @if($locale === $code) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Bar utama --}}
    <div class="transition-[background-color,box-shadow] duration-300"
         :class="scrolled || open || mobileOpen ? 'bg-white shadow-[0_1px_0_rgba(15,23,42,.08),0_8px_24px_-16px_rgba(15,23,42,.25)]' : 'bg-transparent'">
        <div class="mx-auto flex h-[60px] max-w-[1200px] items-center px-5 sm:h-[64px] sm:px-6 lg:h-[68px]">
            <a href="{{ $home ?: '#' }}" class="flex shrink-0 items-center gap-2" aria-label="Yoka POS">
                <span class="relative block h-7 w-[78px] sm:h-8 sm:w-[88px]">
                    <img src="{{ asset('images/brand/yoka-logo-white.png') }}" alt="Yoka" class="absolute inset-0 h-full w-full object-contain object-left transition-opacity duration-300" :class="scrolled || open || mobileOpen ? 'opacity-0' : 'opacity-100'">
                    <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="" class="absolute inset-0 h-full w-full object-contain object-left transition-opacity duration-300" :class="scrolled || open || mobileOpen ? 'opacity-100' : 'opacity-0'">
                </span>
                <span class="text-[17px] font-bold leading-none tracking-tight transition-colors duration-300 sm:text-[19px]" :class="scrolled || open || mobileOpen ? 'text-[#15803D]' : 'text-white'">POS</span>
            </a>

            {{-- Menu desktop: semua item satu gaya huruf --}}
            <nav class="ml-10 hidden items-center gap-1 text-[15px] font-semibold transition-colors duration-300 lg:flex" :class="scrolled || open ? 'text-[#0B1B3F]' : 'text-white'">
                @foreach(['produk' => 'Produk', 'harga' => 'Harga', 'bundling' => 'Bundling', 'bantuan' => 'Bantuan'] as $key => $label)
                    @if(in_array($key, ['produk', 'bantuan'], true))
                        <div class="relative" @mouseenter="open = '{{ $key }}'" @mouseleave="open = null">
                            <button type="button" @click="open = open === '{{ $key }}' ? null : '{{ $key }}'" :aria-expanded="open === '{{ $key }}'" aria-haspopup="true"
                                    class="inline-flex h-[68px] items-center gap-1 rounded-full px-3.5 transition-opacity hover:opacity-70">
                                {{ $label }}
                                <svg class="h-4 w-4 opacity-60 transition-transform duration-200" :class="open === '{{ $key }}' ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                            </button>

                            @if($key === 'produk')
                                <div x-show="open === 'produk'" x-cloak x-transition.opacity.duration.150ms
                                     class="absolute -left-6 top-[60px] w-[720px] rounded-2xl border border-slate-200 bg-white p-6 text-[#0B1B3F] shadow-[0_24px_60px_-20px_rgba(15,23,42,.30)]">
                                    <div class="grid grid-cols-[1fr_1fr_.95fr] gap-6">
                                        @foreach(array_chunk($produk, 4) as $chunk)
                                            <div class="space-y-0.5">
                                                @foreach($chunk as [$href, $title, $desc])
                                                    <a href="{{ $href }}" class="group block rounded-xl px-3 py-2.5 hover:bg-[#F3F6F4]">
                                                        <span class="block text-[14px] font-semibold group-hover:text-[#15803D]">{{ $title }}</span>
                                                        <span class="mt-0.5 block text-[13px] font-normal text-slate-500">{{ $desc }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endforeach
                                        <div class="flex flex-col rounded-xl bg-[#0B1B3F] p-5 text-white">
                                            <p class="text-[15px] font-semibold">Laporan & Tanya Yoka</p>
                                            <p class="mt-2 flex-1 text-[13px] font-normal leading-relaxed text-white/75">Tanya omzet, profit, stok, dan menu terlaris langsung dari data tokomu.</p>
                                            <a href="{{ route('features.show', 'laporan') }}" class="mt-4 text-[13px] font-semibold text-[#4ADE80] hover:underline">Lihat cara kerjanya &rarr;</a>
                                            <a href="{{ $home }}#keunggulan" class="mt-2 text-[13px] font-semibold text-white/80 hover:text-white">Keunggulan Yoka &rarr;</a>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div x-show="open === 'bantuan'" x-cloak x-transition.opacity.duration.150ms
                                     class="absolute -left-4 top-[60px] w-[300px] rounded-2xl border border-slate-200 bg-white p-2 text-[#0B1B3F] shadow-[0_24px_60px_-20px_rgba(15,23,42,.30)]">
                                    @foreach($bantuan as [$href, $title, $desc, $external])
                                        <a href="{{ $href }}" @if($external) target="_blank" rel="noopener" @endif class="group block rounded-xl px-3 py-2.5 hover:bg-[#F3F6F4]">
                                            <span class="block text-[14px] font-semibold group-hover:text-[#15803D]">{{ $title }}</span>
                                            <span class="mt-0.5 block text-[13px] font-normal text-slate-500">{{ $desc }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <a href="{{ $key === 'harga' ? route('pricing') : route('pricing.bundles') }}"
                           class="inline-flex h-[68px] items-center rounded-full px-3.5 transition-opacity hover:opacity-70 {{ request()->routeIs($key === 'harga' ? 'pricing' : 'pricing.bundles') ? 'underline decoration-[#16A34A] decoration-2 underline-offset-[10px]' : '' }}">{{ $label }}</a>
                    @endif
                @endforeach
            </nav>

            <div class="ml-auto hidden items-center gap-6 lg:flex">
                <a href="{{ url('/login') }}" class="text-[15px] font-semibold transition-colors duration-300 hover:opacity-70" :class="scrolled || open ? 'text-[#0B1B3F]' : 'text-white'">Masuk</a>
                <a href="{{ route('register') }}" class="rounded-full px-6 py-2.5 text-[15px] font-semibold transition-colors duration-300"
                   :class="scrolled || open ? 'bg-[#16A34A] text-white hover:bg-[#15803D]' : 'bg-white text-[#15803D] hover:bg-emerald-50'">Coba gratis</a>
            </div>

            <button type="button" @click="mobileOpen = !mobileOpen" class="-mr-2 ml-auto flex h-11 w-11 items-center justify-center rounded-lg lg:hidden" :class="scrolled || mobileOpen ? 'text-[#0B1B3F]' : 'text-white'" :aria-expanded="mobileOpen" aria-label="Menu">
                <svg x-show="!mobileOpen" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                <svg x-show="mobileOpen" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Menu HP --}}
        <div x-show="mobileOpen" x-cloak x-transition class="max-h-[calc(100dvh-60px)] overflow-y-auto border-t border-slate-100 bg-white px-5 pb-6 pt-2 lg:hidden">
            @foreach(['produk' => ['Produk', $produk], 'bantuan' => ['Bantuan', $bantuan]] as $key => [$label, $items])
                @if($key === 'bantuan')
                    <a href="{{ route('pricing') }}" class="block border-b border-slate-100 py-3.5 text-[16px] font-semibold text-[#0B1B3F]">Harga</a>
                    <a href="{{ route('pricing.bundles') }}" class="block border-b border-slate-100 py-3.5 text-[16px] font-semibold text-[#0B1B3F]">Bundling</a>
                @endif
                <div class="border-b border-slate-100">
                    <button type="button" @click="mobileGroup = mobileGroup === '{{ $key }}' ? null : '{{ $key }}'" class="flex w-full items-center justify-between py-3.5 text-[16px] font-semibold text-[#0B1B3F]">
                        {{ $label }}
                        <svg class="h-4 w-4 transition-transform" :class="mobileGroup === '{{ $key }}' ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="mobileGroup === '{{ $key }}'" x-cloak class="pb-3">
                        @foreach($items as $item)
                            <a href="{{ $item[0] }}" @click="mobileOpen = false" @if($item[3] ?? false) target="_blank" rel="noopener" @endif class="block rounded-lg px-3 py-2.5 text-[15px] text-slate-700 hover:bg-[#F3F6F4]">{{ $item[1] }}</a>
                        @endforeach
                        @if($key === 'produk')<a href="{{ $home }}#keunggulan" @click="mobileOpen = false" class="block rounded-lg px-3 py-2.5 text-[15px] text-slate-700 hover:bg-[#F3F6F4]">Keunggulan Yoka</a>@endif
                    </div>
                </div>
            @endforeach
            <div class="mt-5 grid grid-cols-2 gap-3">
                <a href="{{ url('/login') }}" class="rounded-full border border-slate-300 py-3 text-center text-[15px] font-semibold text-[#0B1B3F]">Masuk</a>
                <a href="{{ route('register') }}" class="rounded-full bg-[#16A34A] py-3 text-center text-[15px] font-semibold text-white">Coba gratis</a>
            </div>
            <div class="mt-5 flex items-center justify-center gap-2 text-sm font-semibold text-slate-500">
                @foreach(['id' => 'Bahasa Indonesia', 'en' => 'English'] as $code => $name)
                    @if(! $loop->first)<span aria-hidden="true">|</span>@endif
                    <a href="{{ route('lang.switch', $code) }}" class="{{ $locale === $code ? 'text-[#0B1B3F]' : 'hover:text-[#0B1B3F]' }}">{{ $name }}</a>
                @endforeach
            </div>
        </div>
    </div>
</header>
