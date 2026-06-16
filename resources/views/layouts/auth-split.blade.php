<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Masuk') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans bg-white">
    <div class="min-h-screen grid lg:grid-cols-2">

        {{-- Left — branding / pitch panel --}}
        <div class="relative hidden lg:flex flex-col justify-center px-16 py-12 bg-gradient-to-br from-emerald-700 via-emerald-600 to-emerald-800 text-white overflow-hidden">
            <div class="absolute -top-24 -left-20 w-80 h-80 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -bottom-28 -right-16 w-96 h-96 rounded-full bg-emerald-400/30 blur-3xl"></div>

            <div class="relative z-10">
                <a href="{{ route('landing') }}" class="inline-flex items-center gap-2 mb-10">
                    <div class="w-10 h-10 rounded-xl bg-white/15 backdrop-blur flex items-center justify-center font-extrabold">F</div>
                    <span class="text-xl font-extrabold tracking-tight">FNB<span class="text-emerald-200">POS</span></span>
                </a>

                <h1 class="text-3xl xl:text-4xl font-extrabold leading-tight mb-4">
                    @yield('left-title', 'Kelola Bisnis F&B Anda Lebih Mudah dalam Satu Sistem')
                </h1>
                <p class="text-emerald-50/90 text-sm leading-relaxed mb-10 max-w-md">
                    @yield('left-subtitle', 'Kasir, stok, shift, QRIS, hingga laporan profit — semuanya terhubung dan siap dipakai kapan saja.')
                </p>

                {{-- Mini dashboard mockup --}}
                <div class="rounded-2xl bg-white/10 border border-white/15 backdrop-blur p-5 max-w-md">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs text-emerald-100/80">Ringkasan Hari Ini</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-white/15">● Live</span>
                    </div>
                    <p class="text-2xl font-extrabold mb-1">Rp 6.840.000</p>
                    <p class="text-xs text-emerald-100/80 mb-4">▲ 22% dari kemarin · 128 transaksi</p>
                    <div class="flex items-end gap-1.5 h-16">
                        @foreach ([45,65,50,80,60,90,100] as $h)
                            <div class="flex-1 rounded-t-md bg-white/30" style="height: {{ $h }}%"></div>
                        @endforeach
                    </div>
                </div>

                <ul class="mt-10 space-y-3 text-sm text-emerald-50/90">
                    @foreach ([
                        'Coba gratis 7 hari, semua fitur terbuka penuh',
                        'Tanpa kartu kredit, tanpa biaya tersembunyi',
                        'Siap pakai untuk cafe, resto, bakery & UMKM F&B',
                    ] as $point)
                        <li class="flex items-center gap-3">
                            <span class="w-5 h-5 rounded-full bg-white/15 flex items-center justify-center text-xs">✓</span>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Right — form panel --}}
        <div class="flex flex-col justify-center px-6 sm:px-12 lg:px-20 py-12 bg-slate-50 lg:bg-white">
            <div class="w-full max-w-md mx-auto">
                <div class="lg:hidden flex items-center gap-2 mb-8">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center text-white font-bold">F</div>
                    <span class="text-lg font-extrabold tracking-tight text-slate-900">FNB<span class="text-emerald-600">POS</span></span>
                </div>

                @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3">
                    <ul class="text-sm space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @if(session('info'))
                <div class="mb-4 bg-blue-50 border border-blue-200 text-blue-700 rounded-xl px-4 py-3 text-sm">
                    {{ session('info') }}
                </div>
                @endif

                @if(session('success'))
                <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
