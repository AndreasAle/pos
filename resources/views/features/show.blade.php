<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $feature['title'] }} — Yoka POS</title>
    <meta name="description" content="{{ $feature['description'] }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
        .paper-grid { background-image: linear-gradient(rgba(15,23,42,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(15,23,42,.06) 1px, transparent 1px); background-size: 24px 24px; }
        .receipt-edge { clip-path: polygon(0 0,100% 0,100% 97%,97% 100%,94% 97%,91% 100%,88% 97%,85% 100%,82% 97%,79% 100%,76% 97%,73% 100%,70% 97%,67% 100%,64% 97%,61% 100%,58% 97%,55% 100%,52% 97%,49% 100%,46% 97%,43% 100%,40% 97%,37% 100%,34% 97%,31% 100%,28% 97%,25% 100%,22% 97%,19% 100%,16% 97%,13% 100%,10% 97%,7% 100%,4% 97%,0 100%); }
    </style>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
</head>
<body class="bg-white text-slate-800 antialiased" x-data="{ mobileOpen: false, featuresOpen: false }">
    @php $waLink = 'https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka'; @endphp

    <header class="fixed inset-x-0 top-0 z-40 border-b border-slate-200 bg-white">
        <div class="max-w-[1200px] mx-auto px-6 h-[72px] flex items-center">
            <a href="{{ route('landing') }}" class="flex items-center gap-2 shrink-0" aria-label="Yoka POS — Beranda">
                <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka" class="h-8 w-[92px] object-contain object-left">
                <span class="text-xl leading-none font-bold tracking-tight text-emerald-700">POS</span>
            </a>

            <nav class="hidden lg:flex items-center gap-9 ml-12 text-sm font-bold text-slate-800">
                <div class="relative" @mouseenter="featuresOpen = true" @mouseleave="featuresOpen = false" @click.outside="featuresOpen = false">
                    <button type="button" @click="featuresOpen = !featuresOpen" class="h-[72px] inline-flex items-center gap-1.5 text-emerald-700" :aria-expanded="featuresOpen">
                        Fitur
                        <svg class="w-4 h-4 transition-transform" :class="featuresOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="featuresOpen" x-cloak x-transition class="absolute top-[64px] -left-28 w-[920px] border border-slate-200 bg-white p-7 shadow-[0_24px_60px_rgba(15,23,42,.18)]">
                        <div class="grid grid-cols-3 gap-8">
                            @foreach ($featureGroups as $group => $items)
                                <div>
                                    <p class="mb-3 text-[10px] font-extrabold uppercase tracking-[.16em] text-slate-400">{{ $group }}</p>
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
                <a href="{{ route('landing') }}#harga" class="hover:text-emerald-700">Harga</a>
                <a href="{{ route('landing') }}#faq" class="hover:text-emerald-700">FAQ</a>
            </nav>

            <div class="hidden lg:flex items-center gap-7 ml-auto">
                <a href="{{ route('login') }}" class="text-sm font-bold text-slate-800">Masuk</a>
                <a href="{{ $waLink }}" target="_blank" class="border-2 border-emerald-700 bg-emerald-700 px-6 py-2.5 text-sm font-bold text-white hover:bg-white hover:text-emerald-700 transition-colors">Coba Gratis</a>
            </div>

            <button type="button" @click="mobileOpen = !mobileOpen" class="lg:hidden ml-auto w-10 h-10 flex items-center justify-center text-slate-800" aria-label="Buka menu">
                <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div x-show="mobileOpen" x-cloak x-transition class="lg:hidden max-h-[calc(100vh-72px)] overflow-y-auto border-t border-slate-200 bg-white px-6 py-5">
            @foreach ($featureGroups as $group => $items)
                <p class="mt-5 first:mt-0 text-[10px] font-extrabold uppercase tracking-[.16em] text-slate-400">{{ $group }}</p>
                <div class="mt-2 divide-y divide-slate-100 border-y border-slate-100">
                    @foreach ($items as $slug => $item)
                        <a href="{{ route('features.show', $slug) }}" class="block py-3 text-sm font-bold {{ $slug === $featureSlug ? 'text-emerald-700' : 'text-slate-700' }}">{{ $item['title'] }}</a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </header>

    @include('features.pages.' . $featureSlug)

    <footer class="border-t border-slate-200 bg-white px-6 py-8">
        <div class="max-w-[1100px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-400">
            <p>&copy; {{ date('Y') }} Conwebid Indonesia Digital. Seluruh hak cipta dilindungi.</p>
            <a href="{{ route('landing') }}" class="font-semibold text-emerald-700">Yoka POS — Kembali ke beranda</a>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
