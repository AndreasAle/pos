<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FNB POS — Kasir Modern untuk Cafe, Resto, Bakery & Bisnis F&B</title>
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
</head>
<body class="bg-white text-slate-800 antialiased" x-data="{ mobileOpen: false }">

    {{-- ================= NAVBAR ================= --}}
    <header
        x-data="{ scrolled: false }"
        x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 12)"
        :class="scrolled ? 'shadow-sm bg-white/90 backdrop-blur border-slate-200' : 'bg-white/60 backdrop-blur border-transparent'"
        class="fixed top-0 inset-x-0 z-40 border-b transition-all"
    >
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <a href="#" class="flex items-center gap-2">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center text-white font-bold shadow-md shadow-emerald-200">F</div>
                <span class="text-lg font-extrabold tracking-tight text-slate-900">FNB<span class="text-emerald-600">POS</span></span>
            </a>

            <nav class="hidden lg:flex items-center gap-8 text-sm font-medium text-slate-600">
                <a href="#fitur" class="hover:text-emerald-600 transition-colors">Fitur</a>
                <a href="#keunggulan" class="hover:text-emerald-600 transition-colors">Keunggulan</a>
                <a href="#cara-kerja" class="hover:text-emerald-600 transition-colors">Cara Kerja</a>
                <a href="#harga" class="hover:text-emerald-600 transition-colors">Harga</a>
                <a href="#faq" class="hover:text-emerald-600 transition-colors">FAQ</a>
            </nav>

            <div class="hidden lg:flex items-center gap-3">
                <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20FNB" target="_blank"
                   class="px-5 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800 transition-colors">
                    Hubungi Kami
                </a>
                <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20FNB" target="_blank"
                   class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-lg shadow-emerald-200 transition-colors">
                    Coba Demo Sekarang
                </a>
            </div>

            {{-- Hamburger --}}
            <button @click="mobileOpen = !mobileOpen" class="lg:hidden w-10 h-10 flex items-center justify-center rounded-lg border border-slate-200 text-slate-700">
                <svg x-show="!mobileOpen" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                <svg x-show="mobileOpen" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        {{-- Mobile menu --}}
        <div x-show="mobileOpen" x-cloak x-transition class="lg:hidden border-t border-slate-200 bg-white px-6 py-4 space-y-3">
            <a @click="mobileOpen=false" href="#fitur" class="block text-sm font-medium text-slate-700">Fitur</a>
            <a @click="mobileOpen=false" href="#keunggulan" class="block text-sm font-medium text-slate-700">Keunggulan</a>
            <a @click="mobileOpen=false" href="#cara-kerja" class="block text-sm font-medium text-slate-700">Cara Kerja</a>
            <a @click="mobileOpen=false" href="#harga" class="block text-sm font-medium text-slate-700">Harga</a>
            <a @click="mobileOpen=false" href="#faq" class="block text-sm font-medium text-slate-700">FAQ</a>
            <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20FNB" target="_blank"
               class="block text-center px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold">Coba Demo Sekarang</a>
        </div>
    </header>

    {{-- ================= HERO ================= --}}
    <section class="relative overflow-hidden pt-36 pb-24 px-6 bg-gradient-to-b from-emerald-50/60 to-white">
        <div class="blob w-96 h-96 bg-emerald-300 -top-32 -left-20"></div>
        <div class="blob w-96 h-96 bg-teal-200 top-10 right-0"></div>

        <div class="max-w-7xl mx-auto relative z-10 grid lg:grid-cols-2 gap-16 items-center">
            <div class="fade-up">
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold mb-6">
                    🚀 Sistem Kasir Generasi Baru untuk F&B
                </span>
                <h1 class="text-4xl md:text-5xl xl:text-6xl font-extrabold leading-[1.1] text-slate-900 mb-6">
                    Kasir Modern untuk <span class="gradient-text">Cafe, Resto, Bakery,</span> dan Bisnis F&B
                </h1>
                <p class="text-lg text-slate-600 mb-8 max-w-xl">
                    Kelola transaksi, QRIS, stok, shift kasir, struk, dan laporan profit dalam satu sistem POS yang mudah digunakan — dari kasir di outlet sampai laporan untuk owner.
                </p>
                <div class="flex flex-wrap gap-4">
                    <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20FNB" target="_blank"
                       class="px-7 py-3.5 rounded-xl bg-emerald-600 text-white font-semibold shadow-xl shadow-emerald-200 hover:bg-emerald-700 transition-all hover:-translate-y-0.5">
                        Coba Demo Sekarang
                    </a>
                    <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20FNB" target="_blank"
                       class="px-7 py-3.5 rounded-xl border border-slate-300 font-semibold text-slate-700 hover:bg-slate-50 transition-all hover:-translate-y-0.5">
                        Konsultasi Gratis
                    </a>
                </div>
                <div class="mt-10 flex items-center gap-8 text-sm text-slate-500">
                    <div><span class="block text-2xl font-extrabold text-slate-900">500+</span>Outlet Aktif</div>
                    <div><span class="block text-2xl font-extrabold text-slate-900">1 Jt+</span>Transaksi/Bulan</div>
                    <div><span class="block text-2xl font-extrabold text-slate-900">99.9%</span>Uptime Sistem</div>
                </div>
            </div>

            {{-- Mockup dashboard --}}
            <div class="relative fade-up" style="animation-delay:.1s">
                <div class="rounded-3xl bg-white border border-slate-200 shadow-2xl shadow-emerald-100 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <p class="text-xs text-slate-400">Dashboard Owner</p>
                            <p class="font-bold text-slate-900">Ringkasan Hari Ini</p>
                        </div>
                        <span class="text-xs px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 font-semibold">● Live</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="rounded-2xl bg-emerald-50 p-4">
                            <p class="text-xs text-slate-500 mb-1">Total Penjualan</p>
                            <p class="text-xl font-extrabold text-slate-900">Rp 6.840.000</p>
                            <p class="text-xs text-emerald-600 font-medium mt-1">▲ 22% dari kemarin</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500 mb-1">Order Masuk</p>
                            <p class="text-xl font-extrabold text-slate-900">128 Order</p>
                            <p class="text-xs text-slate-500 font-medium mt-1">32 sedang diproses</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-100 p-4 mb-4">
                        <p class="text-xs font-semibold text-slate-500 mb-3">Grafik Penjualan 7 Hari</p>
                        <div class="flex items-end gap-2 h-24">
                            <div class="flex-1 rounded-t-md bg-emerald-200" style="height:45%"></div>
                            <div class="flex-1 rounded-t-md bg-emerald-300" style="height:65%"></div>
                            <div class="flex-1 rounded-t-md bg-emerald-300" style="height:50%"></div>
                            <div class="flex-1 rounded-t-md bg-emerald-400" style="height:80%"></div>
                            <div class="flex-1 rounded-t-md bg-emerald-500" style="height:60%"></div>
                            <div class="flex-1 rounded-t-md bg-emerald-500" style="height:90%"></div>
                            <div class="flex-1 rounded-t-md bg-emerald-600" style="height:100%"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-2xl border border-slate-100 p-4">
                            <p class="text-xs font-semibold text-slate-500 mb-2">Produk Terlaris</p>
                            <p class="text-sm font-bold text-slate-900">Es Kopi Susu Gula Aren</p>
                            <p class="text-xs text-slate-400">87 terjual hari ini</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 p-4">
                            <p class="text-xs font-semibold text-slate-500 mb-2">Pembayaran QRIS</p>
                            <div class="w-12 h-12 rounded-lg bg-slate-900 grid grid-cols-3 gap-0.5 p-1.5">
                                @for ($i = 0; $i < 9; $i++)
                                    <span class="bg-white rounded-[2px] {{ in_array($i, [1,3,5,7]) ? 'opacity-40' : '' }}"></span>
                                @endfor
                            </div>
                            <p class="text-xs text-slate-400 mt-2">Scan & bayar instan</p>
                        </div>
                    </div>
                </div>

                <div class="absolute -bottom-6 -left-6 hidden md:block bg-white rounded-2xl shadow-xl border border-slate-100 px-5 py-3 fade-up" style="animation-delay:.3s">
                    <p class="text-xs text-slate-400">Shift Kasir</p>
                    <p class="text-sm font-bold text-slate-900">✅ Tutup shift selisih Rp 0</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ================= PROBLEM ================= --}}
    <section class="py-24 px-6">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-emerald-600 font-semibold text-sm uppercase tracking-wide">Masalah yang sering dialami</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 mt-2">Kenapa Mengelola Bisnis F&B Sering Terasa Berantakan?</h2>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ([
                    ['📝', 'Transaksi Masih Manual', 'Pencatatan di buku atau Excel rawan salah hitung dan memakan waktu kasir.'],
                    ['📉', 'Laporan Tidak Rapi', 'Owner kesulitan melihat performa penjualan karena data tersebar di banyak tempat.'],
                    ['📦', 'Stok Tiba-tiba Habis', 'Bahan baku menipis tanpa peringatan, akhirnya menu favorit terpaksa "sold out".'],
                    ['🕒', 'Shift Kasir Tidak Terpantau', 'Selisih kas saat tutup shift sulit dilacak penyebabnya.'],
                    ['💸', 'Profit Tidak Terlihat Jelas', 'Omzet tinggi belum tentu untung — tanpa data COGS, profit nyata jadi tanda tanya.'],
                    ['🧾', 'Struk & Laporan Manual', 'Membuat struk dan rekap laporan satu per satu membuang waktu staf.'],
                ] as [$icon, $title, $desc])
                    <div class="hover-card rounded-2xl border border-slate-200 p-6 bg-white">
                        <div class="w-12 h-12 rounded-xl bg-red-50 text-2xl flex items-center justify-center mb-4">{{ $icon }}</div>
                        <h3 class="font-bold text-slate-900 mb-2">{{ $title }}</h3>
                        <p class="text-sm text-slate-500">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ================= SOLUTION ================= --}}
    <section id="keunggulan" class="py-24 px-6 bg-gradient-to-b from-slate-900 to-slate-800 text-white relative overflow-hidden">
        <div class="blob w-[28rem] h-[28rem] bg-emerald-500/30 -bottom-40 -right-32"></div>
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-emerald-400 font-semibold text-sm uppercase tracking-wide">Solusinya</span>
                <h2 class="text-3xl md:text-4xl font-extrabold mt-2">FNB POS Menjawab Semua Tantangan Itu</h2>
                <p class="text-slate-300 mt-3">Satu sistem yang menyatukan kasir, stok, shift, hingga laporan bisnis — siap pakai tanpa ribet.</p>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach ([
                    ['⚡', 'Kasir digital yang cepat dan ringan'],
                    ['🔳', 'QRIS static langsung tampil saat pembayaran'],
                    ['📦', 'Stok produk otomatis terpantau real-time'],
                    ['📊', 'Laporan sales & profit yang jelas'],
                    ['🕒', 'Shift kasir lebih terkontrol & transparan'],
                    ['📤', 'Export laporan ke Excel & PDF sekali klik'],
                    ['🔍', 'Audit log aktivitas seluruh staf'],
                    ['🔔', 'Notifikasi otomatis saat stok menipis'],
                ] as [$icon, $text])
                    <div class="rounded-2xl bg-white/5 border border-white/10 backdrop-blur p-5 hover:bg-white/10 transition-colors">
                        <div class="text-2xl mb-3">{{ $icon }}</div>
                        <p class="text-sm text-slate-200">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ================= FEATURES ================= --}}
    <section id="fitur" class="py-24 px-6">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-emerald-600 font-semibold text-sm uppercase tracking-wide">Fitur Unggulan</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 mt-2">Semua Fitur yang Dibutuhkan Bisnis F&B Modern</h2>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ([
                    ['🧾', 'POS Transaksi Cepat', 'Antarmuka kasir ringan dengan varian produk, addon, dan promo dalam satu layar.'],
                    ['🔳', 'QRIS Static Payment', 'QR statis langsung tampil saat metode QRIS dipilih, tanpa perlu device tambahan.'],
                    ['📷', 'Barcode / SKU Scanner', 'Dukungan scanner hardware maupun input keyboard untuk transaksi lebih cepat.'],
                    ['📦', 'Manajemen Produk & Stok', 'Kelola kategori, varian, resep, dan pergerakan stok bahan baku secara otomatis.'],
                    ['🕒', 'Shift Kasir', 'Buka & tutup shift dengan pencatatan kas masuk-keluar yang rapi dan terlacak.'],
                    ['🖨️', 'Struk Thermal / PDF', 'Cetak struk thermal 80mm atau kirim struk dalam format PDF ke pelanggan.'],
                    ['📈', 'Laporan Sales', 'Pantau tren penjualan harian, mingguan, hingga bulanan dengan grafik interaktif.'],
                    ['💰', 'Laporan Profit', 'Analisa profit & COGS untuk mengetahui keuntungan bisnis yang sesungguhnya.'],
                    ['📤', 'Export Excel & PDF', 'Unduh laporan kapan saja untuk kebutuhan owner maupun tim accounting.'],
                    ['🔍', 'Audit Log', 'Riwayat aktivitas staf tercatat lengkap demi transparansi dan keamanan data.'],
                    ['🔔', 'Notifikasi Stok Menipis', 'Peringatan otomatis dikirim sebelum bahan baku benar-benar habis.'],
                    ['🖥️', 'Dashboard Owner', 'Pantau performa seluruh outlet dari satu layar, kapan saja dan di mana saja.'],
                ] as [$icon, $title, $desc])
                    <div class="hover-card rounded-2xl border border-slate-200 p-6 bg-white">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-2xl flex items-center justify-center mb-4">{{ $icon }}</div>
                        <h3 class="font-bold text-slate-900 mb-2">{{ $title }}</h3>
                        <p class="text-sm text-slate-500">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ================= QRIS HIGHLIGHT ================= --}}
    <section id="cara-kerja" class="py-24 px-6 bg-emerald-50/60">
        <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <span class="text-emerald-600 font-semibold text-sm uppercase tracking-wide">Pembayaran Digital</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 mt-2 mb-4">Pembayaran QRIS Lebih Cepat dan Praktis</h2>
                <p class="text-slate-600 mb-8">
                    Saat metode QRIS dipilih, sistem langsung menampilkan QR statis. Customer tinggal scan, kasir konfirmasi pembayaran, dan transaksi pun selesai — tanpa perlu mesin EDC tambahan.
                </p>

                <div class="space-y-4">
                    @foreach ([
                        ['1', 'Pilih QRIS', 'Kasir memilih metode pembayaran QRIS pada layar transaksi.'],
                        ['2', 'QR Muncul', 'Sistem menampilkan kode QR statis milik bisnis Anda secara instan.'],
                        ['3', 'Customer Scan', 'Pelanggan memindai QR menggunakan aplikasi e-wallet atau m-banking.'],
                        ['4', 'Konfirmasi Bayar', 'Kasir mengonfirmasi pembayaran sudah diterima.'],
                        ['5', 'Struk Terbit', 'Struk otomatis tercetak / terkirim sebagai bukti transaksi selesai.'],
                    ] as [$num, $title, $desc])
                        <div class="flex items-start gap-4">
                            <div class="w-9 h-9 shrink-0 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-sm">{{ $num }}</div>
                            <div>
                                <p class="font-bold text-slate-900">{{ $title }}</p>
                                <p class="text-sm text-slate-500">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-center">
                <div class="rounded-3xl bg-white border border-slate-200 shadow-2xl p-8 max-w-sm w-full text-center">
                    <p class="text-sm text-slate-400 mb-1">Total Pembayaran</p>
                    <p class="text-3xl font-extrabold text-slate-900 mb-6">Rp 87.000</p>
                    <div class="w-44 h-44 mx-auto rounded-2xl bg-slate-900 grid grid-cols-6 gap-1 p-3">
                        @for ($i = 0; $i < 36; $i++)
                            <span class="rounded-[2px] bg-white {{ $i % 5 === 0 || $i % 7 === 0 ? 'opacity-30' : '' }}"></span>
                        @endfor
                    </div>
                    <p class="text-sm font-semibold text-slate-700 mt-6">Scan QRIS untuk membayar</p>
                    <span class="inline-block mt-3 px-4 py-1.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">Menunggu konfirmasi kasir…</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ================= REPORT & ANALYTICS ================= --}}
    <section class="py-24 px-6">
        <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-16 items-center">
            <div class="order-2 lg:order-1 rounded-3xl border border-slate-200 shadow-xl p-6">
                <p class="text-xs font-semibold text-slate-400 mb-4">Laporan Profit & COGS — 30 Hari Terakhir</p>
                <div class="flex items-end gap-3 h-40 mb-4">
                    @foreach ([55, 70, 50, 85, 65, 95, 75, 60, 88, 100] as $h)
                        <div class="flex-1 rounded-t-lg bg-gradient-to-t from-emerald-200 to-emerald-500" style="height: {{ $h }}%"></div>
                    @endforeach
                </div>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-400">Omzet</p>
                        <p class="font-bold text-slate-900">Rp 182jt</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs text-slate-400">COGS</p>
                        <p class="font-bold text-slate-900">Rp 76jt</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-3">
                        <p class="text-xs text-emerald-700">Profit Bersih</p>
                        <p class="font-bold text-emerald-700">Rp 106jt</p>
                    </div>
                </div>
            </div>

            <div class="order-1 lg:order-2">
                <span class="text-emerald-600 font-semibold text-sm uppercase tracking-wide">Laporan & Analitik</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 mt-2 mb-4">Lihat Performa Bisnis dalam Sekejap</h2>
                <p class="text-slate-600 mb-6">Owner tidak perlu lagi menunggu rekap manual — semua data tersaji rapi dan siap diambil keputusan kapan saja.</p>
                <ul class="space-y-3">
                    @foreach ([
                        'Laporan penjualan harian, mingguan, dan bulanan',
                        'Daftar produk terlaris secara otomatis',
                        'Analisa profit dan COGS yang akurat',
                        'Laporan shift kasir per outlet',
                        'Export laporan ke Excel & PDF sekali klik',
                        'Owner bisa mengambil keputusan bisnis lebih cepat & tepat',
                    ] as $point)
                        <li class="flex items-start gap-3 text-sm text-slate-600">
                            <span class="mt-0.5 w-5 h-5 shrink-0 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs">✓</span>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- ================= TARGET USER ================= --}}
    <section class="py-24 px-6 bg-slate-50">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-emerald-600 font-semibold text-sm uppercase tracking-wide">Cocok untuk Siapa?</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 mt-2">Dipakai oleh Berbagai Jenis Bisnis F&B</h2>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-5">
                @foreach ([
                    ['☕', 'Coffee Shop'], ['🍽️', 'Cafe'], ['🍜', 'Resto Kecil'], ['🥖', 'Bakery'],
                    ['🍰', 'Dessert Shop'], ['🧋', 'Booth Minuman'], ['🍱', 'UMKM Makanan'], ['🏪', 'Franchise Kecil'],
                ] as [$icon, $name])
                    <div class="hover-card rounded-2xl bg-white border border-slate-200 p-6 text-center">
                        <div class="text-3xl mb-3">{{ $icon }}</div>
                        <p class="font-semibold text-slate-800 text-sm">{{ $name }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ================= BENEFITS ================= --}}
    <section class="py-24 px-6">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-emerald-600 font-semibold text-sm uppercase tracking-wide">Manfaat Nyata</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 mt-2">Apa yang Anda Dapatkan dengan FNB POS?</h2>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ([
                    ['⚡', 'Transaksi lebih cepat', 'Antrean lebih singkat, pelanggan lebih puas, omzet harian lebih maksimal.'],
                    ['🗂️', 'Data penjualan lebih rapi', 'Semua transaksi tercatat otomatis, tidak ada lagi catatan yang tercecer.'],
                    ['🔒', 'Mengurangi kebocoran kas', 'Shift kasir dan audit log membuat setiap transaksi lebih transparan.'],
                    ['📍', 'Owner bisa pantau bisnis', 'Cek performa outlet kapan saja dan dari mana saja lewat dashboard.'],
                    ['🙌', 'Staff lebih mudah bekerja', 'Antarmuka simpel membuat kasir baru cepat terlatih tanpa training lama.'],
                    ['📨', 'Laporan siap dikirim', 'Tinggal export ke Excel/PDF untuk dikirim ke owner atau tim accounting.'],
                    ['✨', 'Bisnis terlihat lebih profesional', 'Struk rapi, sistem modern, dan pelayanan yang lebih cepat di mata pelanggan.'],
                ] as [$icon, $title, $desc])
                    <div class="hover-card rounded-2xl border border-slate-200 p-6 bg-white">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-2xl flex items-center justify-center mb-4">{{ $icon }}</div>
                        <h3 class="font-bold text-slate-900 mb-2">{{ $title }}</h3>
                        <p class="text-sm text-slate-500">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
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
                        <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20FNB" target="_blank"
                           class="block text-center px-4 py-3 rounded-xl font-semibold transition-colors {{ $highlight ? 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-lg shadow-emerald-200' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }}">
                            {{ $cta }}
                        </a>
                    </div>
                @endforeach
            </div>
            <p class="text-center text-sm text-slate-400 mt-8">* Harga &amp; detail paket dapat disesuaikan — hubungi kami untuk penawaran terbaik.</p>
        </div>
    </section>

    {{-- ================= ROADMAP ================= --}}
    <section class="py-24 px-6">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-emerald-600 font-semibold text-sm uppercase tracking-wide">Pengembangan Selanjutnya</span>
                <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 mt-2">Roadmap Fitur yang Sedang Kami Siapkan</h2>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach ([
                    ['🪑', 'Table Management'],
                    ['🎫', 'Kitchen Order Ticket'],
                    ['🖨️', 'Multi-Printer'],
                    ['📺', 'Customer Display'],
                    ['📑', 'Purchase Order Supplier'],
                    ['💵', 'Expense Tracking'],
                    ['🏬', 'Multi-Outlet'],
                    ['📱', 'Mobile / PWA'],
                ] as [$icon, $name])
                    <div class="rounded-2xl border border-dashed border-emerald-300 bg-emerald-50/50 p-5 text-center">
                        <div class="text-2xl mb-2">{{ $icon }}</div>
                        <p class="font-semibold text-sm text-slate-700">{{ $name }}</p>
                        <span class="inline-block mt-2 text-[11px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-medium">Segera Hadir</span>
                    </div>
                @endforeach
            </div>
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
                    ['Sejak pakai sistem ini, tutup kasir jadi jauh lebih cepat dan selisih kas hampir tidak pernah terjadi lagi.', 'Andri Saputra', 'Owner Coffee Shop — Kopi Pagi'],
                    ['Laporan stok bahan baku sangat membantu, kami jadi tahu lebih awal kalau tepung atau mentega mau habis.', 'Sri Wulandari', 'Owner Bakery — Roti & Kue Bu Sri'],
                    ['Bagian yang paling kami suka adalah laporan profitnya — jadi tahu menu mana yang sebenarnya paling menguntungkan.', 'Bayu Pratama', 'Owner Resto — Warung Nasi Bayu'],
                ] as [$quote, $name, $role])
                    <div class="rounded-2xl bg-white border border-slate-200 p-7">
                        <div class="text-emerald-500 text-2xl mb-3">"</div>
                        <p class="text-slate-600 text-sm mb-6 leading-relaxed">{{ $quote }}</p>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center text-sm">
                                {{ collect(explode(' ', $name))->map(fn($w) => $w[0])->take(2)->implode('') }}
                            </div>
                            <div>
                                <p class="font-semibold text-sm text-slate-900">{{ $name }}</p>
                                <p class="text-xs text-slate-500">{{ $role }}</p>
                            </div>
                        </div>
                    </div>
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

    {{-- ================= FINAL CTA ================= --}}
    <section class="py-24 px-6">
        <div class="max-w-5xl mx-auto rounded-3xl bg-gradient-to-br from-emerald-600 to-emerald-800 px-8 py-16 text-center text-white relative overflow-hidden">
            <div class="blob w-72 h-72 bg-white/20 -top-24 -right-16"></div>
            <div class="relative z-10">
                <h2 class="text-3xl md:text-4xl font-extrabold mb-4">Siap Membuat Operasional Bisnis F&B Lebih Rapi?</h2>
                <p class="text-emerald-50 mb-8 max-w-2xl mx-auto">Gunakan POS modern untuk transaksi, stok, shift, QRIS, dan laporan bisnis dalam satu sistem.</p>
                <div class="flex flex-wrap items-center justify-center gap-4">
                    <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20FNB" target="_blank"
                       class="px-7 py-3.5 rounded-xl bg-white text-emerald-700 font-semibold hover:bg-emerald-50 transition-all hover:-translate-y-0.5 shadow-xl">
                        Hubungi Kami
                    </a>
                    <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20FNB" target="_blank"
                       class="px-7 py-3.5 rounded-xl border border-white/40 font-semibold hover:bg-white/10 transition-all hover:-translate-y-0.5">
                        Coba Demo
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- ================= FOOTER ================= --}}
    <footer class="border-t border-slate-200 pt-16 pb-8 px-6">
        <div class="max-w-7xl mx-auto grid md:grid-cols-4 gap-10 mb-12">
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center text-white font-bold">F</div>
                    <span class="text-lg font-extrabold text-slate-900">FNB<span class="text-emerald-600">POS</span></span>
                </div>
                <p class="text-sm text-slate-500 leading-relaxed">
                    Sistem kasir modern untuk cafe, resto, bakery, dan bisnis F&B — transaksi, stok, shift, hingga laporan dalam satu platform.
                </p>
            </div>
            <div>
                <p class="font-semibold text-slate-900 mb-3 text-sm">Navigasi</p>
                <ul class="space-y-2 text-sm text-slate-500">
                    <li><a href="#fitur" class="hover:text-emerald-600">Fitur</a></li>
                    <li><a href="#keunggulan" class="hover:text-emerald-600">Keunggulan</a></li>
                    <li><a href="#cara-kerja" class="hover:text-emerald-600">Cara Kerja</a></li>
                    <li><a href="#harga" class="hover:text-emerald-600">Harga</a></li>
                    <li><a href="#faq" class="hover:text-emerald-600">FAQ</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold text-slate-900 mb-3 text-sm">Akun</p>
                <ul class="space-y-2 text-sm text-slate-500">
                    <li><a href="{{ route('login') }}" class="hover:text-emerald-600">Masuk</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-emerald-600">Daftar</a></li>
                </ul>
            </div>
            <div>
                <p class="font-semibold text-slate-900 mb-3 text-sm">Kontak</p>
                <ul class="space-y-2 text-sm text-slate-500">
                    <li>
                        <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20FNB" target="_blank" class="hover:text-emerald-600">
                            WhatsApp: +62 8xx-xxxx-xxxx
                        </a>
                    </li>
                    <li><a href="mailto:halo@fnbpos.id" class="hover:text-emerald-600">Email: halo@fnbpos.id</a></li>
                </ul>
            </div>
        </div>
        <div class="max-w-7xl mx-auto pt-6 border-t border-slate-200 text-center text-sm text-slate-400">
            &copy; {{ date('Y') }} FNB POS. Seluruh hak cipta dilindungi.
        </div>
    </footer>

    {{-- Floating WhatsApp CTA --}}
    <a href="https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20FNB" target="_blank"
       class="fixed bottom-6 right-6 z-50 flex items-center gap-2 px-5 py-3.5 rounded-full bg-emerald-600 text-white font-semibold shadow-2xl shadow-emerald-300 hover:bg-emerald-700 transition-all hover:-translate-y-0.5">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.79.47 3.47 1.36 4.95L2 22l5.27-1.38a9.9 9.9 0 0 0 4.77 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2zm5.84 14.16c-.25.7-1.45 1.34-2 1.43-.51.08-1.16.11-1.87-.12-.43-.14-.99-.32-1.7-.63-2.99-1.29-4.94-4.31-5.09-4.51-.15-.2-1.22-1.62-1.22-3.09 0-1.47.77-2.19 1.05-2.49.27-.3.6-.37.8-.37.2 0 .4 0 .57.01.18.01.43-.07.67.51.25.6.85 2.07.92 2.22.07.15.12.33.02.53-.1.2-.15.32-.3.49-.15.17-.31.38-.45.51-.15.15-.3.31-.13.6.17.3.76 1.25 1.63 2.02 1.12 1 2.06 1.31 2.36 1.46.3.15.48.13.65-.08.18-.2.75-.87.95-1.17.2-.3.4-.25.67-.15.27.1 1.73.82 2.03.97.3.15.5.22.57.35.07.13.07.74-.18 1.43z"/></svg>
        <span class="hidden sm:inline">Chat WhatsApp</span>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
