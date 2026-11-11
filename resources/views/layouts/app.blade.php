<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
</head>
<body class="h-full bg-gray-50 font-sans" x-data="{
        sidebarOpen: false,
        collapsed: (() => { try { return localStorage.getItem('yoka.sidebar') === '1' } catch (e) { return false } })(),
        tip: { show: false, text: '', y: 0 },
        toggle() { this.collapsed = !this.collapsed; try { localStorage.setItem('yoka.sidebar', this.collapsed ? '1' : '0') } catch (e) {} this.tip.show = false },
        tipShow(e, text) { if (!this.collapsed || window.innerWidth < 1024) return; const r = e.currentTarget.getBoundingClientRect(); this.tip = { show: true, text, y: r.top + r.height / 2 } },
        tipHide() { this.tip.show = false }
    }">
    <x-page-loader/>

    {{-- Mobile sidebar backdrop --}}
    <div x-show="sidebarOpen" x-cloak
         @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-[260px] bg-white border-r border-[#E5E7EB] flex flex-col -translate-x-full lg:translate-x-0 transition-[width,transform] duration-200"
           :class="[sidebarOpen ? '!translate-x-0' : '', collapsed ? 'lg:w-[72px]' : 'lg:w-[260px]']">

        {{-- Logo --}}
        <div class="flex items-center h-14 px-5 shrink-0" :class="collapsed ? 'lg:justify-center lg:px-0' : ''">
            <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka POS" class="h-8 w-auto" :class="collapsed ? 'lg:hidden' : ''">
            <img src="{{ asset('images/brand/yoka-icon.png') }}" alt="Yoka" class="hidden w-8 h-8 object-contain" :class="collapsed ? 'lg:block' : ''">
        </div>

        {{-- Business / outlet --}}
        @php $outletName = auth()->user()->outlet?->name ?? 'Semua Outlet'; @endphp
        <div class="px-3 pb-2 shrink-0" :class="collapsed ? 'lg:px-4' : ''">
            <div class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] px-2.5 h-11"
                 :class="collapsed ? 'lg:justify-center lg:px-0 lg:h-10 lg:border-0' : ''"
                 @mouseenter="tipShow($event, @js(($currentBusiness->name ?? config('app.name')).' · '.$outletName))" @mouseleave="tipHide()">
                <svg class="w-6 h-6 text-gray-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l1.5-5h15L21 9M3 9v11a1 1 0 001 1h16a1 1 0 001-1V9M3 9h18M9 21v-6h6v6"/></svg>
                <div class="min-w-0 flex-1" :class="collapsed ? 'lg:hidden' : ''">
                    <p class="text-[13px] font-bold text-gray-900 leading-tight truncate">{{ $currentBusiness->name ?? config('app.name') }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ $outletName }}</p>
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto overflow-x-hidden px-3 py-2 space-y-px [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" :class="collapsed ? 'lg:px-4' : ''">
            @php
                $role     = auth()->user()->role;
                $isRetail = (bool) auth()->user()->business?->isRetail();
            @endphp

            {{-- Utama --}}
            <x-nav-item route="dashboard" icon="home">Dashboard</x-nav-item>
            @if(in_array($role, ['owner','admin','cashier']))
            <x-nav-item route="pos.index" icon="shopping-cart">Kasir / POS</x-nav-item>
            <x-nav-item route="orders.index" icon="clipboard-list">Riwayat Order</x-nav-item>
            <x-nav-item route="shifts.index" icon="clock">Shift Kasir</x-nav-item>
            @endif

            @if(in_array($role, ['owner','admin']))
            <x-nav-group title="Master Data" :routes="['products.*','bundles.*','categories.*','customers.*','promotions.*']">
                <x-nav-item route="products.index" icon="cube">{{ $isRetail ? 'Produk' : 'Produk / Menu' }}</x-nav-item>
                <x-nav-item route="bundles.index" icon="squares-plus">Bundling Produk</x-nav-item>
                <x-nav-item route="categories.index" icon="tag">Kategori</x-nav-item>
                <x-nav-item route="customers.index" icon="users">Pelanggan</x-nav-item>
                <x-nav-item route="promotions.index" icon="badge-percent">Promo & Diskon</x-nav-item>
            </x-nav-group>
            @endif

            @if(!$isRetail && in_array($role, ['owner','admin','kitchen','warehouse']))
            <x-nav-group title="Operasional" :routes="['kitchen.*','ingredients.*','recipes.*','inventory.*']">
                @if(in_array($role, ['owner','admin','kitchen']))
                <x-nav-item route="kitchen.index" icon="fire">Dapur (KDS)</x-nav-item>
                @endif
                @if(in_array($role, ['owner','admin','warehouse']))
                <x-nav-item route="ingredients.index" icon="beaker">Bahan Baku</x-nav-item>
                <x-nav-item route="recipes.index" icon="book-open">Resep</x-nav-item>
                <x-nav-item route="inventory.movements" icon="arrows-right-left">Pergerakan Stok</x-nav-item>
                @endif
            </x-nav-group>
            @endif

            @if(in_array($role, ['owner','admin']))
            <x-nav-group title="Laporan" :routes="['reports.*']">
                <x-nav-item route="reports.sales" icon="chart-bar">Laporan Penjualan</x-nav-item>
                <x-nav-item route="reports.products" icon="chart-pie">Produk Terlaris</x-nav-item>
                <x-nav-item route="reports.cashier" icon="user-circle">Laporan Kasir</x-nav-item>
                <x-nav-item route="reports.shift" icon="clock-rotate-left">Laporan Shift</x-nav-item>
                <x-nav-item route="reports.inventory" icon="archive">Laporan Inventory</x-nav-item>
                <x-nav-item route="reports.profit" icon="banknotes">Estimasi Profit</x-nav-item>
            </x-nav-group>

            <x-nav-group title="Pengaturan" :routes="['outlets.*','users.*','settings.*','audit.*']">
                <x-nav-item route="outlets.index" icon="building-storefront">Outlet</x-nav-item>
                <x-nav-item route="users.index" icon="user-group">Manajemen User</x-nav-item>
                <x-nav-item route="settings.business" icon="building-office">Profil Bisnis</x-nav-item>
                <x-nav-item route="settings.receipt" icon="receipt-percent">Pengaturan Struk</x-nav-item>
                @if(config('pos.features.audit_log'))
                <x-nav-item route="audit.index" icon="clipboard-list">Audit Log</x-nav-item>
                @endif
            </x-nav-group>
            @endif

            @if($role === 'owner' && (config('pos.features.subscription') || config('pos.features.balance')))
            <x-nav-group title="Langganan & Saldo" :routes="['saas.*','balance.*','admin.withdrawals.*']">
                @if(config('pos.features.subscription'))
                <x-nav-item route="saas.plans" icon="credit-card">Paket Langganan</x-nav-item>
                @endif
                @if(config('pos.features.balance'))
                <x-nav-item route="balance.index" icon="banknotes">Saldo & Penarikan</x-nav-item>
                <x-nav-item route="admin.withdrawals.index" icon="queue-list">Kelola WD</x-nav-item>
                @endif
            </x-nav-group>
            @endif
        </nav>

        <div class="px-5 py-3 border-t border-[#E5E7EB] shrink-0 text-[11px] font-medium text-gray-400" :class="collapsed ? 'lg:hidden' : ''">
            Yoka POS &copy; {{ now()->year }}
        </div>

    </aside>

    {{-- Main content --}}
    <div class="transition-all duration-200 flex flex-col min-h-screen min-w-0 overflow-x-clip" :class="collapsed ? 'lg:pl-[72px]' : 'lg:pl-[260px]'">

        {{-- Topbar --}}
        <header class="sticky top-0 z-20 bg-white border-b border-gray-200 px-3 sm:px-4 py-3 flex items-center gap-2 sm:gap-4">
            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 -ml-1 rounded-lg hover:bg-gray-100 shrink-0" aria-label="Menu">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <button @click="toggle()" class="hidden lg:inline-flex p-2 -ml-1 rounded-lg text-gray-500 hover:bg-[#F4F6F8] shrink-0" :aria-label="collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'" :title="collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h16"/></svg>
            </button>
            <div class="flex-1 min-w-0">
                <h1 class="text-base sm:text-lg font-semibold text-gray-900 truncate">@yield('page-title', 'Dashboard')</h1>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                {{-- Date --}}
                <span class="hidden sm:inline text-sm text-gray-500">{{ now()->isoFormat('dddd, D MMMM Y') }}</span>

                {{-- Active shift indicator --}}
                @php
                    $activeShift = auth()->user()->activeShift()->first();
                @endphp
                @if($activeShift)
                <a href="{{ route('shifts.show', $activeShift) }}"
                   class="hidden sm:flex items-center gap-1.5 text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full px-3 py-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Shift Aktif
                </a>
                @endif

                {{-- Profil & logout --}}
                <div class="relative" x-data="{ menu: false }" @click.outside="menu = false" @keydown.escape.window="menu = false">
                    <button @click="menu = !menu" class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center" aria-label="Menu akun">
                        <span class="text-sm font-semibold text-emerald-700">{{ substr(auth()->user()->name, 0, 1) }}</span>
                    </button>
                    <div x-show="menu" x-cloak x-transition.opacity.duration.100ms
                         class="absolute right-0 mt-2 w-56 rounded-xl bg-white border border-[#E5E7EB] shadow-lg py-1 z-30">
                        <div class="px-3 py-2 border-b border-[#E5E7EB]">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-500 capitalize">{{ auth()->user()->role }}</p>
                        </div>
                        <a href="{{ route('profile') }}" class="block px-3 py-2 text-sm font-medium text-gray-700 hover:bg-[#F4F6F8]">Profil Saya</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Keluar</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- Flash messages --}}
        <div class="px-4 sm:px-6 pt-4">
            @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 mb-4">
                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm">{{ session('success') }}</span>
                <button @click="show = false" class="ml-auto text-emerald-400 hover:text-emerald-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            @endif

            @if(session('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                 class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 mb-4">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                <span class="text-sm">{{ session('error') }}</span>
                <button @click="show = false" class="ml-auto text-red-400 hover:text-red-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            @endif

            @if(session('info'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="flex items-center gap-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-xl px-4 py-3 mb-4">
                <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-sm">{{ session('info') }}</span>
            </div>
            @endif

            @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 mb-4">
                <p class="text-sm font-medium mb-1">Terjadi kesalahan:</p>
                <ul class="list-disc list-inside text-sm space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>

        {{-- Page content --}}
        <main class="flex-1 min-w-0 px-4 sm:px-6 pb-8">
            @yield('content')
        </main>
    </div>

    {{-- Tooltip sidebar collapsed --}}
    <div x-show="tip.show" x-cloak x-transition.opacity.duration.100ms
         class="hidden lg:block fixed z-50 pointer-events-none px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-medium shadow-lg whitespace-nowrap"
         :style="`left: 80px; top: ${tip.y}px; transform: translateY(-50%)`" x-text="tip.text"></div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    @stack('scripts')
</body>
</html>
