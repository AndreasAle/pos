<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Masuk') — {{ config('app.name') }}</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
    <style>
        .auth-form input:not([type=checkbox]):not([type=radio]) { height: 3rem; border-radius: .75rem; border-color: #cbd5e1; font-size: .9375rem; }
        .auth-form input:not([type=checkbox]):not([type=radio]):focus { border-color: #059669; box-shadow: 0 0 0 3px rgba(5,150,105,.18); outline: none; }
    </style>
</head>
<body class="h-full font-sans bg-white">
    <x-page-loader/>
    <div class="min-h-screen grid lg:grid-cols-2">

        {{-- Left — panel merek --}}
        <div class="relative hidden lg:flex flex-col px-14 pt-10 bg-[#0c874e] text-white overflow-hidden lg:sticky lg:top-0 lg:h-screen self-start">
            <a href="{{ route('landing') }}" class="relative z-10 inline-flex items-center gap-2" aria-label="Yoka POS">
                <img src="{{ asset('images/brand/yoka-logo-white.png') }}" alt="Yoka" class="h-9 w-auto">
                <span class="text-[22px] leading-none font-bold tracking-tight">POS</span>
            </a>

            <div class="relative z-10 mt-16 max-w-md">
                <h1 class="text-[34px] xl:text-[40px] font-extrabold leading-[1.15] mb-4">
                    @yield('left-title', 'Kasir, Stok, dan Laporan dalam Satu Aplikasi')
                </h1>
                <p class="text-white/90 text-base leading-relaxed">
                    @yield('left-subtitle', 'Masuk untuk lanjut melayani pelanggan dan memantau toko Anda.')
                </p>
            </div>

            <img src="{{ asset('images/hero/slide-owner.jpg') }}" alt="" aria-hidden="true"
                 class="absolute bottom-0 right-0 w-[150%] max-w-none select-none"
                 style="-webkit-mask-image:linear-gradient(to right,transparent 0,#000 10%);mask-image:linear-gradient(to right,transparent 0,#000 10%)">
        </div>

        {{-- Right — form panel --}}
        <div class="relative flex flex-col justify-center px-6 sm:px-12 lg:px-20 py-12 bg-white">
            <div class="absolute right-5 top-5"><x-lang-switch variant="light" /></div>
            <div class="w-full max-w-md mx-auto">
                <div class="lg:hidden flex items-center gap-2 mb-8">
                    <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka" class="h-8 w-auto">
                    <span class="text-xl leading-none font-bold tracking-tight text-emerald-700">POS</span>
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

                <div class="auth-form">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
