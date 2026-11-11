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
    </style>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
</head>
<body class="bg-white text-slate-800 antialiased" x-data="{ mobileOpen: false, featuresOpen: false }">
    @php $waLink = 'https://wa.me/628xxxxxxxxxx?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka'; @endphp

    <header class="fixed inset-x-0 top-0 z-40 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl">
        <div class="max-w-[1200px] mx-auto px-6 h-[76px] flex items-center">
            <a href="{{ route('landing') }}" class="flex items-center gap-2 shrink-0" aria-label="Yoka POS — Beranda">
                <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka" class="h-9 w-[100px] object-contain object-left">
                <span class="text-[22px] leading-none font-bold tracking-tight text-emerald-700">POS</span>
            </a>

            <nav class="hidden lg:flex items-center gap-9 ml-12 text-[15px] font-bold text-slate-800">
                <div class="relative" @mouseenter="featuresOpen = true" @mouseleave="featuresOpen = false" @click.outside="featuresOpen = false">
                    <button type="button" @click="featuresOpen = !featuresOpen" class="h-[76px] inline-flex items-center gap-1.5 text-emerald-700" :aria-expanded="featuresOpen">
                        Fitur
                        <svg class="w-4 h-4 transition-transform" :class="featuresOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="featuresOpen" x-cloak x-transition
                         class="absolute top-[68px] -left-28 w-[920px] rounded-[26px] border border-slate-200 bg-white p-7 shadow-[0_28px_70px_rgba(15,23,42,.20)]">
                        <span class="absolute -top-2 left-[132px] w-4 h-4 rotate-45 border-l border-t border-slate-200 bg-white"></span>
                        <div class="grid grid-cols-3 gap-7">
                            @foreach ($featureGroups as $group => $items)
                                <div>
                                    <p class="mb-3 text-[11px] font-extrabold uppercase tracking-[.14em] text-slate-400">{{ $group }}</p>
                                    <div class="space-y-1">
                                        @foreach ($items as $slug => $item)
                                            <a href="{{ route('features.show', $slug) }}"
                                               class="block rounded-xl px-3 py-2.5 transition-colors {{ $slug === $featureSlug ? 'bg-emerald-50 text-emerald-700' : 'text-slate-800 hover:bg-slate-50 hover:text-emerald-700' }}">
                                                <span class="block text-sm font-bold">{{ $item['title'] }}</span>
                                                <span class="mt-0.5 block text-[11px] font-medium text-slate-400">{{ $item['category'] }}</span>
                                            </a>
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
                <a href="{{ route('login') }}" class="text-[15px] font-bold text-slate-800">Masuk</a>
                <a href="{{ $waLink }}" target="_blank" class="px-7 py-3 rounded-full bg-emerald-600 text-[15px] font-bold text-white hover:bg-emerald-700 transition-colors">Coba Gratis</a>
            </div>

            <button type="button" @click="mobileOpen = !mobileOpen" class="lg:hidden ml-auto w-10 h-10 flex items-center justify-center text-slate-800" aria-label="Buka menu">
                <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div x-show="mobileOpen" x-cloak x-transition class="lg:hidden max-h-[calc(100vh-76px)] overflow-y-auto border-t border-slate-200 bg-white px-6 py-5">
            @foreach ($featureGroups as $group => $items)
                <p class="mt-4 first:mt-0 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">{{ $group }}</p>
                <div class="mt-2 grid gap-1">
                    @foreach ($items as $slug => $item)
                        <a href="{{ route('features.show', $slug) }}" class="rounded-xl px-3 py-2.5 text-sm font-semibold {{ $slug === $featureSlug ? 'bg-emerald-700 text-white' : 'text-slate-700 bg-slate-50' }}">{{ $item['title'] }}</a>
                    @endforeach
                </div>
            @endforeach
            <a href="{{ $waLink }}" target="_blank" class="mt-5 block rounded-xl bg-emerald-600 px-5 py-3 text-center text-sm font-bold text-white">Coba Gratis</a>
        </div>
    </header>

    <main>
        <section class="relative overflow-hidden bg-[#f2f9f5] px-6 pb-20 pt-[132px] md:pb-28 md:pt-[160px]">
            <div class="absolute -top-32 right-[-10%] w-[520px] h-[520px] rounded-full bg-emerald-200/40 blur-3xl"></div>
            <div class="relative max-w-[1100px] mx-auto grid lg:grid-cols-2 gap-12 lg:gap-20 items-center">
                <div>
                    <a href="{{ route('landing') }}" class="inline-flex items-center gap-2 text-sm font-bold text-emerald-700 hover:text-emerald-800">
                        <span aria-hidden="true">←</span> Kembali ke beranda
                    </a>
                    <p class="mt-8 text-sm font-extrabold uppercase tracking-[.14em] text-emerald-600">{{ $feature['category'] }}</p>
                    <h1 class="mt-4 text-[40px] md:text-[56px] font-extrabold leading-[1.08] text-slate-950">{{ $feature['headline'] }}</h1>
                    <p class="mt-6 max-w-[560px] text-[17px] md:text-[19px] leading-[1.75] text-slate-600">{{ $feature['description'] }}</p>
                    <div class="mt-9 flex flex-wrap gap-3">
                        <a href="{{ $waLink }}" target="_blank" class="rounded-full bg-emerald-600 px-7 py-3.5 text-sm font-extrabold text-white hover:bg-emerald-700 transition-colors">Coba Gratis</a>
                        <a href="#cara-kerja" class="rounded-full border border-slate-300 bg-white px-7 py-3.5 text-sm font-extrabold text-slate-700 hover:border-emerald-600 hover:text-emerald-700 transition-colors">Lihat Cara Kerja</a>
                    </div>
                </div>

                @if ($featureSlug === 'telegram')
                    <div class="rounded-[30px] border border-slate-200 bg-[#dce8e2] p-5 shadow-[0_25px_70px_rgba(15,23,42,.18)]">
                        <div class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm">
                            <span class="w-11 h-11 rounded-full bg-emerald-600 text-white flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 3-7.5 18-4.3-7.7L3 9.5 21 3Z"/></svg>
                            </span>
                            <span><strong class="block text-sm text-slate-900">Yoka POS Assistant</strong><small class="font-semibold text-emerald-600">bot · online</small></span>
                        </div>
                        <div class="space-y-4 py-6">
                            <div class="ml-auto max-w-[78%] rounded-2xl rounded-br-md bg-emerald-600 px-4 py-3 text-sm text-white">Omzet hari ini berapa?</div>
                            <div class="max-w-[88%] rounded-2xl rounded-bl-md bg-white px-4 py-3 text-sm leading-relaxed text-slate-700 shadow-sm">💰 Omzet hari ini: <strong>Rp4.850.000</strong> dari 73 transaksi.<br><span class="text-emerald-700">📈 Naik 18% dari periode sebelumnya.</span></div>
                            <div class="ml-auto max-w-[78%] rounded-2xl rounded-br-md bg-emerald-600 px-4 py-3 text-sm text-white">Stok apa yang mau habis?</div>
                            <div class="max-w-[88%] rounded-2xl rounded-bl-md bg-white px-4 py-3 text-sm leading-relaxed text-slate-700 shadow-sm">⚠️ Susu segar tersisa 4 liter dan biji kopi tersisa 1,2 kg.</div>
                        </div>
                    </div>
                @else
                    <div class="relative">
                        <div class="absolute -inset-4 rounded-[34px] bg-emerald-700"></div>
                        <img src="{{ asset($feature['image']) }}" alt="{{ $feature['image_alt'] }}" class="relative w-full aspect-[4/3] rounded-[28px] object-cover shadow-[0_24px_60px_rgba(15,23,42,.22)]">
                        <div class="absolute -bottom-6 left-5 right-5 grid grid-cols-3 gap-2 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-xl backdrop-blur-sm">
                            @foreach ($feature['metrics'] as [$value, $label])
                                <div class="px-2 text-center">
                                    <strong class="block text-xs md:text-sm text-emerald-700">{{ $value }}</strong>
                                    <span class="mt-1 block text-[9px] md:text-[10px] leading-tight text-slate-500">{{ $label }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <section class="px-6 py-20 md:py-28 bg-white">
            <div class="max-w-[1100px] mx-auto">
                <div class="max-w-[680px]">
                    <p class="text-sm font-extrabold uppercase tracking-[.14em] text-emerald-600">Manfaat Utama</p>
                    <h2 class="mt-4 text-[32px] md:text-[42px] font-extrabold leading-tight text-slate-950">{{ $feature['title'] }} yang bekerja mengikuti operasional Anda</h2>
                </div>
                <div class="mt-12 grid md:grid-cols-3 gap-6">
                    @foreach ($feature['highlights'] as $index => [$title, $description])
                        <article class="rounded-3xl border border-slate-200 p-7 hover:-translate-y-1 hover:shadow-xl hover:shadow-slate-200/70 transition-all">
                            <span class="w-11 h-11 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm font-extrabold">0{{ $index + 1 }}</span>
                            <h3 class="mt-6 text-xl font-extrabold text-slate-900">{{ $title }}</h3>
                            <p class="mt-3 text-sm leading-[1.75] text-slate-600">{{ $description }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="cara-kerja" class="scroll-mt-24 px-6 py-20 md:py-28 bg-slate-950 text-white">
            <div class="max-w-[1100px] mx-auto grid lg:grid-cols-[.8fr_1.2fr] gap-12 lg:gap-20">
                <div>
                    <p class="text-sm font-extrabold uppercase tracking-[.14em] text-emerald-400">Cara Kerja</p>
                    <h2 class="mt-4 text-[32px] md:text-[42px] font-extrabold leading-tight">Mulai tanpa proses yang rumit</h2>
                    <p class="mt-5 leading-relaxed text-slate-400">Yoka dirancang supaya owner dan tim operasional bisa langsung menggunakannya dalam alur kerja sehari-hari.</p>
                </div>
                <ol class="space-y-5">
                    @foreach ($feature['steps'] as $index => [$title, $description])
                        <li class="flex gap-5 rounded-2xl border border-white/10 bg-white/5 p-5">
                            <span class="w-10 h-10 shrink-0 rounded-full bg-emerald-500 text-emerald-950 flex items-center justify-center text-sm font-extrabold">{{ $index + 1 }}</span>
                            <span><strong class="block text-lg text-white">{{ $title }}</strong><span class="mt-1 block text-sm leading-relaxed text-slate-400">{{ $description }}</span></span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="px-6 py-20 md:py-24 bg-[#f2f9f5]">
            <div class="max-w-[1000px] mx-auto text-center">
                <p class="text-sm font-extrabold uppercase tracking-[.14em] text-emerald-600">Siap mencoba?</p>
                <h2 class="mt-4 text-[32px] md:text-[44px] font-extrabold text-slate-950">Bawa {{ strtolower($feature['title']) }} ke bisnis Anda</h2>
                <p class="mt-4 text-slate-600">Coba Yoka POS gratis selama 7 hari atau diskusikan kebutuhan operasional Anda bersama tim kami.</p>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('register') }}" class="rounded-full bg-emerald-600 px-7 py-3.5 text-sm font-extrabold text-white hover:bg-emerald-700">Mulai Gratis</a>
                    <a href="{{ $waLink }}" target="_blank" class="rounded-full border border-slate-300 bg-white px-7 py-3.5 text-sm font-extrabold text-slate-700 hover:border-emerald-600 hover:text-emerald-700">Hubungi Kami</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white px-6 py-8">
        <div class="max-w-[1100px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-400">
            <p>&copy; {{ date('Y') }} Conwebid Indonesia Digital. Seluruh hak cipta dilindungi.</p>
            <a href="{{ route('landing') }}" class="font-semibold text-emerald-700">Yoka POS — Kembali ke beranda</a>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
