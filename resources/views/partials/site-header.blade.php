{{-- Header situs bersama (landing, panduan). $home = '' di landing, route('landing') di halaman lain. --}}
@php $home = $home ?? ''; $waLink = $waLink ?? 'https://wa.me/6285891111795'; @endphp
    <header
        x-data="{ scrolled: false, featureOpen: false, mobileFeatures: false, mobileOpen: false }"
        x-init="scrolled = window.scrollY > 40; window.addEventListener('scroll', () => scrolled = window.scrollY > 40, { passive: true })"
        :class="scrolled || mobileOpen || featureOpen ? 'bg-white shadow-[0_2px_16px_rgba(15,23,42,.08)]' : 'bg-transparent'"
        class="fixed top-0 inset-x-0 z-40 transition-[background-color,box-shadow] duration-300 will-change-transform [transform:translate3d(0,0,0)] [backface-visibility:hidden] pt-[env(safe-area-inset-top)]"
    >
        <div class="max-w-[1200px] mx-auto px-5 sm:px-6 h-[60px] sm:h-[68px] lg:h-[76px] flex items-center">
            {{-- Logo: putih di atas hero, hijau setelah scroll --}}
            <a href="{{ $home ?: '#' }}" class="flex items-center gap-2 shrink-0" aria-label="Yoka POS">
                <span class="relative block h-7 w-[78px] sm:h-8 sm:w-[88px] lg:h-9 lg:w-[100px]">
                    <img src="{{ asset('images/brand/yoka-logo-white.png') }}" alt="Yoka" class="absolute inset-0 h-full w-full object-contain object-left transition-opacity duration-300" :class="scrolled || mobileOpen || featureOpen ? 'opacity-0' : 'opacity-100'">
                    <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="" class="absolute inset-0 h-full w-full object-contain object-left transition-opacity duration-300" :class="scrolled || mobileOpen || featureOpen ? 'opacity-100' : 'opacity-0'">
                </span>
                <span class="text-[17px] sm:text-[19px] lg:text-[22px] leading-none font-bold tracking-tight transition-colors duration-300" :class="scrolled || mobileOpen || featureOpen ? 'text-emerald-700' : 'text-white'">POS</span>
            </a>

            <nav class="hidden lg:flex items-center gap-9 ml-12 text-[15px] font-bold transition-colors duration-300" :class="scrolled || featureOpen ? 'text-slate-800' : 'text-white'">
                <div class="relative" @mouseenter="featureOpen = true" @mouseleave="featureOpen = false" @click.outside="featureOpen = false">
                    <button type="button" @click="featureOpen = !featureOpen"
                            class="h-[76px] inline-flex items-center gap-1.5 hover:opacity-70 transition-opacity"
                            :aria-expanded="featureOpen" aria-haspopup="true">
                        Fitur
                        <svg class="w-4 h-4 transition-transform duration-200" :class="featureOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>

                    <div x-show="featureOpen" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-2"
                         class="absolute top-[68px] -left-28 w-[920px] rounded-[26px] border border-slate-200/80 bg-white p-7 text-slate-800 shadow-[0_28px_70px_rgba(15,23,42,.20)]">
                        <span class="absolute -top-2 left-[132px] w-4 h-4 rotate-45 border-l border-t border-slate-200 bg-white"></span>
                        <div class="grid grid-cols-[1fr_1fr_1.15fr] gap-7">
                            <div>
                                <p class="mb-3 text-[12px] font-extrabold uppercase tracking-[.14em] text-slate-400">Kasir &amp; Pembayaran</p>
                                <div class="space-y-1">
                                    @foreach ([
                                        [route('features.show', 'transaksi-kasir'), 'Transaksi Kasir', 'Proses pesanan lebih cepat'],
                                        [route('features.show', 'pembayaran-qris'), 'Pembayaran & QRIS', 'Tunai, QRIS, dan split payment'],
                                        [route('features.show', 'hold-order'), 'Hold Order', 'Simpan pesanan dan nomor meja'],
                                        [route('features.show', 'struk-printer'), 'Struk & Printer', 'Browser, PDF, Bluetooth, RawBT'],
                                    ] as [$href, $title, $desc])
                                        <a href="{{ $href }}" @click="featureOpen = false" class="group block rounded-xl px-3 py-2.5 hover:bg-emerald-50 transition-colors">
                                            <span class="block text-sm font-bold text-slate-800 group-hover:text-emerald-700">{{ $title }}</span>
                                            <span class="mt-0.5 block text-[12px] font-medium text-slate-400">{{ $desc }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <p class="mb-3 text-[12px] font-extrabold uppercase tracking-[.14em] text-slate-400">Operasional Bisnis</p>
                                <div class="space-y-1">
                                    @foreach ([
                                        [route('features.show', 'stok-resep'), 'Stok & Resep', 'Stok bahan berkurang otomatis'],
                                        [route('features.show', 'kitchen-display'), 'Kitchen Display', 'Pesanan dapur lebih teratur'],
                                        [route('features.show', 'shift-karyawan'), 'Shift & Karyawan', 'Rekap kas dan akses setiap role'],
                                        [route('features.show', 'pelanggan-promosi'), 'Pelanggan & Promosi', 'Loyalty point, bundle, dan promo'],
                                    ] as [$href, $title, $desc])
                                        <a href="{{ $href }}" @click="featureOpen = false" class="group block rounded-xl px-3 py-2.5 hover:bg-emerald-50 transition-colors">
                                            <span class="block text-sm font-bold text-slate-800 group-hover:text-emerald-700">{{ $title }}</span>
                                            <span class="mt-0.5 block text-[12px] font-medium text-slate-400">{{ $desc }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>

                            <div class="rounded-2xl bg-emerald-700 p-5 text-white">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 3-7.5 18-4.3-7.7L3 9.5 21 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.2 13.3 21 3"/></svg>
                                    </span>
                                    <span class="rounded-full bg-amber-300 px-2.5 py-1 text-[12px] font-extrabold uppercase tracking-wider text-amber-950">Unggulan</span>
                                </div>
                                <p class="mt-5 text-lg font-extrabold">Laporan &amp; Insight</p>
                                <p class="mt-2 text-xs font-medium leading-relaxed text-emerald-50/85">Tanya omzet, profit, stok, produk terlaris, dan dapatkan insight langsung dari data tokomu.</p>
                                <a href="{{ route('features.show', 'laporan') }}" @click="featureOpen = false" class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-white hover:gap-3 transition-all">
                                    Lihat cara kerjanya
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                                </a>
                                <a href="{{ route('features.show', 'laporan') }}" @click="featureOpen = false" class="mt-4 block border-t border-white/15 pt-4 text-xs font-bold text-emerald-50 hover:text-white">Laporan penjualan, profit &amp; stok →</a>
                            </div>
                        </div>
                    </div>
                </div>
                <a href="{{ $home }}#keunggulan" class="hover:opacity-70 transition-opacity">Keunggulan</a>
                <a href="{{ route('guides.index') }}" class="hover:opacity-70 transition-opacity">Panduan</a>
                <a href="{{ $home }}#harga" class="hover:opacity-70 transition-opacity">Paket Harga</a>
                <a href="{{ $home }}#faq" class="hover:opacity-70 transition-opacity">FAQ</a>
            </nav>

            <div class="hidden lg:flex items-center gap-7 ml-auto">
                <x-lang-switch variant="light" />
                <a href="{{ url('/login') }}" class="text-[15px] font-bold transition-colors duration-300" :class="scrolled || featureOpen ? 'text-slate-800' : 'text-white'">Masuk</a>
                <a href="{{ url('/login') }}"
                   class="px-7 py-3 rounded-full text-[15px] font-bold transition-colors duration-300"
                   :class="scrolled || featureOpen ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-white text-emerald-700 hover:bg-emerald-50'">
                    Coba Gratis
                </a>
            </div>

            {{-- Hamburger --}}
            <button @click="mobileOpen = !mobileOpen" class="lg:hidden ml-auto -mr-2 w-11 h-11 flex items-center justify-center rounded-lg transition-colors" :class="scrolled || mobileOpen ? 'text-slate-800' : 'text-white'" aria-label="Menu">
                <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        {{-- Mobile menu --}}
        <div x-show="mobileOpen" x-cloak x-transition class="lg:hidden border-t border-slate-200 bg-white px-6 py-4 space-y-3">
            <button type="button" @click="mobileFeatures = !mobileFeatures" class="w-full flex items-center justify-between py-1 text-sm font-bold text-slate-800" :aria-expanded="mobileFeatures">
                Fitur
                <svg class="w-4 h-4 transition-transform" :class="mobileFeatures ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
            </button>
            <div x-show="mobileFeatures" x-cloak x-transition class="rounded-2xl bg-slate-50 p-3 space-y-1">
                @foreach ([
                    [route('features.show', 'transaksi-kasir'), 'Transaksi Kasir'],
                    [route('features.show', 'pembayaran-qris'), 'Pembayaran & QRIS'],
                    [route('features.show', 'stok-resep'), 'Stok & Resep'],
                    [route('features.show', 'kitchen-display'), 'Kitchen Display'],
                    [route('features.show', 'laporan'), 'Laporan Penjualan & Profit'],
                ] as [$href, $label])
                    <a @click="mobileOpen=false; mobileFeatures=false" href="{{ $href }}"
                       class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white">
                        {{ $label }}
                        <span aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
            <a @click="mobileOpen=false" href="{{ $home }}#keunggulan" class="block text-sm font-medium text-slate-700">Keunggulan</a>
            <a href="{{ route('guides.index') }}" class="block text-sm font-medium text-slate-700">Panduan</a>
            <a @click="mobileOpen=false" href="{{ $home }}#harga" class="block text-sm font-medium text-slate-700">Paket Harga</a>
            <a @click="mobileOpen=false" href="{{ $home }}#faq" class="block text-sm font-medium text-slate-700">FAQ</a>
            <x-lang-switch variant="light" />
            <a href="{{ $waLink }}" target="_blank"
               class="block text-center px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold">Coba Demo Sekarang</a>
        </div>
    </header>
