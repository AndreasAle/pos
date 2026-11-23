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
<body class="bg-white text-slate-800 antialiased">
    @php $waLink = 'https://wa.me/6285891111795?text=Halo%20saya%20tertarik%20dengan%20aplikasi%20POS%20Yoka'; @endphp
    @include('partials.site-header', ['waLink' => $waLink, 'home' => route('landing')])

    <main>
        <article>
            <header class="bg-emerald-600 text-white pt-[96px] sm:pt-[100px] lg:pt-[104px]">
                <div class="max-w-[760px] mx-auto px-5 sm:px-6 py-10 sm:py-14">
                    <nav class="text-sm font-semibold text-emerald-100" aria-label="Breadcrumb">
                        <a href="{{ route('articles.index') }}" class="hover:text-white">Artikel</a>
                        <span class="mx-1.5">/</span>
                        <span>{{ $article['category'] }}</span>
                    </nav>
                    <h1 class="mt-3 text-[30px] sm:text-[40px] font-extrabold leading-[1.12] tracking-[-.03em]">{{ $article['title'] }}</h1>
                    <p class="mt-5 flex flex-wrap gap-x-5 gap-y-1 text-sm font-semibold text-emerald-100">
                        <span>Tim Yoka POS</span>
                        <time datetime="{{ $article['published'] }}">{{ \Illuminate\Support\Carbon::parse($article['published'])->locale('id')->translatedFormat('d F Y') }}</time>
                        <span>{{ $article['minutes'] }} menit baca</span>
                    </p>
                </div>
            </header>

            <div class="max-w-[760px] mx-auto px-5 sm:px-6 py-10 sm:py-12">
                <img src="{{ asset($article['image']) }}" alt="{{ $article['keyword'] }}" class="aspect-[16/9] w-full rounded-2xl object-cover">

                <p class="mt-8 text-[18px] sm:text-[20px] leading-[1.65] font-medium text-slate-800">{{ $article['intro'] }}</p>

                @if (count($article['sections']) > 2)
                    <nav class="mt-8 rounded-2xl bg-slate-50 p-5 sm:p-6" aria-label="Daftar isi">
                        <p class="text-[13px] font-bold uppercase tracking-[.12em] text-slate-500">Daftar isi</p>
                        <ol class="mt-3 space-y-2 text-[15px] sm:text-[16px]">
                            @foreach ($article['sections'] as $i => $s)
                                <li><a href="#bagian-{{ $i + 1 }}" class="font-semibold text-emerald-700 hover:underline">{{ $s['h'] }}</a></li>
                            @endforeach
                        </ol>
                    </nav>
                @endif

                @foreach ($article['sections'] as $i => $s)
                    <section id="bagian-{{ $i + 1 }}" class="mt-10 scroll-mt-24">
                        <h2 class="text-[22px] sm:text-[26px] font-extrabold leading-snug tracking-[-.02em] text-slate-900">{{ $s['h'] }}</h2>
                        @foreach ($s['p'] ?? [] as $p)
                            <p class="mt-4 text-[16px] sm:text-[17px] leading-[1.75] text-slate-700">{{ $p }}</p>
                        @endforeach
                        @if (! empty($s['list']))
                            @if (! empty($s['ol']))
                                <ol class="mt-4 list-decimal space-y-2 pl-6 text-[16px] sm:text-[17px] leading-[1.7] text-slate-700 marker:font-bold marker:text-emerald-700">
                                    @foreach ($s['list'] as $item)<li class="pl-1">{{ $item }}</li>@endforeach
                                </ol>
                            @else
                                <ul class="mt-4 space-y-2 text-[16px] sm:text-[17px] leading-[1.7] text-slate-700">
                                    @foreach ($s['list'] as $item)
                                        <li class="flex gap-3"><span class="mt-[11px] h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-600"></span><span>{{ $item }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </section>
                @endforeach

                <section class="mt-12">
                    <h2 class="text-[22px] sm:text-[26px] font-extrabold tracking-[-.02em] text-slate-900">Pertanyaan umum</h2>
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

                <div class="mt-12 rounded-2xl bg-emerald-600 p-6 sm:p-8 text-white">
                    <h2 class="text-[22px] sm:text-[26px] font-extrabold leading-tight tracking-[-.02em]">Kelola tokomu lebih rapi dengan Yoka POS</h2>
                    <p class="mt-2 text-[15px] sm:text-[16px] text-emerald-50">Kasir, stok, shift, dan laporan dalam satu aplikasi. Trial gratis 7 hari.</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('register') }}" class="rounded-full bg-white px-6 py-3 text-[15px] font-bold text-emerald-700 hover:bg-emerald-50">Coba Gratis</a>
                        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="rounded-full border-2 border-white/80 px-6 py-3 text-[15px] font-bold text-white hover:bg-white hover:text-emerald-700">Tanya via WhatsApp</a>
                    </div>
                </div>
            </div>
        </article>

        <section class="border-t border-slate-200 bg-slate-50">
            <div class="max-w-[1200px] mx-auto px-5 sm:px-6 py-10 sm:py-14">
                <h2 class="text-[22px] sm:text-[26px] font-extrabold tracking-[-.02em] text-slate-900">Artikel lainnya</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $rSlug => $r)
                        <a href="{{ route('articles.show', $rSlug) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 hover:border-emerald-300">
                            <span class="text-[12px] font-bold uppercase tracking-[.12em] text-emerald-700">{{ $r['category'] }}</span>
                            <span class="mt-1 block text-[17px] font-extrabold leading-snug text-slate-900 group-hover:text-emerald-700">{{ $r['title'] }}</span>
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
