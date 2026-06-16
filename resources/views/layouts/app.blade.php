<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full bg-gray-50 font-sans" x-data="{ sidebarOpen: false }">

    {{-- Mobile sidebar backdrop --}}
    <div x-show="sidebarOpen" x-cloak
         @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-gray-200 flex flex-col transition-transform duration-200 lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

        {{-- Logo --}}
        <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-200">
            <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900 leading-none">{{ $currentBusiness->name ?? config('app.name') }}</p>
                <p class="text-xs text-gray-500 mt-0.5">FNB POS System</p>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            @php $role = auth()->user()->role; @endphp

            {{-- Dashboard --}}
            <x-nav-item route="dashboard" icon="home">Dashboard</x-nav-item>

            @if(in_array($role, ['owner','admin','cashier']))
            {{-- POS --}}
            <x-nav-item route="pos.index" icon="shopping-cart">Kasir / POS</x-nav-item>
            <x-nav-item route="orders.index" icon="clipboard-list">Riwayat Order</x-nav-item>
            <x-nav-item route="shifts.index" icon="clock">Shift Kasir</x-nav-item>
            @endif

            @if(in_array($role, ['owner','admin']))
            {{-- Master Data --}}
            <div class="pt-3">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Master Data</p>
            </div>
            <x-nav-item route="products.index" icon="cube">Produk / Menu</x-nav-item>
            <x-nav-item route="bundles.index" icon="squares-plus">Bundling Produk</x-nav-item>
            <x-nav-item route="categories.index" icon="tag">Kategori</x-nav-item>
            <x-nav-item route="customers.index" icon="users">Pelanggan</x-nav-item>
            <x-nav-item route="promotions.index" icon="badge-percent">Promo & Diskon</x-nav-item>
            @endif

            @if(in_array($role, ['owner','admin','kitchen']))
            {{-- Operations --}}
            <div class="pt-3">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Operasional</p>
            </div>
            <x-nav-item route="kitchen.index" icon="fire">Dapur (KDS)</x-nav-item>
            @endif

            @if(in_array($role, ['owner','admin','warehouse']))
            <x-nav-item route="ingredients.index" icon="beaker">Bahan Baku</x-nav-item>
            <x-nav-item route="recipes.index" icon="book-open">Resep</x-nav-item>
            <x-nav-item route="inventory.movements" icon="arrows-right-left">Pergerakan Stok</x-nav-item>
            @endif

            @if(in_array($role, ['owner','admin']))
            {{-- Reports --}}
            <div class="pt-3">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Laporan</p>
            </div>
            <x-nav-item route="reports.sales" icon="chart-bar">Laporan Penjualan</x-nav-item>
            <x-nav-item route="reports.products" icon="chart-pie">Produk Terlaris</x-nav-item>
            <x-nav-item route="reports.cashier" icon="user-circle">Laporan Kasir</x-nav-item>
            <x-nav-item route="reports.shift" icon="clock-rotate-left">Laporan Shift</x-nav-item>
            <x-nav-item route="reports.inventory" icon="archive">Laporan Inventory</x-nav-item>
            <x-nav-item route="reports.profit" icon="banknotes">Estimasi Profit</x-nav-item>
            @endif

            @if(in_array($role, ['owner','admin']))
            {{-- Settings --}}
            <div class="pt-3">
                <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Pengaturan</p>
            </div>
            <x-nav-item route="outlets.index" icon="building-storefront">Outlet</x-nav-item>
            <x-nav-item route="users.index" icon="user-group">Manajemen User</x-nav-item>
            <x-nav-item route="settings.business" icon="building-office">Profil Bisnis</x-nav-item>
            <x-nav-item route="settings.receipt" icon="receipt-percent">Pengaturan Struk</x-nav-item>
            @endif

            @if(in_array($role, ['owner','admin']))
            <x-nav-item route="audit.index" icon="clipboard-list">Audit Log</x-nav-item>
            @endif
            @if($role === 'owner')
            <x-nav-item route="saas.plans" icon="credit-card">Paket Langganan</x-nav-item>
            <x-nav-item route="balance.index" icon="banknotes">Saldo & Penarikan</x-nav-item>
            <x-nav-item route="admin.withdrawals.index" icon="queue-list">Kelola WD</x-nav-item>
            @endif
        </nav>

        {{-- User Profile --}}
        <div class="border-t border-gray-200 px-3 py-3">
            <div class="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-gray-50">
                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-sm font-semibold text-emerald-700">{{ substr(auth()->user()->name, 0, 1) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-500 capitalize">{{ auth()->user()->role }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="p-1 text-gray-400 hover:text-red-500 rounded" title="Logout">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main content --}}
    <div class="lg:pl-64 flex flex-col min-h-screen">

        {{-- Topbar --}}
        <header class="sticky top-0 z-20 bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-4">
            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-lg hover:bg-gray-100">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="flex-1">
                <h1 class="text-lg font-semibold text-gray-900">@yield('page-title', 'Dashboard')</h1>
            </div>

            <div class="flex items-center gap-3">
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

                {{-- Profile link --}}
                <a href="{{ route('profile') }}" class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
                    <span class="text-sm font-semibold text-emerald-700">{{ substr(auth()->user()->name, 0, 1) }}</span>
                </a>
            </div>
        </header>

        {{-- Flash messages --}}
        <div class="px-6 pt-4">
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
        <main class="flex-1 px-6 pb-8">
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    @stack('scripts')
</body>
</html>
