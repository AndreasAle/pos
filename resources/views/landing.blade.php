<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Yoka POS — Kasir Modern untuk Cafe, Resto, Bakery & Bisnis F&B</title>
    <meta name="description" content="Kelola transaksi, QRIS, stok, shift kasir, laporan profit, dan insight bisnis dalam satu sistem POS untuk cafe, resto, bakery, dan UMKM.">


    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
        .gradient-text {
            background: linear-gradient(90deg, #059669, #10b981);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .blob {
            position: absolute;
            border-radius: 9999px;
            filter: blur(80px);
            opacity: .35;
            z-index: 0;
        }
        .fade-up { animation: fadeUp .6s ease both; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .hover-card { transition: transform .25s ease, box-shadow .25s ease; }
        .hover-card:hover { transform: translateY(-4px); box-shadow: 0 20px 45px -15px rgba(5,150,105,.25); }
        .testimonial-track { scrollbar-width: none; }
        .testimonial-track::-webkit-scrollbar { display: none; }
        .ai-demo-enter { animation: aiDemoEnter .45s cubic-bezier(.2,.75,.3,1) both; }
        .ai-demo-cursor::after { content: ''; display: inline-block; width: 2px; height: 1em; margin-left: 2px; vertical-align: -.12em; background: #059669; animation: aiCursor .8s steps(1) infinite; }
        .ai-demo-shimmer { background: linear-gradient(90deg,#f1f5f9 25%,#dcfce7 45%,#f1f5f9 65%); background-size: 240% 100%; animation: aiShimmer 1.5s linear infinite; }
        @keyframes aiDemoEnter { from { opacity: 0; transform: translateY(9px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes aiCursor { 50% { opacity: 0; } }
        @keyframes aiShimmer { to { background-position: -240% 0; } }
        @media (prefers-reduced-motion: reduce) {
            .ai-demo-enter, .ai-demo-cursor::after, .ai-demo-shimmer { animation: none !important; }
        }
    </style>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
</head>
<body class="bg-white text-slate-800 antialiased" x-data="{ mobileOpen: false }">

    {{-- ================= NAVBAR ================= --}}
    @php $waLink = 'https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka'; @endphp
    <header
        x-data="{ scrolled: false, featureOpen: false, mobileFeatures: false }"
        x-init="scrolled = window.scrollY > 40; window.addEventListener('scroll', () => scrolled = window.scrollY > 40, { passive: true })"
        :class="scrolled || mobileOpen || featureOpen ? 'bg-white shadow-[0_2px_16px_rgba(15,23,42,.08)]' : 'bg-transparent'"
        class="fixed top-0 inset-x-0 z-40 transition-[background-color,box-shadow] duration-300 will-change-transform [transform:translate3d(0,0,0)] [backface-visibility:hidden] pt-[env(safe-area-inset-top)]"
    >
        <div class="max-w-[1200px] mx-auto px-5 sm:px-6 h-[60px] sm:h-[68px] lg:h-[76px] flex items-center">
            {{-- Logo: putih di atas hero, hijau setelah scroll --}}
            <a href="#" class="flex items-center gap-2 shrink-0" aria-label="Yoka POS">
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
                <a href="#keunggulan" class="hover:opacity-70 transition-opacity">Keunggulan</a>
                <a href="#harga" class="hover:opacity-70 transition-opacity">Paket Harga</a>
                <a href="#faq" class="hover:opacity-70 transition-opacity">FAQ</a>
            </nav>

            <div class="hidden lg:flex items-center gap-7 ml-auto">
                <x-lang-switch variant="light" />
                <a href="{{ url('/login') }}" class="text-[15px] font-bold transition-colors duration-300" :class="scrolled || featureOpen ? 'text-slate-800' : 'text-white'">Masuk</a>
                <a href="{{ $waLink }}" target="_blank"
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
            <a @click="mobileOpen=false" href="#keunggulan" class="block text-sm font-medium text-slate-700">Keunggulan</a>
            <a @click="mobileOpen=false" href="#harga" class="block text-sm font-medium text-slate-700">Paket Harga</a>
            <a @click="mobileOpen=false" href="#faq" class="block text-sm font-medium text-slate-700">FAQ</a>
            <x-lang-switch variant="light" />
            <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka" target="_blank"
               class="block text-center px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold">Coba Demo Sekarang</a>
        </div>
    </header>

    <style>
        /* Slide 3: pudar ke atas di mobile/tablet, ke kiri di desktop. */
        .hero-fade-y { -webkit-mask-image: linear-gradient(to bottom, transparent 0, #000 28%); mask-image: linear-gradient(to bottom, transparent 0, #000 28%); }
        @media (min-width: 1024px) { .hero-fade-y { -webkit-mask-image: linear-gradient(to right, transparent 0, #000 var(--hero-mask-x, 28%)); mask-image: linear-gradient(to right, transparent 0, #000 var(--hero-mask-x, 28%)); } }
    </style>
    {{-- ================= HERO SLIDER ================= --}}
    @php
        $slides = [
            [
                'bg' => '#01a05b',
                'img' => asset('images/hero/slide-kasir.webp'),
                'imgClass' => 'right-[-30%] bottom-6 w-[150%] sm:right-[-10%] sm:w-[110%] md:right-0 md:w-full lg:top-[52%] lg:bottom-auto lg:-translate-y-1/2 lg:w-[72%] max-w-none',
                'title' => 'Kasir Cepat, Struk Langsung Keluar',
                'text' => 'Pilih menu, hitung, bayar, cetak. Untuk kafe, resto, dan bakery yang tidak mau antrean menumpuk.',
                'cta' => 'Coba Gratis',
                'href' => $waLink,
                'alt' => 'Tablet kasir Yoka POS dan printer struk',
            ],
            [
                'bg' => '#0c874e',
                'img' => asset('images/hero/slide-owner.jpg'),
                'imgClass' => 'right-0 bottom-0 w-[150%] sm:w-[120%] lg:w-full max-w-none',
                'title' => 'Pantau Toko dari Mana Saja',
                'text' => 'Penjualan, stok, dan shift kasir bisa dicek dari HP, tanpa harus menunggu laporan akhir hari.',
                'cta' => 'Jadwalkan Demo',
                'href' => $waLink,
                'alt' => 'Pemilik toko memantau penjualan dari HP di samping kasir Yoka POS',
            ],
            [
                'bg' => '#075f40',
                'img' => asset('images/about/dashboard-tim.webp'),
                // Di bawah lg foto hanya mengisi bagian bawah slide (tidak menimpa teks) dan memudar ke atas; di lg tetap di kanan.
                'imgClass' => 'inset-x-0 bottom-0 w-full h-[40%] md:h-[42%] object-cover object-[68%_35%] hero-fade-y lg:inset-x-auto lg:right-0 lg:h-auto lg:w-[72%] lg:object-fill max-w-none',
                'maskStop' => '28%',
                'title' => 'Satu Sistem untuk Seluruh Operasional',
                'text' => 'Transaksi, stok, pelanggan, shift, dan laporan terhubung dalam satu sistem yang siap dipakai seluruh tim.',
                'cta' => 'Jelajahi Fitur',
                'href' => route('features.show', 'transaksi-kasir'),
                'alt' => 'Tim Yoka POS memantau operasional toko melalui dashboard di laptop',
            ],
        ];
    @endphp
    <section
        x-data="{ i: 0, n: {{ count($slides) }}, t: null, hover: false,
            go(k) { this.i = (k + this.n) % this.n },
            start() { this.t = setInterval(() => { if (!this.hover) this.go(this.i + 1) }, 6500) } }"
        x-init="start()"
        @mouseenter="hover = true" @mouseleave="hover = false"
        class="relative overflow-hidden h-[700px] md:h-[780px] lg:h-[720px] transition-colors duration-700"
        :style="'background-color:' + ['{{ $slides[0]['bg'] }}','{{ $slides[1]['bg'] }}','{{ $slides[2]['bg'] }}'][i]"
        aria-roledescription="carousel" aria-label="Yoka POS"
    >
        @foreach ($slides as $k => $s)
            <div class="absolute inset-0 transition-opacity duration-700"
                 :class="i === {{ $k }} ? 'opacity-100 z-10' : 'opacity-0 pointer-events-none'"
                 :aria-hidden="i !== {{ $k }}">
                <img src="{{ $s['img'] }}" alt="{{ $s['alt'] }}" class="absolute select-none {{ $s['imgClass'] }}"
                     @unless (str_contains($s['imgClass'], 'hero-fade-y')) style="-webkit-mask-image:linear-gradient(to right,transparent 0,#000 {{ $s['maskStop'] ?? '8%' }});mask-image:linear-gradient(to right,transparent 0,#000 {{ $s['maskStop'] ?? '8%' }})" @endunless
                     @if (str_contains($s['imgClass'], 'hero-fade-y')) style="--hero-mask-x: {{ $s['maskStop'] ?? '8%' }}" @endif
                     @if ($k === 0) fetchpriority="high" @else loading="lazy" @endif>
                <div class="relative max-w-[1200px] mx-auto px-6 h-full flex items-start lg:items-center pt-[88px] sm:pt-[100px] lg:pt-0">
                    <div class="max-w-[520px] lg:pb-8">
                        <h1 class="text-[34px] md:text-[48px] font-extrabold leading-[1.15] text-white mb-5">{{ $s['title'] }}</h1>
                        <p class="text-base md:text-lg text-white/90 mb-8 max-w-[440px]">{{ $s['text'] }}</p>
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ $s['href'] }}" @if (str_starts_with($s['href'], 'https://wa.me/')) target="_blank" @endif class="px-5 sm:px-7 py-3 sm:py-3.5 rounded-full bg-white text-emerald-700 font-bold text-[15px] sm:text-base hover:bg-emerald-50 transition-colors">{{ $s['cta'] }}</a>
                            <a href="{{ $waLink }}" target="_blank" class="px-5 sm:px-7 py-3 sm:py-3.5 rounded-full border border-white/70 text-white font-bold text-[15px] sm:text-base hover:bg-white/10 transition-colors">WhatsApp Kami</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Panah --}}
        <button type="button" @click="go(i - 1)" aria-label="Sebelumnya"
                class="hidden md:flex absolute z-20 left-5 bottom-8 w-14 h-14 rounded-full bg-white text-emerald-700 items-center justify-center shadow-lg hover:scale-105 transition-transform">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m6-6-6 6 6 6"/></svg>
        </button>
        <button type="button" @click="go(i + 1)" aria-label="Berikutnya"
                class="hidden md:flex absolute z-20 right-5 bottom-8 w-14 h-14 rounded-full bg-white text-emerald-700 items-center justify-center shadow-lg hover:scale-105 transition-transform">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </button>

        {{-- Titik --}}
        <div class="absolute z-20 bottom-8 left-1/2 -translate-x-1/2 flex items-center gap-3">
            @foreach ($slides as $k => $s)
                <button type="button" @click="go({{ $k }})" aria-label="Slide {{ $k + 1 }}"
                        class="w-2.5 h-2.5 rounded-full transition-colors"
                        :class="i === {{ $k }} ? 'bg-white' : 'bg-white/45 hover:bg-white/70'"></button>
            @endforeach
        </div>
    </section>

    {{-- ================= BUMPER (video penuh lebar, tanpa suara) ================= --}}
    <section aria-label="Video perkenalan Yoka POS" class="relative w-full bg-white pt-14 md:pt-20 pb-14 md:pb-20">
        <div class="px-6 text-center mb-8 md:mb-10">
            <p class="text-sm font-bold tracking-wide text-emerald-600 uppercase">Sekilas Yoka POS</p>
            <h2 class="mt-2 text-[28px] md:text-[38px] font-extrabold leading-[1.15] text-emerald-800">Satu Aplikasi, Seluruh Toko Terkendali</h2>
        </div>
        <video id="yoka-bumper" class="block w-full h-auto aspect-video object-cover"
               autoplay muted loop playsinline preload="metadata" disablepictureinpicture controlslist="nodownload noplaybackrate"
               poster="{{ asset('images/video/yoka-bumper-poster.jpg') }}" aria-hidden="true" tabindex="-1">
            <source src="{{ asset('images/video/yoka-bumper.mp4') }}" type="video/mp4">
        </video>
        <script>
            (function () {
                var v = document.getElementById('yoka-bumper');
                if (!v) return;
                v.muted = true;
                // Hormati pengguna yang meminta gerakan dikurangi: tampilkan poster saja.
                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { v.removeAttribute('autoplay'); v.pause(); return; }
                // Hemat baterai/data: berhenti saat di luar layar.
                if ('IntersectionObserver' in window) {
                    new IntersectionObserver(function (e) { e[0].isIntersecting ? v.play().catch(function () {}) : v.pause(); }, { threshold: 0.15 }).observe(v);
                }
            })();
        </script>
    </section>

    {{-- ================= TENTANG KAMI ================= --}}
    <section id="tentang" class="py-20 md:py-28 px-6 bg-white">
        <div class="max-w-[1100px] mx-auto grid lg:grid-cols-2 gap-14 lg:gap-20 items-center">

            {{-- Kolase foto --}}
            <div class="relative mx-auto w-full max-w-[520px] h-[420px] sm:h-[500px]">
                <img src="{{ asset('images/about/barista.jpg') }}" alt="Barista tersenyum melayani pelanggan di kafe"
                     class="absolute left-0 top-0 w-[44%] h-[52%] object-cover object-top rounded-2xl shadow-[0_14px_30px_rgba(15,23,42,.22)]" loading="lazy">
                <img src="{{ asset('images/about/layar.jpg') }}" alt="Layar kasir Yoka POS berisi menu kopi"
                     class="absolute right-0 top-[6%] w-[52%] h-[38%] object-cover rounded-2xl shadow-[0_14px_30px_rgba(15,23,42,.22)]" loading="lazy">
                <img src="{{ asset('images/about/printer.jpg') }}" alt="Pemilik toko melihat analitik penjualan di tablet"
                     class="absolute left-[-2%] bottom-0 w-[50%] h-[40%] object-cover rounded-2xl shadow-[0_14px_30px_rgba(15,23,42,.22)]" loading="lazy">
                <img src="{{ asset('images/about/kafe.jpg') }}" alt="Penjaga toko kelontong memakai HP untuk mencatat penjualan"
                     class="absolute right-[2%] bottom-[2%] w-[44%] h-[52%] object-cover rounded-2xl shadow-[0_14px_30px_rgba(15,23,42,.22)]" loading="lazy">
            </div>

            {{-- Teks --}}
            <div class="text-center lg:text-left">
                <h2 class="text-[34px] md:text-[42px] font-extrabold leading-[1.15] text-emerald-800">Masih Baru, Tapi Sudah Dipakai Berjualan</h2>
                <p class="mt-5 text-[17px] leading-relaxed text-slate-700">
                    Yoka POS baru hadir. Kami belum punya ribuan pelanggan, tapi aplikasi ini sudah dipakai berjualan setiap hari,
                    dan kami terus memperbaikinya dari masukan kasir dan pemilik toko yang memakainya.
                </p>

                <div class="mt-9 grid grid-cols-3 gap-4 text-center">
                    @foreach ([
                        ['7 Hari', 'Coba gratis', 'M12 8v4l3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['1 Aplikasi', 'Kasir, stok, laporan', 'M4 6h16M4 12h16M4 18h10'],
                        ['QRIS', 'Bayar langsung tercatat', 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h2v2h-2v-2zm4 0h2v6h-6v-2h4v-4z'],
                    ] as [$big, $small, $path])
                        <div>
                            <svg class="w-8 h-8 mx-auto text-emerald-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                            <p class="mt-3 text-xl font-bold text-emerald-800">{{ $big }}</p>
                            <p class="text-sm text-slate-500 mt-0.5">{{ $small }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ================= YOKA IN ACTION ================= --}}
    @php
        $yokaStories = [
            [
                'label' => 'Antrean lancar',
                'eyebrow' => 'Kasir yang siap saat ramai',
                'title' => 'Lebih banyak pesanan,',
                'accent' => 'tetap terkendali',
                'description' => 'Saat pelanggan berdatangan bersamaan, alur pesanan tetap rapi. Kasir bisa fokus melayani tanpa sibuk menghitung atau mencatat ulang.',
                'video' => 'images/videos/landing/yoka-antrean-kafe.mp4',
                'position' => 'center',
            ],
            [
                'label' => 'Layanan cepat',
                'eyebrow' => 'Dari pembayaran sampai struk',
                'title' => 'Satu transaksi selesai,',
                'accent' => 'pelanggan langsung dilayani',
                'description' => 'Tunai atau QRIS tercatat dalam satu alur. Kembalian dihitung otomatis dan struk siap dicetak atau dibagikan sebagai PDF.',
                'video' => 'images/videos/landing/yoka-pelayanan-kasir.mp4',
                'position' => 'center',
            ],
            [
                'label' => 'Tim terhubung',
                'eyebrow' => 'Operasional dalam satu sistem',
                'title' => 'Semua tim bergerak,',
                'accent' => 'data tetap satu',
                'description' => 'Kasir, dapur, stok, dan pemilik bekerja dari informasi yang sama. Aktivitas toko bisa dipantau tanpa menunggu rekap akhir hari.',
                'video' => 'images/videos/landing/yoka-operasional-tim.mp4',
                'position' => 'center',
            ],
        ];
    @endphp
    <section id="kasir" class="overflow-hidden bg-[#F5F8F6] px-5 py-20 sm:px-6 md:py-28"
             x-data="{
                active: 0,
                count: {{ count($yokaStories) }},
                timer: null,
                go(index) {
                    this.active = (index + this.count) % this.count;
                    this.$nextTick(() => this.playActive());
                },
                playActive() {
                    this.$root.querySelectorAll('video').forEach((video, index) => {
                        if (index === this.active) { video.currentTime = 0; video.play().catch(() => {}); }
                        else { video.pause(); }
                    });
                },
                start() {
                    this.playActive();
                    this.timer = setInterval(() => this.go(this.active + 1), 7000);
                },
                restart() {
                    clearInterval(this.timer);
                    this.timer = setInterval(() => this.go(this.active + 1), 7000);
                }
             }"
             x-init="start()" @mouseenter="clearInterval(timer)" @mouseleave="restart()">
        <div class="mx-auto max-w-[1180px]">
            <div class="flex flex-col gap-7 md:flex-row md:items-end md:justify-between">
                <div class="max-w-[760px]">
                    <p class="text-sm font-bold uppercase tracking-[.13em] text-emerald-700">Yoka POS dalam keseharian</p>
                    <h2 class="mt-3 text-[36px] font-extrabold leading-[1.08] tracking-[-.035em] text-slate-950 md:text-[54px]">Saat kerja cepat<br class="hidden sm:block"> berubah jadi hasil</h2>
                    <p class="mt-4 max-w-[670px] text-base leading-relaxed text-slate-600 md:text-[18px]">Dari antrean pertama sampai toko tutup, Yoka menjaga transaksi dan operasional tetap bergerak dalam satu alur.</p>
                </div>
                <a href="{{ route('register') }}" class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-600 px-6 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700">
                    Coba Yoka gratis
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
            </div>

            <div class="mt-10 flex flex-wrap items-center gap-x-7 gap-y-3 border-y border-emerald-950/10 py-4 md:mt-12">
                @foreach ($yokaStories as $k => $story)
                    <button type="button" @click="go({{ $k }}); restart()" class="group inline-flex items-center gap-2.5 text-sm font-semibold transition-colors"
                            :class="active === {{ $k }} ? 'text-emerald-700' : 'text-slate-400 hover:text-slate-700'">
                        <span class="h-2 w-2 rounded-full transition-all" :class="active === {{ $k }} ? 'scale-100 bg-emerald-600' : 'scale-75 bg-slate-300'"></span>
                        {{ $story['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="mt-7 flex h-[360px] gap-2.5 sm:h-[430px] md:h-[520px] md:gap-3" aria-live="polite">
                @foreach ($yokaStories as $k => $story)
                    <button type="button" @click="go({{ $k }}); restart()"
                            class="group relative min-w-0 overflow-hidden rounded-[22px] border text-left shadow-[0_18px_45px_rgba(15,23,42,.12)] transition-[flex,filter,border-color] duration-700 ease-[cubic-bezier(.2,.75,.25,1)] md:rounded-[28px]"
                            :class="[active === {{ $k }} ? 'border-emerald-500' : 'border-white/60 grayscale-[.2] hover:grayscale-0', active !== {{ $k }} ? 'max-md:hidden' : '']"
                            :style="active === {{ $k }} ? 'flex: 1 1 72%;' : 'flex: 0 1 14%;'"
                            :aria-label="active === {{ $k }} ? '{{ $story['label'] }}, sedang aktif' : 'Tampilkan {{ $story['label'] }}'">
                        <video muted loop playsinline preload="metadata" class="absolute inset-0 h-full w-full object-cover" style="object-position: {{ $story['position'] }}">
                            <source src="{{ asset($story['video']) }}" type="video/mp4">
                        </video>
                        <div class="absolute inset-0 bg-gradient-to-t transition-opacity duration-500"
                             :class="active === {{ $k }} ? 'from-slate-950/85 via-slate-950/5 to-transparent opacity-100' : 'from-slate-950/65 via-transparent to-transparent opacity-80'"></div>

                        <div x-show="active === {{ $k }}" x-transition.opacity.duration.500ms class="absolute inset-x-0 bottom-0 p-6 text-white sm:p-8 md:p-10">
                            <p class="text-xs font-bold uppercase tracking-[.14em] text-emerald-300 sm:text-sm">{{ $story['eyebrow'] }}</p>
                            <h3 class="mt-2 max-w-[760px] text-[29px] font-extrabold leading-[1.06] tracking-[-.035em] sm:text-[40px] md:text-[52px]">
                                {{ $story['title'] }}<br><span class="text-emerald-300">{{ $story['accent'] }}</span>
                            </h3>
                        </div>

                        <div x-show="active !== {{ $k }}" class="absolute inset-x-0 bottom-0 hidden p-4 md:block">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-xs font-extrabold text-emerald-800 shadow">0{{ $k + 1 }}</span>
                        </div>
                    </button>
                @endforeach
            </div>

            <div class="mt-7 grid items-start gap-6 md:grid-cols-[1fr_auto] md:gap-10">
                <div class="relative min-h-[92px] max-w-[820px]">
                    @foreach ($yokaStories as $k => $story)
                        <div x-show="active === {{ $k }}" x-cloak x-transition.opacity.duration.300ms>
                            <p class="text-[18px] font-semibold leading-relaxed text-slate-800 md:text-[21px]">“{{ $story['description'] }}”</p>
                            <p class="mt-3 text-xs font-bold uppercase tracking-[.12em] text-emerald-700">Yoka POS · {{ $story['label'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" @click="go(active - 1); restart()" class="flex h-12 w-12 items-center justify-center rounded-full border border-emerald-600 text-emerald-700 transition hover:bg-emerald-600 hover:text-white" aria-label="Cerita sebelumnya">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m6-6-6 6 6 6"/></svg>
                    </button>
                    <button type="button" @click="go(active + 1); restart()" class="flex h-12 w-12 items-center justify-center rounded-full border border-emerald-600 bg-emerald-600 text-white transition hover:bg-emerald-700" aria-label="Cerita berikutnya">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- ================= INTERACTIVE PRODUCT EXPERIENCE ================= --}}
    <section id="demo-kasir" class="overflow-hidden bg-white py-20 md:py-28" aria-labelledby="product-experience-title">
        <div class="w-full">
            <div class="mx-auto max-w-[780px] px-6 text-center">
                <h2 id="product-experience-title" class="mt-6 text-[34px] font-extrabold leading-[1.08] tracking-[-.035em] text-[#0B1B3F] sm:text-[44px] md:text-[56px]">Rasakan alur kasir Yoka,<br><span class="text-emerald-600">langsung dari browser.</span></h2>
            </div>
            <div class="mt-12 min-h-[580px] w-full overflow-hidden border-y border-slate-200 bg-[#2b241f] shadow-[0_30px_80px_rgba(15,23,42,.12)] sm:min-h-[680px]" data-yoka-product-experience data-signup-url="{{ route('register') }}">
                <div class="flex min-h-[580px] items-center justify-center text-sm font-bold text-slate-400 sm:min-h-[680px]" role="status">
                    <span class="mr-3 h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-400"></span>Menyiapkan pengalaman 3D…
                </div>
            </div>
            <noscript><p class="mt-4 text-center text-sm text-slate-300">Aktifkan JavaScript untuk mencoba demo kasir interaktif.</p></noscript>
        </div>
    </section>

    {{-- ================= INSIGHT BISNIS DEMO ================= --}}
    <section id="yoka-intelligence" class="relative overflow-hidden bg-white px-6 py-20 md:py-28">
        <div class="pointer-events-none absolute left-1/2 top-[46%] h-[520px] w-[900px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-emerald-100/55 blur-[100px]"></div>
        <div class="relative mx-auto max-w-[1120px]" x-data="yokaAiDemo()" x-init="observe($el)">
            <div class="mx-auto max-w-[850px] text-center">
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3.5 py-1.5 text-xs font-extrabold uppercase tracking-[.14em] text-emerald-700 ring-1 ring-emerald-100">
                    <span class="flex h-5 w-5 items-center justify-center rounded-md bg-emerald-600 text-white">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3z"/></svg>
                    </span>
                    Insight Bisnis
                </span>
                <h2 class="mt-6 text-[34px] font-extrabold leading-[1.12] tracking-[-.03em] text-slate-950 md:text-[50px]">
                    Data bisnis bicara lebih jelas,<br class="hidden sm:block"> keputusan jadi lebih cepat
                </h2>
            </div>

            <div class="mt-14 grid items-center gap-10 lg:grid-cols-[600px_1fr] lg:gap-14">
                {{-- Product demo --}}
                <div class="relative mx-auto w-full max-w-[600px]">
                    <div class="absolute -inset-5 rounded-[34px] bg-gradient-to-br from-emerald-200/55 via-sky-100/30 to-amber-100/50 blur-2xl"></div>
                    <div class="relative overflow-hidden rounded-[24px] border border-emerald-200 bg-white shadow-[0_28px_70px_rgba(15,23,42,.14)]">
                        <div class="flex h-12 items-center border-b border-slate-200 bg-slate-50/80 px-3">
                            <div class="flex items-center gap-1.5 pr-3"><span class="h-2.5 w-2.5 rounded-full bg-red-300"></span><span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span><span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span></div>
                            <template x-for="(item, index) in scenarios" :key="item.tab">
                                <button type="button" @click="select(index)" class="relative flex h-9 items-center gap-1.5 rounded-t-lg px-3 text-xs font-bold transition-colors"
                                        :class="active === index ? 'bg-white text-emerald-700 shadow-[0_-1px_0_#d1fae5,1px_0_0_#d1fae5,-1px_0_0_#d1fae5]' : 'text-slate-400 hover:text-slate-600'">
                                    <span x-text="item.icon"></span><span x-text="item.tab"></span>
                                </button>
                            </template>
                            <span class="ml-auto hidden items-center gap-1.5 text-[12px] font-semibold text-slate-400 sm:flex"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Data tersambung</span>
                        </div>

                        <div class="flex h-[500px] flex-col sm:h-[520px]">
                            <div class="flex h-12 shrink-0 items-center justify-between border-b border-slate-100 px-5">
                                <div class="flex items-center gap-2"><span class="flex h-6 w-6 items-center justify-center rounded-md bg-emerald-600 text-[12px] font-black text-white">Y</span><span class="text-xs font-bold text-slate-700" x-text="scenarios[active].mode"></span></div>
                                <span class="text-[12px] font-medium text-slate-400">{{ config('app.name') }}</span>
                            </div>

                            <div class="relative flex-1 overflow-hidden px-5 py-5 sm:px-7">
                                {{-- Empty/waiting state --}}
                                <div x-show="phase === 0" x-transition.opacity class="flex h-full flex-col items-center justify-center text-center">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 ring-8 ring-emerald-50/50">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3z"/></svg>
                                    </div>
                                    <p class="mt-5 text-sm font-bold text-slate-800">Tanyakan apa pun tentang toko</p>
                                    <p class="mt-1 max-w-[290px] text-xs leading-relaxed text-slate-400">Yoka akan memilih data yang tepat untuk menjawab pertanyaanmu.</p>
                                </div>

                                {{-- Analysis --}}
                                <div x-show="phase >= 2" class="space-y-4">
                                    <div class="ai-demo-enter rounded-2xl bg-[#E8F7EF] px-4 py-3 text-sm leading-relaxed text-[#0B1B3F]" x-text="scenarios[active].question"></div>
                                    <div class="ai-demo-enter flex items-center gap-2 text-xs font-bold text-emerald-700"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-600 text-white">Y</span><span x-text="phase < 4 ? 'Yoka sedang menganalisis...' : 'Analisis selesai'"></span></div>
                                    <div x-show="phase === 2" class="space-y-2.5">
                                        <template x-for="(line, index) in scenarios[active].analysis" :key="line">
                                            <div x-show="analysisStep > index" x-transition class="ai-demo-enter flex items-start gap-3 rounded-xl border border-slate-100 bg-white px-3.5 py-3 shadow-sm">
                                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-[12px] font-black text-emerald-600" x-text="index + 1"></span>
                                                <p class="text-xs leading-relaxed text-slate-600" x-text="line"></p>
                                            </div>
                                        </template>
                                        <div x-show="analysisStep < 3" class="h-11 rounded-xl ai-demo-shimmer"></div>
                                    </div>
                                    <div x-show="phase >= 3" x-transition class="ai-demo-enter overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm">
                                        <div class="border-b border-emerald-100 bg-emerald-50/70 px-4 py-2.5 text-[12px] font-extrabold uppercase tracking-wider text-emerald-700" x-text="scenarios[active].label"></div>
                                        <div class="p-4">
                                            <p class="text-[15px] font-extrabold leading-relaxed text-[#0B1B3F]" x-text="scenarios[active].answer"></p>
                                            <p class="mt-2 text-xs leading-relaxed text-slate-500" x-text="scenarios[active].detail"></p>
                                            <button x-show="phase >= 4" x-transition type="button" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm">Lihat detail <span>→</span></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="shrink-0 border-t border-slate-100 bg-white p-3.5 sm:p-4">
                                <div class="flex min-h-12 items-center gap-2 rounded-xl border px-3 transition-colors" :class="phase === 1 ? 'border-emerald-400 ring-4 ring-emerald-50' : 'border-slate-200'">
                                    <p class="min-w-0 flex-1 text-xs text-slate-700" :class="phase === 1 ? 'ai-demo-cursor' : ''" x-text="typed || 'Tanya kondisi bisnis kamu...' "></p>
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-colors" :class="typed ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-300'">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m-6 6l6-6 6 6"/></svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Explanation and synced progress --}}
                <div class="lg:pl-2">
                    <p class="text-sm font-extrabold uppercase tracking-[.14em] text-emerald-600">Dari data menjadi tindakan</p>
                    <h3 class="mt-3 text-[30px] font-extrabold leading-tight text-slate-950 md:text-[36px]">Bukan sekadar chatbot.<br>Yoka memahami operasionalmu.</h3>
                    <p class="mt-5 text-[15px] leading-relaxed text-slate-600">Semua jawaban dihitung dari transaksi, produk, stok, pelanggan, shift, dan outlet milik bisnismu sendiri.</p>

                    <ol class="mt-8 space-y-5">
                        <template x-for="(step, index) in steps" :key="step">
                            <li class="flex items-center gap-4 transition-all duration-500" :class="progress > index ? 'text-slate-900' : 'text-slate-400'">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 text-xs font-extrabold transition-all duration-500"
                                      :class="progress > index ? 'border-emerald-600 bg-emerald-600 text-white shadow-[0_5px_15px_rgba(5,150,105,.25)]' : 'border-slate-200 bg-white'">
                                    <svg x-show="progress > index" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
                                    <span x-show="progress <= index" x-text="index + 1"></span>
                                </span>
                                <span class="text-[15px] font-bold" x-text="step"></span>
                            </li>
                        </template>
                    </ol>

                    <a href="{{ route('register') }}" class="mt-9 inline-flex items-center gap-2 rounded-full bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-200 transition hover:-translate-y-0.5 hover:bg-emerald-700">Coba Yoka gratis <span>→</span></a>
                </div>
            </div>
        </div>
    </section>

    <script>
        function yokaAiDemo() {
            return {
                active: 0, phase: 0, analysisStep: 0, typed: '', progress: 0, running: false, timers: [],
                steps: ['Ajukan pertanyaan atau terima insight proaktif.', 'Yoka membaca data operasional yang relevan.', 'Dapatkan jawaban dan rekomendasi yang jelas.', 'Buka tindakan yang tepat dalam satu klik.'],
                scenarios: [
                    {tab:'Penjualan',icon:'↗',mode:'Pahami penjualan',question:'Bagaimana performa penjualan hari ini?',label:'Ringkasan penjualan',answer:'Omzet hari ini Rp4.860.000 dari 124 transaksi.',detail:'Naik 18% dibanding Kamis lalu. Puncak penjualan terjadi pukul 17.00–19.00.' ,analysis:['Membaca transaksi yang sudah dibayar hari ini.','Membandingkan dengan periode yang setara.','Mencari produk dan jam yang mendorong kenaikan.']},
                    {tab:'Produk',icon:'★',mode:'Optimalkan produk',question:'Produk mana yang paling laris dan menguntungkan?',label:'Performa produk',answer:'Es Kopi Susu memimpin dengan 86 gelas dan margin 54%.',detail:'Roti Bakar paling cepat tumbuh. Pertimbangkan bundling keduanya pada jam sore.',analysis:['Mengelompokkan item dari seluruh transaksi.','Menghitung omzet, biaya modal, dan margin.','Membandingkan pertumbuhan setiap menu.']},
                    {tab:'Stok',icon:'◫',mode:'Jaga ketersediaan stok',question:'Stok apa yang perlu saya beli besok?',label:'Prediksi stok',answer:'Susu diperkirakan habis dalam 2 hari.',detail:'Sisa 18 liter dengan penggunaan rata-rata 9,4 liter per hari. Saran pembelian: 30 liter.',analysis:['Membaca stok dan pergerakan bahan 14 hari.','Menghitung rata-rata pemakaian harian.','Memproyeksikan waktu habis dan kebutuhan belanja.']},
                ],
                later(fn, ms) { const id=setTimeout(fn,ms); this.timers.push(id); },
                clear() { this.timers.forEach(clearTimeout); this.timers=[]; },
                observe(el) { const start=()=>{if(!this.running){this.running=true;this.play()}}; if(!('IntersectionObserver' in window)){start();return} new IntersectionObserver((entries,observer)=>{if(entries[0].isIntersecting){start();observer.disconnect()}},{threshold:.25}).observe(el); },
                select(index) { this.active=index; this.running=true; this.play(); },
                play() {
                    this.clear(); this.phase=0; this.analysisStep=0; this.typed=''; this.progress=0;
                    const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    if(reduced){this.phase=4;this.analysisStep=3;this.typed=this.scenarios[this.active].question;this.progress=4;return}
                    this.later(()=>{this.phase=1;this.progress=1;this.typeQuestion(0)},650);
                },
                typeQuestion(index) {
                    const text=this.scenarios[this.active].question;
                    if(index<text.length){this.typed=text.slice(0,index+1);this.later(()=>this.typeQuestion(index+1),28);return}
                    this.later(()=>{this.phase=2;this.progress=2;this.analysisStep=1;this.later(()=>this.analysisStep=2,650);this.later(()=>this.analysisStep=3,1300);this.later(()=>{this.phase=3;this.progress=3},2200);this.later(()=>{this.phase=4;this.progress=4},3050);this.later(()=>{this.active=(this.active+1)%this.scenarios.length;this.play()},6500)},350);
                }
            }
        }
    </script>

    {{-- ================= SOLUSI ================= --}}
    <section id="keunggulan" class="py-20 md:py-28 px-6 bg-white">
        <div class="max-w-[1100px] mx-auto grid lg:grid-cols-[1fr_540px] gap-14 lg:gap-20 items-center">

            <div>
                <p class="text-[17px] font-bold text-slate-900">Dashboard Pemilik</p>
                <h2 class="mt-5 text-[34px] md:text-[44px] font-extrabold leading-[1.12] text-slate-900">
                    Pantau stok dan penjualan toko lebih cepat &amp; akurat dengan
                    <em class="font-black italic text-emerald-700">Laporan Otomatis</em>
                </h2>
                <p class="mt-7 text-[18px] leading-[1.7] text-slate-700 max-w-[470px]">
                    Lihat penjualan hari ini, produk terlaris, dan stok yang mulai menipis dalam satu layar,
                    tanpa rekap manual di akhir hari.
                </p>
                <a href="#harga" class="mt-9 inline-flex items-center gap-3 text-[18px] font-bold text-emerald-600 hover:text-emerald-700 transition-colors group">
                    Lihat Harga
                    <svg class="w-6 h-6 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
            </div>

            <div class="relative pt-8 pr-8">
                <div class="absolute top-0 right-0 left-14 bottom-14 rounded-[28px] bg-[#0c874e]"></div>
                <img src="{{ asset('images/about/dashboard-tim.webp') }}" alt="Dua staf toko melihat dashboard Yoka POS di laptop"
                     class="relative w-full rounded-[24px] shadow-[0_18px_40px_rgba(15,23,42,.25)]" loading="lazy">
            </div>
        </div>
    </section>

    {{-- ================= JENIS USAHA ================= --}}
    @php
        $segmen = [
            ['Restoran', 'images/photos/kafe.webp', 'Suasana restoran yang menggunakan Yoka POS'],
            ['Kafe & Bakery', 'images/about/barista.jpg', 'Barista melayani pelanggan kafe'],
            ['Kedai Kopi', 'images/about/layar.jpg', 'Layar kasir Yoka POS di kedai kopi'],
            ['Warung Makan', 'images/photos/kasir-pelanggan.webp', 'Pelayanan pelanggan di depan kasir'],
            ['Booth Minuman', 'images/menu/es-kopi-susu.jpg', 'Produk minuman untuk booth'],
            ['Food Truck', 'images/menu/beef-burger.jpg', 'Menu makanan untuk food truck'],
            ['Toko Kelontong', 'images/photos/toko-kelontong.webp', 'Penjaga toko kelontong menggunakan Yoka POS'],
            ['Toko Oleh-oleh', 'images/about/printer.jpg', 'Pengelolaan toko dengan Yoka POS'],
            ['Butik & Retail', 'images/photos/toko-online.webp', 'Pemilik butik memantau penjualan'],
            ['Salon & Barbershop', 'images/photos/toko-online.webp', 'Pemilik usaha memantau operasional'],
            ['Laundry', 'images/photos/toko-kelontong.webp', 'Pencatatan transaksi usaha dengan Yoka POS'],
            ['Multi-outlet', 'images/about/dashboard-tim.webp', 'Tim memantau banyak outlet melalui dashboard Yoka POS'],
        ];
    @endphp
    <section id="usaha" class="overflow-hidden bg-white px-5 py-24 md:px-8 md:py-36" x-data="{ active: null }" @mouseleave="active = null">
        <div class="mx-auto max-w-[1240px] text-center">
            <h2 class="text-[36px] font-extrabold leading-[1.08] tracking-[-.035em] text-slate-950 md:text-[58px]">
                Kami bantu bisnis tumbuh<br class="hidden sm:block"> dan bertahan lama
            </h2>
            <p class="mx-auto mt-5 max-w-[650px] text-base leading-relaxed text-slate-500 md:text-[18px]">
                Beragam fitur Yoka POS disiapkan untuk cara jualan dan melayani pelanggan yang berbeda-beda.
            </p>

            <div class="mt-28 flex flex-wrap items-center justify-center gap-2.5 sm:gap-3 md:mt-32" role="list" aria-label="Jenis usaha yang cocok menggunakan Yoka POS">
                @foreach ($segmen as $k => [$label, $image, $alt])
                    <div class="relative" role="listitem">
                        <div x-show="active === {{ $k }}" x-cloak
                             x-transition:enter="transition duration-300 ease-out"
                             x-transition:enter-start="opacity-0 translate-y-5 scale-75 rotate-0"
                             x-transition:enter-end="opacity-100 translate-y-0 scale-100 {{ $k % 2 === 0 ? '-rotate-6' : 'rotate-6' }}"
                             x-transition:leave="transition duration-200 ease-in"
                             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                             x-transition:leave-end="opacity-0 translate-y-3 scale-90"
                             class="pointer-events-none absolute bottom-[calc(100%+18px)] left-1/2 z-20 h-[145px] w-[125px] -translate-x-1/2 overflow-hidden rounded-[18px] border-[5px] border-white bg-emerald-100 shadow-[0_22px_45px_rgba(15,23,42,.25)] sm:h-[175px] sm:w-[150px]">
                            <img src="{{ asset($image) }}" alt="{{ $alt }}" class="h-full w-full object-cover" loading="lazy">
                        </div>
                        <button type="button"
                                @mouseenter="active = {{ $k }}" @focus="active = {{ $k }}" @click="active = active === {{ $k }} ? null : {{ $k }}" @blur="active = null"
                                :aria-pressed="active === {{ $k }}"
                                class="relative z-10 rounded-full px-4 py-2 text-[20px] font-semibold leading-none tracking-[-.025em] transition-all duration-200 sm:px-5 sm:py-2.5 sm:text-[25px] md:px-6 md:text-[31px]"
                                :class="active === {{ $k }} ? 'bg-emerald-600 text-white shadow-[0_8px_22px_rgba(5,150,105,.22)]' : 'bg-[#f2f7f4] text-slate-900 hover:bg-emerald-100'">
                            {{ $label }}
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="mt-14 flex flex-col items-center justify-center gap-3 sm:flex-row md:mt-20">
                <a href="{{ route('register') }}" class="inline-flex min-w-[170px] items-center justify-center rounded-full bg-emerald-600 px-7 py-3.5 text-sm font-extrabold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700">Coba Gratis 7 Hari</a>
                <a href="{{ $waLink }}" target="_blank" class="inline-flex min-w-[170px] items-center justify-center rounded-full border border-emerald-600 px-7 py-3.5 text-sm font-extrabold text-emerald-700 transition hover:bg-emerald-50">Konsultasi Bisnis</a>
            </div>
        </div>
    </section>

    {{-- ================= PETA LAYANAN ================= --}}
    <style>
        .map-sumatra { opacity: 0; transform-origin: center; transition: opacity .9s ease; }
        .map-pin, .map-label { opacity: 0; transform-box: fill-box; transform-origin: 50% 100%; }
        .map-pin-main .map-pulse { opacity: 0; }
        #peta.in .map-sumatra { opacity: 1; }
        #peta.in .map-pin { animation: pinDrop .6s cubic-bezier(.3,1.5,.5,1) var(--d) forwards; }
        #peta.in .map-label { animation: labelIn .5s ease var(--d) forwards; }
        #peta.in .map-pin-main .map-pulse { animation: pulse 2.2s ease-out 1.4s infinite; }
        @keyframes pinDrop { from { opacity: 0; transform: translateY(-26px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes labelIn { from { opacity: 0; transform: translateX(-8px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes pulse { 0% { opacity: .45; transform: scale(.6); } 100% { opacity: 0; transform: scale(3.4); } }
        .map-pulse { transform-box: fill-box; transform-origin: center; }
        @media (prefers-reduced-motion: reduce) {
            #peta .map-sumatra, #peta .map-pin, #peta .map-label { opacity: 1 !important; animation: none !important; transition: none !important; }
            #peta .map-pulse { display: none; }
        }
    </style>
    <section id="peta" class="py-20 md:py-24 px-6 bg-white">
        <div class="max-w-[1000px] mx-auto text-center">
            <h2 class="text-[32px] md:text-[42px] font-extrabold leading-[1.15] text-emerald-900">Dimulai dari Lampung, Fokus ke Sumatera</h2>
            <p class="mt-4 text-[17px] text-slate-600 max-w-[600px] mx-auto">Kami memulai dari tempat kami sendiri, supaya bantuan pasang dan latihan kasir tidak jauh dari toko Anda.</p>

            <div class="mt-10 mx-auto max-w-[920px]">
                <svg viewBox="0 0 920 360" class="w-full h-auto" role="img" aria-label="Peta Indonesia dengan Sumatera disorot dan Lampung ditandai">
<g fill="#e5e7eb" stroke="#ffffff" stroke-width="1" stroke-linejoin="round">
  <path d="M204.0 266.0 L220.0 249.0 L236.0 250.0 L266.0 256.0 L300.0 268.0 L320.0 260.0 L352.0 268.0 L388.0 284.0 L392.0 304.0 L360.0 296.0 L320.0 294.0 L280.0 286.0 L250.0 284.0 L220.0 278.0Z"/>
  <path d="M388.0 292.0 L414.0 294.0 L404.0 306.0Z"/>
  <path d="M420.0 298.0 L434.0 298.0 L428.0 308.0 L418.0 306.0Z"/>
  <path d="M436.0 298.0 L472.0 296.0 L480.0 304.0 L440.0 310.0Z"/>
  <path d="M498.0 300.0 L556.0 296.0 L556.0 306.0 L504.0 306.0Z"/>
  <path d="M480.0 316.0 L504.0 318.0 L516.0 332.0 L484.0 328.0Z"/>
  <path d="M570.0 334.0 L590.0 320.0 L616.0 298.0 L640.0 300.0 L600.0 328.0Z"/>
  <path d="M280.0 94.0 L292.0 90.0 L326.0 72.0 L360.0 66.0 L392.0 36.0 L410.0 24.0 L434.0 -8.0 L454.0 10.0 L460.0 30.0 L476.0 42.0 L452.0 66.0 L450.0 100.0 L450.0 114.0 L450.0 146.0 L434.0 160.0 L428.0 184.0 L420.0 204.0 L410.0 208.0 L390.0 200.0 L370.0 194.0 L340.0 200.0 L304.0 190.0 L300.0 160.0 L280.0 136.0 L280.0 116.0Z"/>
  <path d="M496.0 112.0 L530.0 110.0 L566.0 110.0 L600.0 98.0 L584.0 122.0 L540.0 120.0 L504.0 126.0Z"/>
  <path d="M496.0 128.0 L518.0 148.0 L566.0 148.0 L538.0 150.0 L540.0 170.0 L520.0 174.0 L500.0 160.0Z"/>
  <path d="M524.0 178.0 L548.0 190.0 L566.0 210.0 L550.0 234.0 L530.0 222.0 L544.0 200.0Z"/>
  <path d="M476.0 186.0 L496.0 190.0 L508.0 240.0 L490.0 238.0 L486.0 200.0Z"/>
  <path d="M720.0 150.0 L754.0 138.0 L790.0 148.0 L804.0 196.0 L860.0 164.0 L920.0 182.0 L920.0 312.0 L876.0 298.0 L856.0 240.0 L804.0 218.0 L784.0 208.0 L760.0 212.0 L744.0 190.0 L724.0 168.0Z"/>
  <path d="M650.0 100.0 L664.0 110.0 L662.0 140.0 L652.0 130.0 L658.0 114.0Z"/>
  <path d="M660.0 190.0 L716.0 190.0 L700.0 202.0 L660.0 198.0Z"/>
</g>
<path class="map-sumatra" d="M6.0 18.0 L30.0 25.0 L52.0 26.0 L66.0 50.0 L94.0 68.0 L108.0 84.0 L128.0 86.0 L146.0 96.0 L162.0 110.0 L178.0 140.0 L184.0 150.0 L196.0 172.0 L218.0 188.0 L220.0 194.0 L216.0 226.0 L215.0 247.4 L206.0 248.0 L192.0 246.0 L178.0 230.0 L146.0 210.0 L128.0 186.0 L118.0 168.0 L108.0 148.0 L88.0 128.0 L74.0 106.0 L70.0 90.0 L52.0 72.0 L30.0 52.0 L12.0 32.0Z" fill="#bbf7d0" stroke="#ffffff" stroke-width="1.2" stroke-linejoin="round"/>
<g transform="translate(205.2 238.6)"><g class="map-pin map-pin-main" style="--d:0.50s">
  <circle class="map-pulse" r="14" fill="#059669"/>
  <g transform="scale(1.7)"><path d="M0 0C-6-8-9-12-9-17a9 9 0 0118 0c0 5-3 9-9 17z" fill="#047857" stroke="#fff" stroke-width="1.5"/><circle cy="-17" r="3.4" fill="#fff"/></g>
</g></g>
<g class="map-label" style="--d:0.80s"><rect x="227.2" y="230.6" width="86" height="32" rx="16" fill="#047857"/><text x="270.2" y="251.6" text-anchor="middle" font-size="15" font-weight="700" fill="#fff">Lampung</text></g>
<g transform="translate(195.0 189.6)"><g class="map-pin " style="--d:0.68s">
  
  <g transform="scale(1.05)"><path d="M0 0C-6-8-9-12-9-17a9 9 0 0118 0c0 5-3 9-9 17z" fill="#10b981" stroke="#fff" stroke-width="1.5"/><circle cy="-17" r="3.4" fill="#fff"/></g>
</g></g>
<g transform="translate(145.2 206.0)"><g class="map-pin " style="--d:0.86s">
  
  <g transform="scale(1.05)"><path d="M0 0C-6-8-9-12-9-17a9 9 0 0118 0c0 5-3 9-9 17z" fill="#10b981" stroke="#fff" stroke-width="1.5"/><circle cy="-17" r="3.4" fill="#fff"/></g>
</g></g>
<g transform="translate(172.0 162.0)"><g class="map-pin " style="--d:1.04s">
  
  <g transform="scale(1.05)"><path d="M0 0C-6-8-9-12-9-17a9 9 0 0118 0c0 5-3 9-9 17z" fill="#10b981" stroke="#fff" stroke-width="1.5"/><circle cy="-17" r="3.4" fill="#fff"/></g>
</g></g>
<g transform="translate(107.0 149.0)"><g class="map-pin " style="--d:1.22s">
  
  <g transform="scale(1.05)"><path d="M0 0C-6-8-9-12-9-17a9 9 0 0118 0c0 5-3 9-9 17z" fill="#10b981" stroke="#fff" stroke-width="1.5"/><circle cy="-17" r="3.4" fill="#fff"/></g>
</g></g>
<g transform="translate(129.0 119.4)"><g class="map-pin " style="--d:1.40s">
  
  <g transform="scale(1.05)"><path d="M0 0C-6-8-9-12-9-17a9 9 0 0118 0c0 5-3 9-9 17z" fill="#10b981" stroke="#fff" stroke-width="1.5"/><circle cy="-17" r="3.4" fill="#fff"/></g>
</g></g>
<g transform="translate(73.4 58.0)"><g class="map-pin " style="--d:1.58s">
  
  <g transform="scale(1.05)"><path d="M0 0C-6-8-9-12-9-17a9 9 0 0118 0c0 5-3 9-9 17z" fill="#10b981" stroke="#fff" stroke-width="1.5"/><circle cy="-17" r="3.4" fill="#fff"/></g>
</g></g>
<g transform="translate(6.4 19.0)"><g class="map-pin " style="--d:1.76s">
  
  <g transform="scale(1.05)"><path d="M0 0C-6-8-9-12-9-17a9 9 0 0118 0c0 5-3 9-9 17z" fill="#10b981" stroke="#fff" stroke-width="1.5"/><circle cy="-17" r="3.4" fill="#fff"/></g>
</g></g>
</svg>
            </div>

            <div class="mt-6 flex items-center justify-center gap-6 text-sm text-slate-600">
                <span class="inline-flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-emerald-700"></span>Basis kami</span>
                <span class="inline-flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-emerald-400"></span>Area layanan Sumatera</span>
            </div>
        </div>
        <script>
            (function () {
                var el = document.getElementById('peta');
                if (!('IntersectionObserver' in window)) { el.classList.add('in'); return; }
                new IntersectionObserver(function (e, o) {
                    if (e[0].isIntersecting) { el.classList.add('in'); o.disconnect(); }
                }, { threshold: 0.3 }).observe(el);
            })();
        </script>
    </section>

    {{-- ================= PRICING ================= --}}
    <section id="harga" class="py-24 px-6 bg-slate-50">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-emerald-600 font-semibold text-sm uppercase tracking-wide">Paket Harga</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 mt-2">Pilih Paket Sesuai Skala Bisnis Anda</h2>
                <p class="text-slate-600 mt-3">Mulai dari UMKM kecil hingga bisnis multi-outlet — semua bisa disesuaikan.</p>
            </div>
            <div class="grid lg:grid-cols-3 gap-6">
                @foreach ([
                    ['Starter', 'Untuk UMKM kecil yang baru memulai digitalisasi kasir', ['POS transaksi', 'Manajemen produk & stok', 'QRIS static', 'Cetak struk', 'Laporan dasar'], false, 'Pilih Starter'],
                    ['Business', 'Untuk cafe/resto yang ingin kontrol operasional lebih ketat', ['Semua fitur paket Starter', 'Shift kasir', 'Export Excel & PDF', 'Laporan profit & COGS', 'Audit log', 'Notifikasi stok menipis'], true, 'Pilih Business'],
                    ['Custom', 'Untuk multi-outlet, resto besar, atau kebutuhan khusus', ['Multi-outlet & multi-user', 'Custom fitur sesuai kebutuhan', 'Training tim & staf', 'Support prioritas'], false, 'Konsultasi Sekarang'],
                ] as [$name, $desc, $features, $highlight, $cta])
                    <div class="rounded-3xl bg-white p-8 {{ $highlight ? 'border-2 border-emerald-500 shadow-2xl shadow-emerald-100 lg:scale-[1.03]' : 'border border-slate-200' }}">
                        @if($highlight)
                            <span class="inline-block px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold mb-3">Paling Direkomendasikan</span>
                        @endif
                        <h3 class="text-xl font-extrabold text-slate-900">{{ $name }}</h3>
                        <p class="text-sm text-slate-500 mt-1 mb-6">{{ $desc }}</p>
                        <ul class="space-y-3 mb-8">
                            @foreach ($features as $f)
                                <li class="flex items-start gap-2 text-sm text-slate-600">
                                    <span class="mt-0.5 w-5 h-5 shrink-0 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs">✓</span>
                                    {{ $f }}
                                </li>
                            @endforeach
                        </ul>
                        <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka" target="_blank"
                           class="block text-center px-4 py-3 rounded-xl font-semibold transition-colors {{ $highlight ? 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-lg shadow-emerald-200' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }}">
                            {{ $cta }}
                        </a>
                    </div>
                @endforeach
            </div>
            <p class="text-center text-sm text-slate-400 mt-8">* Harga &amp; detail paket dapat disesuaikan — hubungi kami untuk penawaran terbaik.</p>
        </div>
    </section>

    {{-- ================= TESTIMONIALS ================= --}}
    @php
        $testimonials = [
            [
                'name' => 'Putri Azahra',
                'role' => 'Pemilik Brewcool Ice Cream',
                'brand' => 'Brewcool',
                'category' => 'Ice Cream & Dessert',
                'quote' => 'Yoka bikin operasional Brewcool jauh lebih rapi. Transaksi lebih cepat, stok bahan mudah dipantau, dan saya tidak perlu lagi menunggu rekap manual untuk tahu kondisi toko.',
                'image' => asset('images/testimonials/putri-azahra.webp'),
                'alt' => 'Putri Azahra, pemilik Brewcool Ice Cream',
                'photoBg' => 'bg-[#d9eee5]',
                'accent' => 'text-emerald-700',
            ],
            [
                'name' => 'Rizal Putra Mahardika',
                'role' => 'Founder Base Capella Studio',
                'brand' => 'Base Capella',
                'category' => 'Creative Studio',
                'quote' => 'Pencatatan transaksi sekarang jauh lebih cepat dan rapi. Dashboard Yoka memudahkan saya memantau performa usaha kapan saja tanpa harus membongkar catatan satu per satu.',
                'image' => asset('images/testimonials/rizal-putra-mahardika.webp'),
                'alt' => 'Rizal Putra Mahardika, founder Base Capella Studio',
                'photoBg' => 'bg-[#dce7ef]',
                'accent' => 'text-sky-700',
            ],
        ];
    @endphp
    <section id="testimoni" class="py-20 md:py-28 bg-[#f3f6f8] overflow-hidden"
             x-data="{ move(direction) { this.$refs.track.scrollBy({ left: direction * this.$refs.track.clientWidth * .82, behavior: 'smooth' }) } }">
        <div class="max-w-[1200px] mx-auto px-6">
            <div class="flex items-end justify-between gap-8 mb-10 md:mb-14">
                <div class="max-w-[780px]">
                    <span class="text-emerald-600 font-bold text-sm uppercase tracking-[.14em]">Cerita Pengguna Yoka</span>
                    <h2 class="mt-3 text-[32px] md:text-[46px] font-extrabold leading-[1.14] text-slate-950">
                        Dipercaya orang-orang di balik usaha hebat
                    </h2>
                </div>
                <div class="hidden md:flex items-center gap-3 shrink-0" aria-label="Navigasi testimoni">
                    <button type="button" @click="move(-1)" aria-label="Testimoni sebelumnya"
                            class="w-12 h-12 rounded-full border border-slate-300 bg-white text-slate-700 flex items-center justify-center hover:border-emerald-600 hover:text-emerald-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m6-6-6 6 6 6"/></svg>
                    </button>
                    <button type="button" @click="move(1)" aria-label="Testimoni berikutnya"
                            class="w-12 h-12 rounded-full bg-emerald-600 text-white flex items-center justify-center hover:bg-emerald-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </button>
                </div>
            </div>

            <div x-ref="track"
                 class="testimonial-track flex gap-5 md:gap-7 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-2 -mr-6 pr-6"
                 tabindex="0" aria-label="Testimoni pengguna Yoka POS">
                @foreach ($testimonials as $testimonial)
                    <figure class="snap-start shrink-0 w-[92%] md:w-[84%] lg:w-[88%] min-h-[620px] md:min-h-[470px] grid md:grid-cols-[42%_58%] overflow-hidden rounded-[28px] bg-white shadow-[0_20px_55px_rgba(15,23,42,.08)]">
                        <div class="relative min-h-[300px] md:min-h-full {{ $testimonial['photoBg'] }} overflow-hidden">
                            <div class="absolute top-5 left-5 z-10 px-3 py-1.5 rounded-full bg-white/85 backdrop-blur-sm text-[12px] font-bold uppercase tracking-wider text-slate-700 shadow-sm">
                                {{ $testimonial['category'] }}
                            </div>
                            <img src="{{ $testimonial['image'] }}" alt="{{ $testimonial['alt'] }}"
                                 class="absolute inset-x-0 bottom-0 w-full h-[94%] object-contain object-bottom transition-transform duration-500 hover:scale-[1.02]"
                                 loading="lazy">
                        </div>

                        <figcaption class="relative flex flex-col justify-center px-7 py-9 sm:px-10 md:px-12 lg:px-16 md:py-12">
                            <svg class="absolute top-8 right-8 w-14 h-14 text-emerald-100" fill="currentColor" viewBox="0 0 32 32" aria-hidden="true"><path d="M10 8C5.6 9.4 3 13 3 18v6h9v-9H7.5c.2-2.6 1.6-4.4 4-5.2L10 8zm15 0c-4.4 1.4-7 5-7 10v6h9v-9h-4.5c.2-2.6 1.6-4.4 4-5.2L25 8z"/></svg>
                            <p class="text-xl md:text-2xl font-extrabold italic {{ $testimonial['accent'] }}">{{ $testimonial['brand'] }}</p>
                            <blockquote class="mt-7 text-[17px] md:text-[20px] leading-[1.75] text-slate-700">
                                “{{ $testimonial['quote'] }}”
                            </blockquote>
                            <div class="mt-8 pt-6 border-t border-slate-100">
                                <p class="text-lg font-extrabold text-slate-950">{{ $testimonial['name'] }}</p>
                                <p class="mt-1 text-sm md:text-[15px] font-semibold text-slate-500">{{ $testimonial['role'] }}</p>
                            </div>
                        </figcaption>
                    </figure>
                @endforeach
            </div>

            <p class="mt-5 text-sm text-slate-500 md:hidden">Geser untuk melihat cerita berikutnya <span aria-hidden="true">→</span></p>
        </div>
    </section>

    {{-- ================= FAQ ================= --}}
    <section id="faq" class="py-24 px-6">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-14">
                <span class="text-emerald-600 font-semibold text-sm uppercase tracking-wide">FAQ</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 mt-2">Pertanyaan yang Sering Diajukan</h2>
            </div>

            <div class="space-y-4" x-data="{ open: 0 }">
                @foreach ([
                    ['Apakah bisa menggunakan pembayaran QRIS?', 'Bisa. Saat metode QRIS dipilih saat transaksi, sistem akan langsung menampilkan QR statis untuk dipindai pelanggan.'],
                    ['Apakah bisa mencetak struk?', 'Bisa. Sistem mendukung cetak struk thermal (80mm) maupun struk dalam format PDF yang bisa dikirim ke pelanggan.'],
                    ['Apakah laporan bisa diexport?', 'Bisa. Laporan penjualan, produk, shift, hingga profit dapat diexport ke Excel maupun PDF kapan saja.'],
                    ['Apakah cocok untuk cafe atau usaha kecil?', 'Sangat cocok. Sistem dirancang agar mudah digunakan baik oleh usaha kecil seperti booth minuman maupun resto dengan banyak outlet.'],
                    ['Apakah bisa digunakan oleh banyak kasir sekaligus?', 'Bisa. Anda dapat menambahkan banyak akun staf dengan hak akses (role) yang berbeda-beda sesuai kebutuhan.'],
                    ['Apakah fitur bisa dikustomisasi sesuai kebutuhan bisnis?', 'Bisa. Untuk kebutuhan khusus seperti multi-outlet atau fitur tambahan, tim kami siap membantu menyesuaikan sistem.'],
                    ['Apakah bisa digunakan di HP, tablet, atau laptop?', 'Bisa. Sistem berbasis web sehingga dapat diakses dari perangkat apa pun — HP, tablet, maupun laptop — selama terhubung internet.'],
                ] as $i => [$q, $a])
                    <div class="rounded-2xl border border-slate-200 overflow-hidden">
                        <button @click="open = (open === {{ $i }} ? null : {{ $i }})"
                                class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left font-semibold text-slate-800 hover:bg-slate-50 transition-colors">
                            <span>{{ $q }}</span>
                            <svg class="w-5 h-5 shrink-0 text-emerald-600 transition-transform" :class="open === {{ $i }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="open === {{ $i }}" x-cloak x-transition class="px-6 pb-4 text-sm text-slate-500 leading-relaxed">
                            {{ $a }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @include('partials.site-footer', ['waLink' => $waLink])

    {{-- Floating WhatsApp CTA --}}
    <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka" target="_blank"
       class="fixed bottom-6 right-6 z-50 flex items-center gap-2 px-5 py-3.5 rounded-full bg-emerald-600 text-white font-semibold shadow-2xl shadow-emerald-300 hover:bg-emerald-700 transition-all hover:-translate-y-0.5">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.79.47 3.47 1.36 4.95L2 22l5.27-1.38a9.9 9.9 0 0 0 4.77 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2zm5.84 14.16c-.25.7-1.45 1.34-2 1.43-.51.08-1.16.11-1.87-.12-.43-.14-.99-.32-1.7-.63-2.99-1.29-4.94-4.31-5.09-4.51-.15-.2-1.22-1.62-1.22-3.09 0-1.47.77-2.19 1.05-2.49.27-.3.6-.37.8-.37.2 0 .4 0 .57.01.18.01.43-.07.67.51.25.6.85 2.07.92 2.22.07.15.12.33.02.53-.1.2-.15.32-.3.49-.15.17-.31.38-.45.51-.15.15-.3.31-.13.6.17.3.76 1.25 1.63 2.02 1.12 1 2.06 1.31 2.36 1.46.3.15.48.13.65-.08.18-.2.75-.87.95-1.17.2-.3.4-.25.67-.15.27.1 1.73.82 2.03.97.3.15.5.22.57.35.07.13.07.74-.18 1.43z"/></svg>
        <span class="hidden sm:inline">Chat WhatsApp</span>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
