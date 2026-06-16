<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FNB POS — Sistem Kasir & Manajemen Resto Modern</title>
    <meta name="description" content="FNB POS membantu cafe, resto, dan cloud kitchen mengelola kasir, stok, laporan, dan loyalitas pelanggan dalam satu platform.">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-slate-900 antialiased">

    {{-- Navbar --}}
    <header class="border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-9 h-9 rounded-lg bg-orange-500 flex items-center justify-center text-white font-bold">F</div>
                <span class="text-lg font-semibold">FNB POS</span>
            </div>
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600">
                <a href="#fitur" class="hover:text-slate-900">Fitur</a>
                <a href="#harga" class="hover:text-slate-900">Harga</a>
                <a href="#testimoni" class="hover:text-slate-900">Testimoni</a>
            </nav>
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ url('/dashboard') }}" class="px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-medium hover:bg-orange-600">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Masuk</a>
                    <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-medium hover:bg-orange-600">Coba Gratis</a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="max-w-7xl mx-auto px-6 py-20 grid md:grid-cols-2 gap-12 items-center">
        <div>
            <span class="inline-block px-3 py-1 rounded-full bg-orange-100 text-orange-700 text-xs font-semibold mb-4">
                Untuk Cafe, Resto & Cloud Kitchen
            </span>
            <h1 class="text-4xl md:text-5xl font-bold leading-tight mb-6">
                Kelola Bisnis F&B Anda Lebih Mudah dengan Satu Platform
            </h1>
            <p class="text-lg text-slate-600 mb-8">
                Kasir, stok bahan baku, laporan penjualan, hingga loyalitas pelanggan — semua terhubung dalam satu sistem POS yang dirancang khusus untuk industri makanan & minuman.
            </p>
            <div class="flex flex-wrap gap-4">
                <a href="{{ route('register') }}" class="px-6 py-3 rounded-lg bg-orange-500 text-white font-semibold hover:bg-orange-600">Mulai Gratis Sekarang</a>
                <a href="#fitur" class="px-6 py-3 rounded-lg border border-slate-300 font-semibold text-slate-700 hover:bg-slate-50">Lihat Fitur</a>
            </div>
            <p class="mt-4 text-sm text-slate-500">Tanpa kartu kredit · Setup dalam hitungan menit</p>
        </div>
        <div class="bg-slate-100 rounded-2xl p-4">
            <div class="rounded-xl bg-white shadow-xl border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="font-semibold">Penjualan Hari Ini</span>
                    <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-700">+18%</span>
                </div>
                <div class="text-3xl font-bold mb-6">Rp 4.250.000</div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500">Nasi Goreng Spesial</span>
                        <span class="font-medium">42 terjual</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500">Es Kopi Susu</span>
                        <span class="font-medium">67 terjual</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500">Ayam Geprek</span>
                        <span class="font-medium">31 terjual</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="fitur" class="bg-slate-50 py-20">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <h2 class="text-3xl font-bold mb-3">Semua yang Anda Butuhkan, dalam Satu Sistem</h2>
                <p class="text-slate-600">Dari transaksi kasir di kasir hingga laporan untuk pemilik bisnis.</p>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ([
                    ['🧾', 'Kasir (POS) Cepat', 'Transaksi, varian produk, promo, hingga split bill — semua dalam antarmuka yang ringan dan responsif.'],
                    ['📦', 'Manajemen Stok & Resep', 'Lacak bahan baku, resep produk, dan pergerakan stok secara otomatis setiap transaksi.'],
                    ['📊', 'Laporan Lengkap', 'Laporan penjualan, produk terlaris, kasir, shift, hingga laba rugi — siap export Excel & PDF.'],
                    ['🎁', 'Loyalitas & Promo', 'Kelola member, poin loyalitas, dan promosi untuk meningkatkan repeat order pelanggan.'],
                    ['🍳', 'Kitchen Display System', 'Pesanan masuk langsung tampil di dapur secara real-time, tanpa kertas.'],
                    ['🏢', 'Multi-Outlet & Multi-User', 'Kelola banyak cabang dan staf dengan hak akses berbeda dari satu akun bisnis.'],
                ] as [$icon, $title, $desc])
                    <div class="bg-white rounded-xl border border-slate-200 p-6">
                        <div class="text-3xl mb-3">{{ $icon }}</div>
                        <h3 class="font-semibold text-lg mb-2">{{ $title }}</h3>
                        <p class="text-sm text-slate-600">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Pricing --}}
    <section id="harga" class="py-20">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <h2 class="text-3xl font-bold mb-3">Paket Harga Sesuai Kebutuhan Bisnis Anda</h2>
                <p class="text-slate-600">Mulai gratis, upgrade kapan saja seiring berkembangnya bisnis Anda.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-6">
                @foreach ([
                    ['Starter', 'Rp 0', 'Cocok untuk usaha baru memulai', ['1 Outlet', '2 Pengguna', 'POS & Laporan Dasar'], false],
                    ['Pro', 'Rp 199rb/bln', 'Untuk bisnis yang sedang berkembang', ['5 Outlet', '10 Pengguna', 'Laporan Lengkap + Export', 'Kitchen Display System'], true],
                    ['Business', 'Rp 499rb/bln', 'Untuk jaringan resto/cafe besar', ['Outlet Tanpa Batas', 'Pengguna Tanpa Batas', 'Loyalitas & Promo Lanjutan', 'Dukungan Prioritas'], false],
                ] as [$name, $price, $desc, $features, $highlight])
                    <div class="rounded-2xl border p-8 {{ $highlight ? 'border-orange-500 shadow-xl scale-[1.02]' : 'border-slate-200' }}">
                        @if($highlight)
                            <span class="inline-block px-3 py-1 rounded-full bg-orange-100 text-orange-700 text-xs font-semibold mb-3">Paling Populer</span>
                        @endif
                        <h3 class="text-xl font-bold">{{ $name }}</h3>
                        <p class="text-sm text-slate-500 mb-4">{{ $desc }}</p>
                        <div class="text-3xl font-bold mb-6">{{ $price }}</div>
                        <ul class="space-y-2 mb-8 text-sm text-slate-600">
                            @foreach ($features as $f)
                                <li class="flex items-center gap-2"><span class="text-green-600">✓</span> {{ $f }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('register') }}" class="block text-center px-4 py-2.5 rounded-lg font-semibold {{ $highlight ? 'bg-orange-500 text-white hover:bg-orange-600' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }}">Pilih {{ $name }}</a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Testimonials --}}
    <section id="testimoni" class="bg-slate-50 py-20">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center max-w-2xl mx-auto mb-14">
                <h2 class="text-3xl font-bold mb-3">Dipercaya Pelaku Usaha F&B</h2>
            </div>
            <div class="grid md:grid-cols-3 gap-6">
                @foreach ([
                    ['Sejak pakai FNB POS, laporan harian yang dulu makan waktu 1 jam sekarang otomatis selesai dalam hitungan detik.', 'Dewi — Pemilik Kedai Kopi Senja'],
                    ['Stok bahan baku jadi jauh lebih terkontrol, dapur dan kasir akhirnya bisa sinkron tanpa salah pesan.', 'Rizal — Manajer Operasional, Ayam Geprek Bara'],
                    ['Fitur multi-outlet sangat membantu kami memantau 4 cabang sekaligus dari satu dashboard.', 'Maya — Owner, Boba Bahagia Group'],
                ] as [$quote, $author])
                    <div class="bg-white rounded-xl border border-slate-200 p-6">
                        <p class="text-slate-600 text-sm mb-4">"{{ $quote }}"</p>
                        <p class="font-semibold text-sm">{{ $author }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-20">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <h2 class="text-3xl font-bold mb-4">Siap Mengembangkan Bisnis F&B Anda?</h2>
            <p class="text-slate-600 mb-8">Daftar sekarang dan rasakan kemudahan mengelola kasir, stok, dan laporan dalam satu platform.</p>
            <a href="{{ route('register') }}" class="inline-block px-8 py-3 rounded-lg bg-orange-500 text-white font-semibold hover:bg-orange-600">Coba Gratis Sekarang</a>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-slate-200 py-10">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-slate-500">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-md bg-orange-500 flex items-center justify-center text-white font-bold text-xs">F</div>
                <span class="font-medium text-slate-700">FNB POS</span>
            </div>
            <p>&copy; {{ date('Y') }} FNB POS. Seluruh hak cipta dilindungi.</p>
        </div>
    </footer>

</body>
</html>
