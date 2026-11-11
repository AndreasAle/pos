<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Yoka POS — Kasir Modern untuk Cafe, Resto, Bakery & Bisnis F&B</title>
    <meta name="description" content="Kelola transaksi, QRIS, stok, shift kasir, struk, dan laporan profit dalam satu sistem POS yang mudah digunakan untuk cafe, resto, bakery, dan UMKM F&B.">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
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
    </style>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
</head>
<body class="bg-white text-slate-800 antialiased" x-data="{ mobileOpen: false }">

    {{-- ================= NAVBAR ================= --}}
    @php $waLink = 'https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka'; @endphp
    <header
        x-data="{ scrolled: false }"
        x-init="scrolled = window.scrollY > 40; window.addEventListener('scroll', () => scrolled = window.scrollY > 40, { passive: true })"
        :class="scrolled || mobileOpen ? 'bg-white shadow-[0_2px_16px_rgba(15,23,42,.08)]' : 'bg-transparent'"
        class="fixed top-0 inset-x-0 z-40 transition-[background-color,box-shadow] duration-300"
    >
        <div class="max-w-[1200px] mx-auto px-6 h-[76px] flex items-center">
            {{-- Logo: putih di atas hero, hijau setelah scroll --}}
            <a href="#" class="flex items-center gap-2 shrink-0" aria-label="Yoka POS">
                <span class="relative block h-9 w-[100px]">
                    <img src="{{ asset('images/brand/yoka-logo-white.png') }}" alt="Yoka" class="absolute inset-0 h-full w-full object-contain object-left transition-opacity duration-300" :class="scrolled || mobileOpen ? 'opacity-0' : 'opacity-100'">
                    <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="" class="absolute inset-0 h-full w-full object-contain object-left transition-opacity duration-300" :class="scrolled || mobileOpen ? 'opacity-100' : 'opacity-0'">
                </span>
                <span class="text-[22px] leading-none font-bold tracking-tight transition-colors duration-300" :class="scrolled || mobileOpen ? 'text-emerald-700' : 'text-white'">POS</span>
            </a>

            <nav class="hidden lg:flex items-center gap-9 ml-12 text-[15px] font-bold transition-colors duration-300" :class="scrolled ? 'text-slate-800' : 'text-white'">
                <a href="#tentang" class="hover:opacity-70 transition-opacity">Tentang</a>
                <a href="#keunggulan" class="hover:opacity-70 transition-opacity">Keunggulan</a>
                                <a href="#harga" class="hover:opacity-70 transition-opacity">Harga</a>
                <a href="#faq" class="hover:opacity-70 transition-opacity">FAQ</a>
            </nav>

            <div class="hidden lg:flex items-center gap-7 ml-auto">
                <a href="{{ url('/login') }}" class="text-[15px] font-bold transition-colors duration-300" :class="scrolled ? 'text-slate-800' : 'text-white'">Masuk</a>
                <a href="{{ $waLink }}" target="_blank"
                   class="px-7 py-3 rounded-full text-[15px] font-bold transition-colors duration-300"
                   :class="scrolled ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-white text-emerald-700 hover:bg-emerald-50'">
                    Coba Gratis
                </a>
            </div>

            {{-- Hamburger --}}
            <button @click="mobileOpen = !mobileOpen" class="lg:hidden ml-auto w-10 h-10 flex items-center justify-center rounded-lg transition-colors" :class="scrolled || mobileOpen ? 'text-slate-800' : 'text-white'" aria-label="Menu">
                <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        {{-- Mobile menu --}}
        <div x-show="mobileOpen" x-cloak x-transition class="lg:hidden border-t border-slate-200 bg-white px-6 py-4 space-y-3">
            <a @click="mobileOpen=false" href="#tentang" class="block text-sm font-medium text-slate-700">Tentang</a>
            <a @click="mobileOpen=false" href="#keunggulan" class="block text-sm font-medium text-slate-700">Keunggulan</a>
            <a @click="mobileOpen=false" href="#harga" class="block text-sm font-medium text-slate-700">Harga</a>
            <a @click="mobileOpen=false" href="#faq" class="block text-sm font-medium text-slate-700">FAQ</a>
            <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka" target="_blank"
               class="block text-center px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold">Coba Demo Sekarang</a>
        </div>
    </header>

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
                'alt' => 'Tablet kasir Yoka POS dan printer struk',
            ],
            [
                'bg' => '#0c874e',
                'img' => asset('images/hero/slide-owner.jpg'),
                'imgClass' => 'right-0 bottom-0 w-[150%] sm:w-[120%] lg:w-full max-w-none',
                'title' => 'Pantau Toko dari Mana Saja',
                'text' => 'Penjualan, stok, dan shift kasir bisa dicek dari HP, tanpa harus menunggu laporan akhir hari.',
                'cta' => 'Jadwalkan Demo',
                'alt' => 'Pemilik toko memantau penjualan dari HP di samping kasir Yoka POS',
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
        :style="'background-color:' + ['{{ $slides[0]['bg'] }}','{{ $slides[1]['bg'] }}'][i]"
        aria-roledescription="carousel" aria-label="Yoka POS"
    >
        @foreach ($slides as $k => $s)
            <div class="absolute inset-0 transition-opacity duration-700"
                 :class="i === {{ $k }} ? 'opacity-100 z-10' : 'opacity-0 pointer-events-none'"
                 :aria-hidden="i !== {{ $k }}">
                <img src="{{ $s['img'] }}" alt="{{ $s['alt'] }}" class="absolute select-none {{ $s['imgClass'] }}"
                     style="-webkit-mask-image:linear-gradient(to right,transparent 0,#000 8%);mask-image:linear-gradient(to right,transparent 0,#000 8%)"
                     @if ($k === 0) fetchpriority="high" @else loading="lazy" @endif>
                <div class="relative max-w-[1200px] mx-auto px-6 h-full flex items-start lg:items-center pt-[104px] lg:pt-0">
                    <div class="max-w-[520px] lg:pb-8">
                        <h1 class="text-[34px] md:text-[48px] font-extrabold leading-[1.15] text-white mb-5">{{ $s['title'] }}</h1>
                        <p class="text-base md:text-lg text-white/90 mb-8 max-w-[440px]">{{ $s['text'] }}</p>
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ $waLink }}" target="_blank" class="px-5 sm:px-7 py-3 sm:py-3.5 rounded-full bg-white text-emerald-700 font-bold text-[15px] sm:text-base hover:bg-emerald-50 transition-colors">{{ $s['cta'] }}</a>
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

    {{-- ================= KASIR CEPAT ================= --}}
    <section id="kasir" class="py-20 md:py-28 px-6 bg-[#f2f9f5]">
        <div class="max-w-[1100px] mx-auto">
            <div class="max-w-[680px]">
                <p class="text-[17px] font-bold text-slate-900">Kasir</p>
                <h2 class="mt-4 text-[34px] md:text-[44px] font-extrabold leading-[1.12] text-slate-900">
                    Pelanggan terima pesanan, struk langsung <em class="font-black italic text-emerald-700">keluar</em>
                </h2>
                <p class="mt-5 text-[18px] leading-[1.7] text-slate-600">Tanpa antre menunggu kasir menghitung. Dari memilih menu sampai struk tercetak, semuanya dalam satu layar.</p>
            </div>

            <img src="{{ asset('images/photos/kasir-pelanggan.webp') }}" alt="Barista menyerahkan kopi ke pelanggan di depan kasir Yoka POS"
                 class="mt-12 w-full aspect-[16/8] object-cover object-[50%_35%] rounded-[28px] shadow-[0_18px_40px_rgba(15,23,42,.18)]" loading="lazy">

            <ol class="mt-12 grid md:grid-cols-3 gap-x-10 gap-y-8">
                @foreach ([
                    ['01', 'Pilih menu', 'Ketuk menu, tentukan ukuran dan topping. Pesanan bisa ditahan atas nama pelanggan atau nomor meja.'],
                    ['02', 'Terima pembayaran', 'Tunai atau QRIS. Kembalian langsung dihitung, tidak perlu kalkulator.'],
                    ['03', 'Cetak struk', 'Struk keluar dari printer begitu pembayaran selesai, atau dikirim sebagai PDF.'],
                ] as [$no, $title, $desc])
                    <li class="border-t-2 border-emerald-700 pt-5">
                        <span class="text-sm font-extrabold text-emerald-700">{{ $no }}</span>
                        <h3 class="mt-2 text-xl font-bold text-slate-900">{{ $title }}</h3>
                        <p class="mt-2 text-[15px] leading-relaxed text-slate-600">{{ $desc }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

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
            [
                'tab' => 'Kafe & Resto',
                'img' => asset('images/photos/kafe.webp'),
                'alt' => 'Barista menunjuk layar kasir Yoka POS di kafe',
                'title' => 'Pesanan masuk rapi, antrean tetap jalan',
                'desc' => 'Terima pesanan dine-in, takeaway, dan delivery dari satu layar, lalu kirim ke dapur tanpa catatan kertas.',
                'points' => ['Varian dan topping per menu', 'Tahan pesanan atas nama atau nomor meja', 'Struk otomatis ke printer thermal'],
            ],
            [
                'tab' => 'Toko Kelontong',
                'img' => asset('images/photos/toko-kelontong.webp'),
                'alt' => 'Penjaga toko kelontong mencatat penjualan dari HP',
                'title' => 'Stok barang selalu terpantau',
                'desc' => 'Jualan ratusan barang kecil tanpa pusing menghitung sisa stok di akhir hari.',
                'points' => ['Scan barcode atau cari nama produk', 'Stok berkurang otomatis tiap transaksi', 'Pemberitahuan saat stok mulai menipis'],
            ],
            [
                'tab' => 'Toko Online & Butik',
                'img' => asset('images/photos/toko-online.webp'),
                'alt' => 'Pemilik toko online melihat analitik penjualan di tablet',
                'title' => 'Rekap penjualan tanpa hitung manual',
                'desc' => 'Lihat omzet dan keuntungan dari hari ke hari, siap dikirim ke pemilik atau akuntan.',
                'points' => ['Laporan harian, mingguan, dan bulanan', 'Laporan profit dengan HPP', 'Ekspor ke Excel atau PDF sekali klik'],
            ],
        ];
    @endphp
    <section id="usaha" class="py-20 md:py-28 px-6 bg-[#f2f9f5]" x-data="{ t: 0 }">
        <div class="max-w-[1100px] mx-auto">
            <div class="text-center max-w-[640px] mx-auto">
                <h2 class="text-[32px] md:text-[42px] font-extrabold leading-[1.15] text-emerald-900">Dipakai di Kafe, Toko, dan Usaha Online</h2>
                <p class="mt-4 text-[17px] text-slate-600">Pilih jenis usaha Anda, lihat bagaimana Yoka POS membantu.</p>
            </div>

            <div class="mt-9 flex flex-wrap justify-center gap-3" role="tablist">
                @foreach ($segmen as $k => $s)
                    <button type="button" role="tab" @click="t = {{ $k }}" :aria-selected="t === {{ $k }}"
                            class="px-6 py-3 rounded-full text-[15px] font-bold border transition-colors"
                            :class="t === {{ $k }} ? 'bg-emerald-700 text-white border-emerald-700' : 'bg-white text-slate-700 border-slate-300 hover:border-emerald-600 hover:text-emerald-700'">
                        {{ $s['tab'] }}
                    </button>
                @endforeach
            </div>

            <div class="mt-12 grid lg:grid-cols-2 gap-10 lg:gap-16 items-center">
                <div class="relative aspect-[4/3] rounded-[28px] overflow-hidden shadow-[0_18px_40px_rgba(15,23,42,.18)] bg-emerald-100">
                    @foreach ($segmen as $k => $s)
                        <img src="{{ $s['img'] }}" alt="{{ $s['alt'] }}" loading="lazy"
                             class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500"
                             :class="t === {{ $k }} ? 'opacity-100' : 'opacity-0'">
                    @endforeach
                </div>

                <div class="relative min-h-[300px]">
                    @foreach ($segmen as $k => $s)
                        <div x-show="t === {{ $k }}" x-transition.opacity.duration.300ms @if ($k > 0) x-cloak @endif>
                            <h3 class="text-[28px] md:text-[32px] font-extrabold leading-[1.2] text-slate-900">{{ $s['title'] }}</h3>
                            <p class="mt-4 text-[17px] leading-relaxed text-slate-600">{{ $s['desc'] }}</p>
                            <ul class="mt-6 space-y-3">
                                @foreach ($s['points'] as $pt)
                                    <li class="flex items-start gap-3 text-[16px] text-slate-700">
                                        <svg class="w-5 h-5 mt-0.5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        {{ $pt }}
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route('register') }}" class="mt-8 inline-flex items-center gap-3 text-[17px] font-bold text-emerald-600 hover:text-emerald-700 transition-colors group">
                                Coba Gratis 7 Hari
                                <svg class="w-6 h-6 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                            </a>
                        </div>
                    @endforeach
                </div>
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
    <section id="testimoni" class="py-24 px-6 bg-slate-50">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-emerald-600 font-semibold text-sm uppercase tracking-wide">Apa Kata Mereka</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 mt-2">Dipercaya Pelaku Usaha F&B</h2>
            </div>
            <div class="grid lg:grid-cols-3 gap-6">
                @foreach ([
                    ['Sejak pakai sistem ini, tutup kasir jadi jauh lebih cepat dan selisih kas hampir tidak pernah terjadi lagi.', 'Andri Saputra', 'Owner Coffee Shop — Kopi Pagi', 'Tutup kasir lebih cepat'],
                    ['Laporan stok bahan baku sangat membantu, kami jadi tahu lebih awal kalau tepung atau mentega mau habis.', 'Sri Wulandari', 'Owner Bakery — Roti & Kue Bu Sri', 'Stok terpantau'],
                    ['Bagian yang paling kami suka adalah laporan profitnya — jadi tahu menu mana yang sebenarnya paling menguntungkan.', 'Bayu Pratama', 'Owner Resto — Warung Nasi Bayu', 'Profit per menu'],
                ] as [$quote, $name, $role, $tag])
                    <figure class="relative flex flex-col rounded-2xl bg-white border border-slate-200 p-8 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-slate-200/70">
                        <svg class="absolute top-6 right-6 w-10 h-10 text-emerald-100" fill="currentColor" viewBox="0 0 32 32"><path d="M10 8C5.6 9.4 3 13 3 18v6h9v-9H7.5c.2-2.6 1.6-4.4 4-5.2L10 8zm15 0c-4.4 1.4-7 5-7 10v6h9v-9h-4.5c.2-2.6 1.6-4.4 4-5.2L25 8z"/></svg>
                        <div class="flex gap-0.5 text-amber-400 mb-4">
                            @for ($s = 0; $s < 5; $s++)
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.1 1 5.9L10 14.9 4.8 17.7l1-5.9L1.5 7.7l5.9-.8L10 1.5z"/></svg>
                            @endfor
                        </div>
                        <blockquote class="text-slate-700 leading-relaxed mb-6 flex-1">{{ $quote }}</blockquote>
                        <span class="self-start mb-6 text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-full px-2.5 py-1">{{ $tag }}</span>
                        <figcaption class="flex items-center gap-3 pt-5 border-t border-slate-100">
                            <div class="w-11 h-11 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 text-white font-bold flex items-center justify-center text-sm">
                                {{ collect(explode(' ', $name))->map(fn($w) => $w[0])->take(2)->implode('') }}
                            </div>
                            <div>
                                <p class="font-semibold text-sm text-slate-900">{{ $name }}</p>
                                <p class="text-xs text-slate-500">{{ $role }}</p>
                            </div>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
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

    {{-- ================= CTA PENUTUP ================= --}}
    <section class="py-20 md:py-24 px-6">
        <div class="max-w-[1000px] mx-auto rounded-[28px] overflow-hidden shadow-[0_18px_40px_rgba(15,23,42,.18)] bg-emerald-800">
            <img src="{{ asset('images/photos/toko-banner.webp') }}" alt="Pemilik toko mengelola usaha dengan Yoka POS" class="w-full block" loading="lazy">
            <div class="px-8 py-9 md:px-12 flex flex-col md:flex-row md:items-center justify-between gap-6 text-white">
                <div>
                    <h2 class="text-[26px] md:text-[30px] font-extrabold leading-tight">Siap mencoba Yoka POS?</h2>
                    <p class="mt-2 text-emerald-50/90">Gratis 7 hari, semua fitur terbuka, tanpa kartu kredit.</p>
                </div>
                <div class="flex flex-wrap gap-3 shrink-0">
                    <a href="{{ route('register') }}" class="px-7 py-3.5 rounded-full bg-white text-emerald-800 font-bold hover:bg-emerald-50 transition-colors">Coba Gratis</a>
                    <a href="{{ $waLink }}" target="_blank" class="px-7 py-3.5 rounded-full border border-white/60 font-bold hover:bg-white/10 transition-colors">Hubungi Kami</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ================= FOOTER ================= --}}
    @php
        $companyName = 'PT Conwebid Indonesia Digital';
        $companyEmail = 'conwebid@gmail.com';
        $companyPhone = '08xx-xxxx-xxxx'; // TODO: isi nomor lengkap
        $companyPhoneDigits = '628xxxxxxxxxx'; // TODO: format 62..., tanpa + dan spasi
    @endphp
    <footer class="bg-white border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-6 pt-14 pb-8">
            <div class="grid md:grid-cols-12 gap-10 mb-12">
                <div class="md:col-span-5">
                    <div class="flex items-center gap-2 mb-4">
                        <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka" class="h-9 w-auto">
                        <span class="text-[22px] leading-none font-bold tracking-tight text-emerald-700">POS</span>
                    </div>
                    <p class="text-sm text-slate-500 leading-relaxed max-w-sm">
                        Sistem kasir modern untuk cafe, resto, bakery, dan bisnis F&amp;B — transaksi, stok, shift, hingga laporan dalam satu platform.
                    </p>
                    <p class="mt-5 text-xs uppercase tracking-widest text-slate-400">Dikembangkan oleh</p>
                    <p class="text-slate-900 font-semibold mt-1">{{ $companyName }}</p>
                </div>

                <div class="md:col-span-2">
                    <p class="font-semibold text-slate-900 mb-4 text-sm">Navigasi</p>
                    <ul class="space-y-3 text-sm text-slate-500">
                        <li><a href="#tentang" class="hover:text-emerald-600 transition-colors">Tentang</a></li>
                        <li><a href="#keunggulan" class="hover:text-emerald-600 transition-colors">Keunggulan</a></li>
                        <li><a href="#harga" class="hover:text-emerald-600 transition-colors">Harga</a></li>
                        <li><a href="#faq" class="hover:text-emerald-600 transition-colors">FAQ</a></li>
                    </ul>
                </div>

                <div class="md:col-span-2">
                    <p class="font-semibold text-slate-900 mb-4 text-sm">Akun</p>
                    <ul class="space-y-3 text-sm text-slate-500">
                        <li><a href="{{ route('login') }}" class="hover:text-emerald-600 transition-colors">Masuk</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-emerald-600 transition-colors">Daftar</a></li>
                    </ul>
                </div>

                <div class="md:col-span-3">
                    <p class="font-semibold text-slate-900 mb-4 text-sm">Kontak</p>
                    <ul class="space-y-3 text-sm text-slate-500">
                        <li><a href="https://wa.me/{{ $companyPhoneDigits }}?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka" target="_blank" class="hover:text-emerald-600 transition-colors">WhatsApp: {{ $companyPhone }}</a></li>
                        <li><a href="mailto:{{ $companyEmail }}" class="hover:text-emerald-600 transition-colors break-all">Email: {{ $companyEmail }}</a></li>
                    </ul>
                </div>
            </div>

            <div class="pt-6 border-t border-slate-200 flex flex-col md:flex-row items-center justify-between gap-2 text-xs text-slate-400">
                <p>&copy; {{ date('Y') }} {{ $companyName }}. Seluruh hak cipta dilindungi.</p>
                <p>Yoka POS &mdash; Kasir modern untuk bisnis F&amp;B Indonesia</p>
            </div>
        </div>
    </footer>

    {{-- Floating WhatsApp CTA --}}
    <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka" target="_blank"
       class="fixed bottom-6 right-6 z-50 flex items-center gap-2 px-5 py-3.5 rounded-full bg-emerald-600 text-white font-semibold shadow-2xl shadow-emerald-300 hover:bg-emerald-700 transition-all hover:-translate-y-0.5">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.79.47 3.47 1.36 4.95L2 22l5.27-1.38a9.9 9.9 0 0 0 4.77 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2zm5.84 14.16c-.25.7-1.45 1.34-2 1.43-.51.08-1.16.11-1.87-.12-.43-.14-.99-.32-1.7-.63-2.99-1.29-4.94-4.31-5.09-4.51-.15-.2-1.22-1.62-1.22-3.09 0-1.47.77-2.19 1.05-2.49.27-.3.6-.37.8-.37.2 0 .4 0 .57.01.18.01.43-.07.67.51.25.6.85 2.07.92 2.22.07.15.12.33.02.53-.1.2-.15.32-.3.49-.15.17-.31.38-.45.51-.15.15-.3.31-.13.6.17.3.76 1.25 1.63 2.02 1.12 1 2.06 1.31 2.36 1.46.3.15.48.13.65-.08.18-.2.75-.87.95-1.17.2-.3.4-.25.67-.15.27.1 1.73.82 2.03.97.3.15.5.22.57.35.07.13.07.74-.18 1.43z"/></svg>
        <span class="hidden sm:inline">Chat WhatsApp</span>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
