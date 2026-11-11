<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Platform') — Yoka</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
</head>
<body class="min-h-full bg-slate-100 text-slate-900 antialiased" x-data="{ menu: false }">
    @php $platformAdmin = auth('platform')->user(); @endphp

    <header class="fixed inset-x-0 top-0 z-40 h-16 border-b border-emerald-950/30 bg-emerald-950 text-white">
        <div class="flex h-full items-center px-4 lg:px-7">
            <button type="button" class="mr-3 p-2 lg:hidden" @click="menu = !menu" aria-label="Buka menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="{{ route('platform.dashboard') }}" class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center bg-white"><img src="{{ asset('images/brand/yoka-icon.png') }}" class="h-7 w-7" alt=""></span>
                <span>
                    <span class="block text-sm font-black tracking-wide">YOKA PLATFORM</span>
                    <span class="block text-[10px] uppercase tracking-[0.18em] text-emerald-200">Internal Operations</span>
                </span>
            </a>
            <div class="ml-auto flex items-center gap-4">
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-semibold">{{ $platformAdmin->name }}</p>
                    <p class="text-[11px] uppercase tracking-wider text-emerald-200">{{ str_replace('_', ' ', $platformAdmin->role) }}</p>
                </div>
                <form method="POST" action="{{ route('platform.logout') }}">
                    @csrf
                    <button class="border border-white/25 px-3 py-2 text-xs font-bold hover:bg-white/10">Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <div x-show="menu" x-cloak class="fixed inset-0 z-20 bg-black/40 lg:hidden" @click="menu = false"></div>
    <aside class="fixed bottom-0 left-0 top-16 z-30 w-64 border-r border-slate-200 bg-white p-4 transition-transform lg:translate-x-0" :class="menu ? 'translate-x-0' : '-translate-x-full'">
        <nav class="space-y-1 text-sm font-semibold">
            <a href="{{ route('platform.dashboard') }}" class="flex items-center gap-3 px-3 py-3 {{ request()->routeIs('platform.dashboard') ? 'bg-emerald-100 text-emerald-900' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="h-2 w-2 bg-emerald-600"></span> Ringkasan Platform
            </a>
            <a href="{{ route('platform.businesses.index') }}" class="flex items-center gap-3 px-3 py-3 {{ request()->routeIs('platform.businesses.*') ? 'bg-emerald-100 text-emerald-900' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="h-2 w-2 bg-sky-600"></span> Semua Toko
            </a>
            @if($platformAdmin->canManageWithdrawals())
                <a href="{{ route('platform.withdrawals.index') }}" class="flex items-center gap-3 px-3 py-3 {{ request()->routeIs('platform.withdrawals.*') ? 'bg-emerald-100 text-emerald-900' : 'text-slate-600 hover:bg-slate-100' }}">
                    <span class="h-2 w-2 bg-amber-500"></span> Antrean Penarikan
                </a>
            @endif
            <a href="{{ route('platform.activity') }}" class="flex items-center gap-3 px-3 py-3 {{ request()->routeIs('platform.activity') ? 'bg-emerald-100 text-emerald-900' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="h-2 w-2 bg-violet-600"></span> Aktivitas Tim
            </a>
        </nav>

        <div class="absolute bottom-5 left-4 right-4 border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed text-slate-500">
            Data lintas tenant bersifat internal. Buka data pelanggan hanya saat diperlukan untuk bantuan.
        </div>
    </aside>

    <main class="min-h-screen pt-16 lg:pl-64">
        <div class="p-4 sm:p-6 lg:p-8">
            @if(session('success'))
                <div class="mb-5 border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-5 border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </div>
    </main>
</body>
</html>
