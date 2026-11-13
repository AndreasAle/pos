<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $feature['title'] }} — Yoka POS</title>
    <meta name="description" content="{{ $feature['description'] }}">
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
    </style>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
</head>
<body class="bg-white text-slate-800 antialiased" x-data="{ mobileOpen: false, featuresOpen: false }">
    @php $waLink = 'https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka'; @endphp

    <header class="fixed inset-x-0 top-0 z-40 border-b border-[#d9e1dd] bg-white">
        <div class="max-w-[1200px] mx-auto px-5 sm:px-6 h-[60px] sm:h-[68px] lg:h-[72px] flex items-center">
            <a href="{{ route('landing') }}" class="flex items-center gap-2 shrink-0" aria-label="Yoka POS — Beranda">
                <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka" class="h-7 w-[78px] sm:h-8 sm:w-[92px] object-contain object-left">
                <span class="text-[17px] sm:text-xl leading-none font-bold tracking-tight text-emerald-700">POS</span>
            </a>

            <nav class="hidden lg:flex items-center gap-9 ml-12 text-sm font-bold text-slate-800">
                <div class="relative" @mouseenter="featuresOpen = true" @mouseleave="featuresOpen = false" @click.outside="featuresOpen = false">
                    <button type="button" @click="featuresOpen = !featuresOpen" class="h-[72px] inline-flex items-center gap-1.5 text-emerald-700" :aria-expanded="featuresOpen">
                        Fitur
                        <svg class="w-4 h-4 transition-transform" :class="featuresOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="featuresOpen" x-cloak x-transition class="absolute top-[64px] -left-28 w-[920px] border border-[#d9e1dd] bg-white p-7">
                        <div class="grid grid-cols-3 gap-8">
                            @foreach ($featureGroups as $group => $items)
                                <div>
                                    <p class="mb-3 text-[12px] font-extrabold uppercase tracking-[.16em] text-slate-400">{{ $group }}</p>
                                    <div class="divide-y divide-slate-100">
                                        @foreach ($items as $slug => $item)
                                            <a href="{{ route('features.show', $slug) }}" class="block py-3 text-sm font-bold transition-colors {{ $slug === $featureSlug ? 'text-emerald-700' : 'text-slate-700 hover:text-emerald-700' }}">{{ $item['title'] }}</a>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <a href="{{ route('landing') }}#usaha" class="hover:text-emerald-700">Solusi Bisnis</a>
                <a href="{{ route('landing') }}#harga" class="hover:text-emerald-700">Paket Harga</a>
                <a href="{{ route('landing') }}#faq" class="hover:text-emerald-700">FAQ</a>
            </nav>

            <div class="hidden lg:flex items-center gap-7 ml-auto">
                <x-lang-switch variant="light" />
                <a href="{{ route('login') }}" class="text-sm font-bold text-slate-800">Masuk</a>
                <a href="{{ route('register') }}" class="border-2 border-emerald-700 bg-emerald-700 px-6 py-2.5 text-sm font-bold text-white hover:bg-white hover:text-emerald-700 transition-colors">Coba Gratis</a>
            </div>

            <button type="button" @click="mobileOpen = !mobileOpen" class="lg:hidden ml-auto -mr-2 w-11 h-11 flex items-center justify-center text-slate-800" aria-label="Buka menu">
                <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div x-show="mobileOpen" x-cloak x-transition class="lg:hidden max-h-[calc(100vh-60px)] overflow-y-auto border-t border-[#d9e1dd] bg-white px-5 py-5">
            @foreach ($featureGroups as $group => $items)
                <p class="mt-5 first:mt-0 text-[12px] font-extrabold uppercase tracking-[.16em] text-slate-400">{{ $group }}</p>
                <div class="mt-2 divide-y divide-slate-100 border-y border-slate-100">
                    @foreach ($items as $slug => $item)
                        <a href="{{ route('features.show', $slug) }}" class="block py-3 text-sm font-bold {{ $slug === $featureSlug ? 'text-emerald-700' : 'text-slate-700' }}">{{ $item['title'] }}</a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </header>

    {{-- Satu template untuk seluruh halaman fitur: datar, siku, garis tipis, tipografi sebagai titik tekan. --}}
    <main class="pt-[60px] sm:pt-[68px] lg:pt-[72px] text-[#0f1f1a]">

        {{-- Judul + foto --}}
        <section class="border-b border-[#d9e1dd] bg-[#f6f8f7]">
            <div class="mx-auto grid max-w-[1200px] items-center gap-7 px-5 py-9 sm:px-6 lg:grid-cols-12 lg:gap-12 lg:py-16">
                <div class="lg:col-span-5">
                    <p class="flex items-center gap-2 text-[12px] font-bold uppercase tracking-[.14em] text-[#0c7856]">
                        <span class="h-[2px] w-6 bg-[#0c7856]"></span>
                        <a href="{{ route('landing') }}#keunggulan" class="hover:underline">Fitur</a> / {{ $feature['category'] }}
                    </p>
                    <h1 class="mt-4 text-[32px] font-extrabold leading-[1.06] tracking-[-.03em] sm:text-[42px] lg:text-[50px]">{{ $feature['headline'] }}</h1>
                    <p class="mt-4 max-w-[460px] text-[16px] leading-[1.6] text-slate-600">{{ $feature['description'] }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('register') }}" class="border-2 border-[#0c7856] bg-[#0c7856] px-6 py-3 text-[15px] font-bold text-white transition-colors hover:bg-white hover:text-[#0c7856]">Coba Gratis</a>
                        <a href="{{ $waLink }}" target="_blank" class="border-2 border-[#0f1f1a] px-6 py-3 text-[15px] font-bold transition-colors hover:bg-[#0f1f1a] hover:text-white">Jadwalkan Demo</a>
                    </div>
                </div>
                <figure class="lg:col-span-7">
                    <img src="{{ asset($feature['image']) }}" alt="{{ $feature['image_alt'] }}" class="aspect-[16/10] w-full object-cover">
                    <figcaption class="mt-2 text-[12px] font-semibold uppercase tracking-[.14em] text-slate-400">Ilustrasi &middot; {{ $feature['title'] }}</figcaption>
                </figure>
            </div>
        </section>

        {{-- Tiga angka kunci --}}
        <section class="border-b border-[#d9e1dd]">
            <dl class="mx-auto grid max-w-[1200px] grid-cols-3 divide-x divide-[#d9e1dd] sm:px-6">
                @foreach ($feature['metrics'] as [$figure, $label])
                    <div class="px-3 py-5 sm:px-6 sm:py-8 sm:first:pl-0">
                        <dt class="text-[16px] font-extrabold leading-tight tracking-[-.01em] text-[#0c7856] sm:text-[24px]">{{ $figure }}</dt>
                        <dd class="mt-1 text-[12px] leading-snug text-slate-500 sm:text-[14px]">{{ $label }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- Cara kerja --}}
        <section class="border-b border-[#d9e1dd]">
            <div class="mx-auto grid max-w-[1200px] gap-6 px-5 py-10 sm:px-6 lg:grid-cols-12 lg:gap-12 lg:py-16">
                <h2 class="text-[24px] font-extrabold tracking-[-.02em] lg:col-span-4 lg:text-[30px]">Cara kerjanya</h2>
                <ol class="grid gap-6 sm:grid-cols-3 lg:col-span-8">
                    @foreach ($feature['steps'] as $i => [$title, $desc])
                        <li class="border-t-2 border-[#0c7856] pt-3">
                            <span class="text-[13px] font-extrabold text-[#0c7856]">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="mt-1 text-[17px] font-bold">{{ $title }}</h3>
                            <p class="mt-1 text-[14px] leading-[1.55] text-slate-600">{{ $desc }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Yang didapat --}}
        <section class="border-b border-[#d9e1dd] bg-[#f6f8f7]">
            <div class="mx-auto max-w-[1200px] px-5 py-10 sm:px-6 lg:py-16">
                <h2 class="text-[24px] font-extrabold tracking-[-.02em] lg:text-[30px]">Yang kamu dapatkan</h2>
                <div class="mt-5 border-b border-[#d9e1dd] lg:mt-8">
                    @foreach ($feature['highlights'] as [$title, $desc])
                        <div class="grid gap-1 border-t border-[#d9e1dd] py-4 sm:grid-cols-12 sm:gap-8 sm:py-5">
                            <h3 class="text-[16px] font-bold sm:col-span-4 lg:text-[18px]">{{ $title }}</h3>
                            <p class="text-[14px] leading-[1.6] text-slate-600 sm:col-span-8 lg:text-[15px]">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Fitur lain --}}
        <section class="border-b border-[#d9e1dd]">
            <div class="mx-auto max-w-[1200px] px-5 py-10 sm:px-6 lg:py-14">
                <h2 class="text-[20px] font-extrabold tracking-[-.01em] lg:text-[24px]">Fitur lainnya</h2>
                <div class="mt-5 grid gap-x-12 gap-y-6 md:grid-cols-3">
                    @foreach ($featureGroups as $group => $items)
                        <div>
                            <p class="text-[12px] font-extrabold uppercase tracking-[.14em] text-slate-400">{{ $group }}</p>
                            <ul class="mt-2 border-b border-[#d9e1dd]">
                                @foreach ($items as $slug => $item)
                                    <li class="border-t border-[#d9e1dd]">
                                        @if ($slug === $featureSlug)
                                            <span class="flex items-center justify-between py-3 text-[15px] font-bold text-[#0c7856]" aria-current="page">{{ $item['title'] }}<span class="text-[12px] uppercase tracking-wide">Dilihat</span></span>
                                        @else
                                            <a href="{{ route('features.show', $slug) }}" class="group flex items-center justify-between py-3 text-[15px] font-semibold hover:text-[#0c7856]">{{ $item['title'] }}<span aria-hidden="true" class="transition-transform group-hover:translate-x-1">&rarr;</span></a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Ajakan --}}
        <section class="bg-[#075f40] text-white">
            <div class="mx-auto flex max-w-[1200px] flex-col gap-5 px-5 py-10 sm:px-6 md:flex-row md:items-center md:justify-between lg:py-14">
                <h2 class="max-w-[560px] text-[24px] font-extrabold leading-[1.15] tracking-[-.02em] lg:text-[32px]">Lihat {{ $feature['title'] }} bekerja di tokomu.</h2>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="border-2 border-white bg-white px-6 py-3 text-[15px] font-bold text-[#075f40] transition-colors hover:bg-transparent hover:text-white">Coba Gratis</a>
                    <a href="{{ $waLink }}" target="_blank" class="border-2 border-white/70 px-6 py-3 text-[15px] font-bold transition-colors hover:bg-white hover:text-[#075f40]">Tanya via WhatsApp</a>
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer', ['waLink' => $waLink, 'home' => route('landing')])

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
