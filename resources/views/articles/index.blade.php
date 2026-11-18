<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo-meta', ['seoTitle' => 'Artikel Bisnis Cafe & Resto — Yoka POS', 'seoDescription' => 'Tips praktis mengelola cafe, resto, dan toko: menghitung HPP, menentukan harga jual, stok bahan baku, kas kasir, dan laporan keuangan sederhana.'])
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
</head>
<body class="bg-white text-slate-800 antialiased">
    @php $waLink = 'https://wa.me/6285891111795?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka'; @endphp
    @include('partials.site-header', ['waLink' => $waLink, 'home' => route('landing')])

    <main>
        <section class="bg-emerald-600 text-white pt-[60px] sm:pt-[68px] lg:pt-[76px]">
            <div class="max-w-[1200px] mx-auto px-5 sm:px-6 py-12 sm:py-16 lg:py-20">
                <p class="text-xs sm:text-sm font-bold uppercase tracking-[.16em] text-emerald-100">Artikel</p>
                <h1 class="mt-3 max-w-[760px] text-[32px] sm:text-[42px] lg:text-[52px] font-extrabold leading-[1.08] tracking-[-.03em]">Tips mengelola cafe, resto, dan toko</h1>
                <p class="mt-4 max-w-[620px] text-[16px] sm:text-[18px] leading-relaxed text-emerald-50">Hitung HPP, tentukan harga jual, jaga stok dan kas. Ditulis singkat untuk pemilik usaha yang sibuk.</p>
            </div>
        </section>

        <section class="max-w-[1200px] mx-auto px-5 sm:px-6 py-10 sm:py-14">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $slug => $a)
                    <a href="{{ route('articles.show', $slug) }}" class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:border-emerald-300 hover:shadow-[0_20px_45px_-20px_rgba(5,150,105,.35)]">
                        <img src="{{ asset($a['image']) }}" alt="" loading="lazy" class="aspect-[16/9] w-full object-cover">
                        <span class="flex flex-1 flex-col p-5 sm:p-6">
                            <span class="text-[12px] font-bold uppercase tracking-[.12em] text-emerald-700">{{ $a['category'] }} &middot; {{ $a['minutes'] }} menit baca</span>
                            <span class="mt-2 text-[18px] sm:text-[20px] font-extrabold leading-snug text-slate-900 group-hover:text-emerald-700">{{ $a['title'] }}</span>
                            <span class="mt-2 text-[15px] leading-relaxed text-slate-600">{{ $a['description'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    </main>

    @include('partials.site-footer', ['waLink' => $waLink, 'home' => route('landing')])
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
