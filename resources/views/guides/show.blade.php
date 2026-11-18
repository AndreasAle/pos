<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $guide['title'] }} — Panduan Yoka POS</title>
    <meta name="description" content="{{ $guide['summary'] }}">
    <link rel="canonical" href="{{ route('guides.show', $slug) }}">
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => $guide['title'],
        'description' => $guide['summary'],
        'totalTime' => 'PT'.$guide['minutes'].'M',
        'step' => collect($guide['steps'])->values()->map(fn ($s, $i) => ['@type' => 'HowToStep', 'position' => $i + 1, 'name' => $s[0], 'text' => $s[1]])->all(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
</head>
<body class="bg-white text-slate-800 antialiased">
    @php $waLink = 'https://wa.me/6285891111795?text='.rawurlencode('Halo, saya butuh bantuan: '.$guide['title']); @endphp
    @include('partials.site-header', ['waLink' => $waLink, 'home' => route('landing')])

    <main>
        <article>
            <header class="bg-emerald-600 text-white pt-[60px] sm:pt-[68px] lg:pt-[76px]">
                <div class="max-w-[760px] mx-auto px-5 sm:px-6 py-10 sm:py-14">
                    <nav class="text-sm font-semibold text-emerald-100" aria-label="Breadcrumb">
                        <a href="{{ route('guides.index') }}" class="hover:text-white">Panduan</a>
                        <span class="mx-1.5">/</span>
                        <span>{{ $guide['category'] }}</span>
                    </nav>
                    <h1 class="mt-3 text-[30px] sm:text-[40px] font-extrabold leading-[1.1] tracking-[-.03em]">{{ $guide['title'] }}</h1>
                    <p class="mt-3 text-[16px] sm:text-[18px] leading-relaxed text-emerald-50">{{ $guide['summary'] }}</p>
                    <p class="mt-5 flex flex-wrap gap-x-5 gap-y-1 text-sm font-semibold text-emerald-100">
                        <span>{{ $guide['minutes'] }} menit</span>
                        <span>{{ count($guide['steps']) }} langkah</span>
                        <span>Panduan {{ $number }} dari {{ $total }}</span>
                    </p>
                </div>
            </header>

            <div class="max-w-[760px] mx-auto px-5 sm:px-6 py-10 sm:py-14">
                <ol class="space-y-8 sm:space-y-10">
                    @foreach ($guide['steps'] as [$title, $body, $tip])
                        <li class="flex gap-4 sm:gap-5">
                            <span class="flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[16px] font-extrabold text-white">{{ $loop->iteration }}</span>
                            <div class="min-w-0 pt-1">
                                <h2 class="text-[19px] sm:text-[21px] font-extrabold leading-snug text-slate-900">{{ $title }}</h2>
                                <p class="mt-2 text-[16px] sm:text-[17px] leading-[1.7] text-slate-700">{{ $body }}</p>
                                @if ($tip)
                                    <p class="mt-3 rounded-xl bg-emerald-50 px-4 py-3 text-[15px] leading-relaxed text-emerald-900"><span class="font-bold">Tips:</span> {{ $tip }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                @if (! empty($guide['faq']))
                    <section class="mt-12 sm:mt-14">
                        <h2 class="text-[22px] sm:text-[26px] font-extrabold tracking-[-.02em] text-slate-900">Pertanyaan umum</h2>
                        <div class="mt-4 divide-y divide-slate-200 border-y border-slate-200">
                            @foreach ($guide['faq'] as [$q, $a])
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
                @endif

                <div class="mt-12 rounded-2xl bg-emerald-600 p-6 sm:p-8 text-white">
                    <h2 class="text-[22px] sm:text-[26px] font-extrabold leading-tight tracking-[-.02em]">Langsung praktik di tokomu</h2>
                    <p class="mt-2 text-[15px] sm:text-[16px] text-emerald-50">Trial gratis 7 hari, tanpa kartu kredit.</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('register') }}" class="rounded-full bg-white px-6 py-3 text-[15px] font-bold text-emerald-700 hover:bg-emerald-50">Coba Gratis</a>
                        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="rounded-full border-2 border-white/80 px-6 py-3 text-[15px] font-bold text-white hover:bg-white hover:text-emerald-700">Tanya via WhatsApp</a>
                    </div>
                </div>

                <nav class="mt-10 grid gap-3 sm:grid-cols-2" aria-label="Panduan lain">
                    @if ($prev)
                        <a href="{{ route('guides.show', $prev[0]) }}" class="rounded-2xl border border-slate-200 p-5 hover:border-emerald-300">
                            <span class="text-[12px] font-bold uppercase tracking-[.12em] text-slate-400">&larr; Sebelumnya</span>
                            <span class="mt-1 block text-[16px] font-bold text-slate-900">{{ $prev[1]['title'] }}</span>
                        </a>
                    @else
                        <span class="hidden sm:block"></span>
                    @endif
                    @if ($next)
                        <a href="{{ route('guides.show', $next[0]) }}" class="rounded-2xl border border-slate-200 p-5 text-right hover:border-emerald-300">
                            <span class="text-[12px] font-bold uppercase tracking-[.12em] text-slate-400">Berikutnya &rarr;</span>
                            <span class="mt-1 block text-[16px] font-bold text-slate-900">{{ $next[1]['title'] }}</span>
                        </a>
                    @endif
                </nav>

                <p class="mt-8 text-center"><a href="{{ route('guides.index') }}" class="text-[15px] font-bold text-emerald-700 hover:underline">Lihat semua panduan</a></p>
            </div>
        </article>
    </main>

    @include('partials.site-footer', ['waLink' => $waLink, 'home' => route('landing')])
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
