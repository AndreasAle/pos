<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo-meta', ['seoTitle' => 'Artikel Bisnis Cafe & Resto — Yoka POS', 'seoDescription' => 'Tips praktis mengelola cafe, resto, dan toko: HPP, harga jual, stok, kas, dan laporan keuangan.'])
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
</head>
<body class="bg-[#F7F8F7] text-[#0B1B3F] antialiased">
@php
    $waLink = 'https://wa.me/6285891111795?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka';
    $articleList = collect($articles)->map(fn ($article, $slug) => [...$article, 'slug' => $slug])->values();
    $headline = $articleList->first();
    $highlights = $articleList->slice(1, 2);
    $latest = $articleList->slice(3);
    $categories = $articleList->pluck('category')->unique();
    $formatDate = fn ($date) => \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('d M Y');
@endphp
@include('partials.site-header', ['waLink' => $waLink, 'home' => route('landing'), 'solid' => true])

<main class="pt-[96px] sm:pt-[100px] lg:pt-[104px]">
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-[1200px] px-5 py-8 sm:px-6 sm:py-10">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="flex items-center gap-2.5 text-[12px] font-extrabold uppercase tracking-[.16em] text-[#16A34A]"><span class="h-2 w-2 rounded-full bg-[#16A34A]"></span>Wawasan usaha</div>
                    <h1 class="mt-3 font-['Plus_Jakarta_Sans'] text-[34px] font-extrabold leading-[1.08] tracking-[-.035em] sm:text-[44px]">Artikel Yoka</h1>
                    <p class="mt-3 max-w-[640px] text-[16px] leading-relaxed text-slate-600 sm:text-[17px]">Panduan praktis untuk membantu bisnis kuliner dan retail bekerja lebih rapi, sehat, dan menguntungkan.</p>
                </div>
                <nav class="flex max-w-full gap-2 overflow-x-auto pb-1" aria-label="Topik artikel">
                    <a href="#artikel-terbaru" class="shrink-0 rounded-full bg-[#0B1B3F] px-4 py-2 text-[13px] font-bold text-white">Semua topik</a>
                    @foreach ($categories as $category)
                        <a href="#artikel-terbaru" class="shrink-0 rounded-full border border-slate-200 bg-white px-4 py-2 text-[13px] font-bold text-slate-600 transition hover:border-[#16A34A] hover:text-[#15803D]">{{ $category }}</a>
                    @endforeach
                </nav>
            </div>
        </div>
    </section>

    <section class="bg-white py-7 sm:py-9 lg:py-11">
        <div class="mx-auto max-w-[1200px] px-5 sm:px-6">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.72fr)_minmax(330px,.88fr)] lg:gap-5">
                <a href="{{ route('articles.show', $headline['slug']) }}" class="group relative min-h-[430px] overflow-hidden rounded-[22px] bg-[#0B1B3F] sm:min-h-[520px]">
                    <img src="{{ asset($headline['image']) }}" alt="{{ $headline['title'] }}" class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-[1.025]">
                    <span class="absolute inset-0 bg-gradient-to-t from-[#07142f]/95 via-[#07142f]/30 to-transparent"></span>
                    <span class="absolute inset-x-0 bottom-0 block p-6 sm:p-9 lg:p-10">
                        <span class="inline-flex rounded-md bg-[#16A34A] px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-[.11em] text-white">Pilihan editor</span>
                        <span class="mt-4 block max-w-[720px] font-['Plus_Jakarta_Sans'] text-[28px] font-extrabold leading-[1.16] tracking-[-.025em] text-white sm:text-[38px] lg:text-[42px]">{{ $headline['title'] }}</span>
                        <span class="mt-3 hidden max-w-[650px] text-[16px] leading-relaxed text-white/80 sm:block">{{ $headline['description'] }}</span>
                        <span class="mt-5 flex flex-wrap items-center gap-3 text-[12px] font-bold text-white/75 sm:text-[13px]"><span>Tim Yoka</span><span class="h-1 w-1 rounded-full bg-white/50"></span><span>{{ $formatDate($headline['published']) }}</span><span class="h-1 w-1 rounded-full bg-white/50"></span><span>{{ $headline['minutes'] }} menit baca</span></span>
                    </span>
                </a>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 lg:gap-5">
                    @foreach ($highlights as $article)
                        <a href="{{ route('articles.show', $article['slug']) }}" class="group grid min-h-[190px] grid-cols-[.92fr_1.08fr] overflow-hidden rounded-[18px] border border-slate-200 bg-white transition hover:border-emerald-300 hover:shadow-[0_18px_40px_-28px_rgba(11,27,63,.5)] sm:min-h-[220px] lg:min-h-0 lg:flex lg:flex-col">
                            <span class="block overflow-hidden lg:h-[150px]"><img src="{{ asset($article['image']) }}" alt="{{ $article['title'] }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.035]"></span>
                            <span class="flex flex-col p-4 sm:p-5 lg:flex-1">
                                <span class="text-[11px] font-extrabold uppercase tracking-[.12em] text-[#16A34A]">{{ $article['category'] }}</span>
                                <span class="mt-2 font-['Plus_Jakarta_Sans'] text-[16px] font-extrabold leading-[1.3] group-hover:text-[#15803D] sm:text-[18px]">{{ $article['title'] }}</span>
                                <span class="mt-auto pt-3 text-[12px] font-semibold text-slate-500">{{ $article['minutes'] }} menit baca</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-emerald-100 bg-[#EAF7EE]">
        <div class="mx-auto flex max-w-[1200px] flex-col gap-4 px-5 py-5 sm:px-6 md:flex-row md:items-center">
            <span class="shrink-0 rounded-md bg-[#16A34A] px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-[.12em] text-white">Yang perlu dibaca</span>
            <p class="font-['Plus_Jakarta_Sans'] text-[14px] font-bold leading-relaxed sm:text-[15px]">Mulai dari angka dasar bisnismu: pahami HPP, tetapkan harga jual, lalu pantau stok dan kas setiap hari.</p>
            <a href="{{ route('articles.show', $headline['slug']) }}" class="shrink-0 text-[13px] font-extrabold text-[#15803D] hover:underline md:ml-auto">Baca panduan →</a>
        </div>
    </section>

    <section id="artikel-terbaru" class="py-12 sm:py-16 lg:py-20">
        <div class="mx-auto max-w-[1200px] px-5 sm:px-6">
            <div class="flex items-end justify-between border-b border-slate-200 pb-5">
                <div><p class="text-[11px] font-extrabold uppercase tracking-[.16em] text-[#16A34A]">Bacaan berikutnya</p><h2 class="mt-2 font-['Plus_Jakarta_Sans'] text-[26px] font-extrabold tracking-[-.025em] sm:text-[32px]">Artikel terbaru</h2></div>
                <span class="hidden text-[13px] font-bold text-slate-500 sm:block">{{ $articleList->count() }} artikel</span>
            </div>
            <div class="mt-7 grid gap-x-6 gap-y-9 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($latest as $article)
                    <article class="group">
                        <a href="{{ route('articles.show', $article['slug']) }}" class="block aspect-[16/10] overflow-hidden rounded-[18px] bg-slate-200"><img src="{{ asset($article['image']) }}" alt="{{ $article['title'] }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.035]"></a>
                        <div class="pt-5">
                            <div class="flex items-center gap-2.5 text-[11px] font-extrabold uppercase tracking-[.1em] text-[#16A34A]"><span>{{ $article['category'] }}</span><span class="h-1 w-1 rounded-full bg-slate-300"></span><span class="text-slate-400">{{ $formatDate($article['published']) }}</span></div>
                            <h3 class="mt-2.5 font-['Plus_Jakarta_Sans'] text-[19px] font-extrabold leading-[1.35] tracking-[-.015em] transition group-hover:text-[#15803D] sm:text-[21px]"><a href="{{ route('articles.show', $article['slug']) }}">{{ $article['title'] }}</a></h3>
                            <p class="mt-3 line-clamp-2 text-[14px] leading-relaxed text-slate-600 sm:text-[15px]">{{ $article['description'] }}</p>
                            <a href="{{ route('articles.show', $article['slug']) }}" class="mt-4 inline-flex items-center gap-2 text-[13px] font-extrabold transition group-hover:gap-3 group-hover:text-[#15803D]">Baca artikel →</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="pb-14 sm:pb-20">
        <div class="mx-auto max-w-[1200px] px-5 sm:px-6">
            <div class="relative overflow-hidden rounded-[24px] bg-[#0B1B3F] px-6 py-9 text-white sm:px-10 sm:py-11 lg:flex lg:items-center lg:justify-between lg:px-14">
                <div class="absolute -right-20 -top-24 h-64 w-64 rounded-full border-[45px] border-white/[.035]"></div>
                <div class="relative max-w-[650px]"><p class="text-[11px] font-extrabold uppercase tracking-[.16em] text-emerald-400">Kerja lebih rapi</p><h2 class="mt-3 font-['Plus_Jakarta_Sans'] text-[26px] font-extrabold leading-tight tracking-[-.025em] sm:text-[34px]">Praktikkan ilmunya langsung di tokomu.</h2><p class="mt-3 text-[15px] leading-relaxed text-white/70 sm:text-[16px]">Kelola transaksi, stok, karyawan, dan laporan dalam satu aplikasi kasir yang mudah dipakai.</p></div>
                <a href="{{ route('register') }}" class="relative mt-7 inline-flex shrink-0 items-center justify-center rounded-full bg-[#16A34A] px-6 py-3.5 text-[14px] font-extrabold text-white transition hover:bg-[#15803D] lg:ml-8 lg:mt-0">Coba gratis 7 hari</a>
            </div>
        </div>
    </section>
</main>

@include('partials.site-footer', ['waLink' => $waLink, 'home' => route('landing')])
@vite('resources/js/alpine.js')
</body>
</html>
