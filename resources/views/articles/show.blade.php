<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo-meta', ['seoTitle' => $article['title'].' — Yoka POS', 'seoDescription' => $article['description'], 'seoImage' => $article['image'], 'seoType' => 'article'])
    <meta property="article:published_time" content="{{ $article['published'] }}">
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Article',
                'headline' => $article['title'],
                'description' => $article['description'],
                'image' => asset($article['image']),
                'datePublished' => $article['published'],
                'dateModified' => $article['published'],
                'inLanguage' => 'id-ID',
                'author' => ['@type' => 'Organization', 'name' => 'Tim Yoka POS'],
                'publisher' => ['@type' => 'Organization', 'name' => 'Yoka POS', 'logo' => ['@type' => 'ImageObject', 'url' => asset('images/brand/yoka-logo.png')]],
                'mainEntityOfPage' => route('articles.show', $slug),
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => collect($article['faq'])->map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]])->all(),
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => route('landing')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Artikel', 'item' => route('articles.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $article['title']],
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
</head>
<body class="bg-[#F7F8F7] text-[#0B1B3F] antialiased">
    @php $waLink = 'https://wa.me/6285891111795?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka'; @endphp
    @include('partials.site-header', ['waLink' => $waLink, 'home' => route('landing'), 'solid' => true])

    <main class="pt-[96px] sm:pt-[100px] lg:pt-[104px]">
        <article>
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-[1200px] px-5 py-8 sm:px-6 sm:py-11 lg:py-14">
                    <nav class="flex items-center gap-2 text-[12px] font-extrabold uppercase tracking-[.12em] text-slate-400" aria-label="Breadcrumb">
                        <a href="{{ route('articles.index') }}" class="transition hover:text-[#15803D]">Artikel</a><span>/</span><span class="text-[#16A34A]">{{ $article['category'] }}</span>
                    </nav>
                    <figure class="mt-5 overflow-hidden rounded-[22px] bg-slate-100 sm:mt-7 lg:rounded-[26px]">
                        <img src="{{ asset($article['image']) }}" alt="{{ $article['keyword'] }}" class="aspect-[16/8.5] w-full object-cover">
                    </figure>
                    <div class="mt-7 grid gap-6 border-b border-slate-200 pb-8 sm:mt-9 sm:pb-10 lg:grid-cols-[minmax(0,1fr)_260px] lg:items-end">
                        <div>
                            <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-[.1em] text-[#15803D]">{{ $article['category'] }}</span>
                            <h1 class="mt-4 max-w-[900px] font-['Plus_Jakarta_Sans'] text-[32px] font-extrabold leading-[1.13] tracking-[-.035em] sm:text-[44px] lg:text-[50px]">{{ $article['title'] }}</h1>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px] font-bold text-slate-500 lg:block lg:space-y-2 lg:border-l lg:border-slate-200 lg:pl-6">
                            <p class="text-[#0B1B3F]">Oleh Tim Yoka POS</p>
                            <time class="block" datetime="{{ $article['published'] }}">{{ \Illuminate\Support\Carbon::parse($article['published'])->locale('id')->translatedFormat('d F Y') }}</time>
                            <p>{{ $article['minutes'] }} menit baca</p>
                        </div>
                    </div>
                    <p class="mt-7 max-w-[850px] font-['Plus_Jakarta_Sans'] text-[18px] font-semibold leading-[1.65] text-slate-600 sm:mt-9 sm:text-[21px]">{{ $article['description'] }}</p>
                </div>
            </header>

            <div class="mx-auto grid max-w-[1100px] gap-10 px-5 py-10 sm:px-6 sm:py-14 lg:grid-cols-[minmax(0,720px)_260px] lg:gap-20 lg:py-20">
                <div>
                    <p class="border-l-4 border-[#16A34A] pl-5 font-['Plus_Jakarta_Sans'] text-[18px] font-semibold leading-[1.75] text-[#0B1B3F] sm:pl-6 sm:text-[20px]">{{ $article['intro'] }}</p>

                    @if (count($article['sections']) > 2)
                        <nav class="mt-8 rounded-[18px] border border-slate-200 bg-white p-5 shadow-[0_12px_30px_-28px_rgba(11,27,63,.45)] lg:hidden" aria-label="Daftar isi">
                            <p class="text-[11px] font-extrabold uppercase tracking-[.14em] text-slate-400">Daftar isi</p>
                            <ol class="mt-3 space-y-2.5 text-[14px]">
                                @foreach ($article['sections'] as $i => $s)
                                    <li><a href="#bagian-{{ $i + 1 }}" class="font-bold text-[#15803D] hover:underline">{{ $s['h'] }}</a></li>
                                @endforeach
                            </ol>
                        </nav>
                    @endif

                @foreach ($article['sections'] as $i => $s)
                    <section id="bagian-{{ $i + 1 }}" class="mt-11 scroll-mt-28 sm:mt-14">
                        <h2 class="font-['Plus_Jakarta_Sans'] text-[23px] font-extrabold leading-snug tracking-[-.025em] text-[#0B1B3F] sm:text-[28px]">{{ $s['h'] }}</h2>
                        @foreach ($s['p'] ?? [] as $p)
                            <p class="mt-4 text-[16px] leading-[1.8] text-slate-600 sm:text-[17px]">{{ $p }}</p>
                        @endforeach
                        @if (! empty($s['list']))
                            @if (! empty($s['ol']))
                                <ol class="mt-5 list-decimal space-y-3 pl-6 text-[16px] leading-[1.75] text-slate-600 marker:font-bold marker:text-[#16A34A] sm:text-[17px]">
                                    @foreach ($s['list'] as $item)<li class="pl-1">{{ $item }}</li>@endforeach
                                </ol>
                            @else
                                <ul class="mt-5 space-y-3 text-[16px] leading-[1.75] text-slate-600 sm:text-[17px]">
                                    @foreach ($s['list'] as $item)
                                        <li class="flex gap-3"><span class="mt-[11px] h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-600"></span><span>{{ $item }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </section>
                @endforeach

                <section class="mt-14 border-t border-slate-200 pt-10">
                    <h2 class="font-['Plus_Jakarta_Sans'] text-[23px] font-extrabold tracking-[-.025em] sm:text-[28px]">Pertanyaan umum</h2>
                    <div class="mt-4 divide-y divide-slate-200 border-y border-slate-200">
                        @foreach ($article['faq'] as [$q, $a])
                            <details class="group py-4">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-[16px] sm:text-[17px] font-bold text-slate-900">
                                    {{ $q }}
                                    <span class="text-emerald-600 transition-transform group-open:rotate-45 text-xl leading-none" aria-hidden="true">+</span>
                                </summary>
                                <p class="mt-2 text-[15px] sm:text-[16px] leading-relaxed text-slate-600">{{ $a }}</p>
                            </details>
                        @endforeach
                    </div>
                </section>

                <section class="mt-10">
                    <p class="text-[13px] font-bold uppercase tracking-[.12em] text-slate-500">Baca juga</p>
                    <ul class="mt-3 space-y-2">
                        @foreach ($article['links'] as [$label, $route, $param])
                            <li><a href="{{ route($route, $param) }}" class="text-[16px] font-bold text-emerald-700 hover:underline">{{ $label }} &rarr;</a></li>
                        @endforeach
                    </ul>
                </section>

                <div class="mt-12 overflow-hidden rounded-[22px] bg-[#0B1B3F] p-6 text-white sm:p-9">
                    <p class="text-[11px] font-extrabold uppercase tracking-[.14em] text-emerald-400">Mulai dari tokomu</p>
                    <h2 class="mt-3 font-['Plus_Jakarta_Sans'] text-[22px] font-extrabold leading-tight tracking-[-.02em] sm:text-[27px]">Kelola tokomu lebih rapi dengan Yoka POS</h2>
                    <p class="mt-3 text-[15px] leading-relaxed text-white/70 sm:text-[16px]">Kasir, stok, shift, dan laporan dalam satu aplikasi. Trial gratis 7 hari.</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('register') }}" class="rounded-full bg-[#16A34A] px-6 py-3 text-[14px] font-extrabold text-white hover:bg-[#15803D]">Coba gratis</a>
                        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="rounded-full border border-white/30 px-6 py-3 text-[14px] font-extrabold text-white hover:bg-white hover:text-[#0B1B3F]">Tanya via WhatsApp</a>
                    </div>
                </div>
                </div>

                <aside class="hidden lg:block">
                    <div class="sticky top-28 space-y-8">
                        @if (count($article['sections']) > 2)
                            <nav class="border-t-2 border-[#0B1B3F] pt-4" aria-label="Daftar isi">
                                <p class="text-[11px] font-extrabold uppercase tracking-[.14em] text-slate-400">Dalam artikel ini</p>
                                <ol class="mt-4 space-y-3 text-[13px] leading-snug">
                                    @foreach ($article['sections'] as $i => $s)
                                        <li><a href="#bagian-{{ $i + 1 }}" class="font-bold text-slate-600 transition hover:text-[#15803D]">{{ $s['h'] }}</a></li>
                                    @endforeach
                                </ol>
                            </nav>
                        @endif
                        <div class="border-t border-slate-200 pt-5">
                            <p class="text-[11px] font-extrabold uppercase tracking-[.14em] text-slate-400">Bagikan artikel</p>
                            <div class="mt-3 flex gap-2">
                                <a href="https://wa.me/?text={{ urlencode($article['title'].' '.route('articles.show', $slug)) }}" target="_blank" rel="noopener" class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-[11px] font-extrabold text-slate-600 hover:border-emerald-300 hover:text-[#15803D]" aria-label="Bagikan ke WhatsApp">WA</a>
                                <button type="button" onclick="navigator.clipboard.writeText(window.location.href)" class="flex h-9 items-center justify-center rounded-full border border-slate-200 bg-white px-3 text-[11px] font-extrabold text-slate-600 hover:border-emerald-300 hover:text-[#15803D]">Salin tautan</button>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </article>

        <section class="border-t border-slate-200 bg-white">
            <div class="max-w-[1200px] mx-auto px-5 sm:px-6 py-10 sm:py-14">
                <p class="text-[11px] font-extrabold uppercase tracking-[.14em] text-[#16A34A]">Lanjut membaca</p>
                <h2 class="mt-2 font-['Plus_Jakarta_Sans'] text-[24px] font-extrabold tracking-[-.025em] sm:text-[30px]">Artikel lainnya</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $rSlug => $r)
                        <a href="{{ route('articles.show', $rSlug) }}" class="group overflow-hidden rounded-[18px] border border-slate-200 bg-white hover:border-emerald-300">
                            <span class="block aspect-[16/9] overflow-hidden"><img src="{{ asset($r['image']) }}" alt="{{ $r['title'] }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.035]"></span>
                            <span class="block p-5"><span class="text-[11px] font-extrabold uppercase tracking-[.12em] text-[#16A34A]">{{ $r['category'] }}</span><span class="mt-2 block font-['Plus_Jakarta_Sans'] text-[17px] font-extrabold leading-snug group-hover:text-[#15803D]">{{ $r['title'] }}</span></span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer', ['waLink' => $waLink, 'home' => route('landing')])
    @vite('resources/js/alpine.js')
</body>
</html>
