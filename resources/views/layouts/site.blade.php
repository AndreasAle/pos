<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo-meta', ['seoTitle' => $seoTitle, 'seoDescription' => $seoDescription])
    @include('partials.brand-schema')
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
</head>
<body class="bg-white text-slate-800 antialiased">
    @include('partials.site-header', ['waLink' => $waLink, 'home' => route('landing'), 'solid' => $solidHeader ?? false])

    <main>
        @yield('content')
    </main>

    @include('partials.site-footer', ['waLink' => $waLink, 'home' => route('landing')])

    <a href="{{ $waLink }}" target="_blank" rel="noopener"
       class="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-full bg-emerald-600 px-5 py-3.5 font-semibold text-white shadow-2xl shadow-emerald-300 transition-all hover:-translate-y-0.5 hover:bg-emerald-700">
        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.79.47 3.47 1.36 4.95L2 22l5.27-1.38a9.9 9.9 0 0 0 4.77 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2z"/></svg>
        <span class="hidden sm:inline">Chat WhatsApp</span>
    </a>
    @vite('resources/js/alpine.js')
</body>
</html>
