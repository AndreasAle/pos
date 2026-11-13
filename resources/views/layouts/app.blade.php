<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    @include('layouts._fonts')
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
    @php $focus = $__env->hasSection('focus'); @endphp
    <x-page-loader/>
    <x-yoka-enter :arrive="$focus"/>

    @php
        $hasTopSubscriptionBar = $currentBusiness?->activeSubscription?->status === 'trial';
    @endphp

    @if(session('platform_impersonating') && auth('platform')->check())
        <div class="fixed bottom-4 left-1/2 z-[80] flex -translate-x-1/2 items-center gap-4 border border-amber-300 bg-amber-50 px-5 py-3 shadow-xl">
            <div>
                <p class="text-sm font-bold text-amber-950">Mode bantuan tim Yoka</p>
                <p class="text-xs text-amber-800">Anda sedang melihat dashboard sebagai toko ini. Seluruh sesi tercatat.</p>
            </div>
            <form method="POST" action="{{ route('platform.impersonate.stop') }}">
                @csrf
                <button class="whitespace-nowrap bg-amber-900 px-4 py-2 text-xs font-bold text-white hover:bg-amber-950">Kembali ke Platform</button>
            </form>
        </div>
    @endif

    @unless($focus)
    {{-- Mobile sidebar backdrop --}}
    <div x-show="sidebarOpen" x-cloak
         @click="sidebarOpen = false"
         class="fixed inset-x-0 bottom-0 z-30 bg-black/50 lg:hidden {{ $hasTopSubscriptionBar ? 'top-28' : 'top-16' }}"></div>

    {{-- Sidebar --}}
    <aside class="fixed bottom-0 left-0 z-40 w-[260px] bg-white border-r border-[#E5E7EB] flex flex-col -translate-x-full lg:translate-x-0 transition-[width,transform] duration-200 {{ $hasTopSubscriptionBar ? 'top-28' : 'top-16' }}"
           :class="[sidebarOpen ? '!translate-x-0' : '', collapsed ? 'lg:w-[72px]' : 'lg:w-[260px]']">

        {{-- Business / outlet --}}
        @php $outletName = auth()->user()->outlet?->name ?? 'Semua Outlet'; @endphp
        <div class="shrink-0 px-3 pb-2 pt-3" :class="collapsed ? 'lg:px-4' : ''">
            <div class="flex h-12 items-center gap-3 rounded-lg border border-[#EAECF0] px-3"
                 :class="collapsed ? 'lg:justify-center lg:px-0 lg:h-10 lg:border-0' : ''"
                 @mouseenter="tipShow($event, @js(($currentBusiness->name ?? config('app.name')).' · '.$outletName))" @mouseleave="tipHide()">
                <svg class="h-5 w-5 shrink-0 text-[#667085]" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l1.5-5h15L21 9M3 9v11a1 1 0 001 1h16a1 1 0 001-1V9M3 9h18M9 21v-6h6v6"/></svg>
                <div class="min-w-0 flex-1" :class="collapsed ? 'lg:hidden' : ''">
                    <p class="truncate text-[14px] font-bold leading-tight text-[#0B1B3F]">{{ $currentBusiness->name ?? config('app.name') }}</p>
                    <p class="mt-0.5 truncate text-[12px] text-[#7C8698]">{{ $outletName }}</p>
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 space-y-0.5 overflow-y-auto overflow-x-hidden px-3 py-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" :class="collapsed ? 'lg:px-4' : ''">
            @php
                $role     = auth()->user()->role;
                $isRetail = (bool) auth()->user()->business?->isRetail();
            @endphp

            {{-- Utama --}}
            <x-nav-item route="dashboard" icon="home" data-tour="dashboard">Dashboard</x-nav-item>
            @if(in_array($role, ['owner','admin']))
            @if(config('pos.features.intelligence'))
                <x-nav-ai route="intelligence.index" label="Tanya Yoka" data-yoka-enter />

            @endif
            @endif
            @if(in_array($role, ['owner','admin','cashier']))
            <x-nav-item route="pos.index" icon="shopping-cart" data-tour="pos">Kasir / POS</x-nav-item>
            <x-nav-item route="orders.index" icon="clipboard-list">Riwayat Order</x-nav-item>
            <x-nav-item route="shifts.index" icon="clock">Shift Kasir</x-nav-item>
            @endif

            @if(in_array($role, ['owner','admin']))
            <x-nav-group title="Master Data" :routes="['products.*','bundles.*','categories.*','customers.*','promotions.*']">
                <x-nav-item route="products.index" icon="cube" data-tour="products">{{ $isRetail ? 'Produk' : 'Produk / Menu' }}</x-nav-item>
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
                <x-nav-item route="reports.sales" icon="chart-bar" data-tour="reports">Laporan Penjualan</x-nav-item>
                <x-nav-item route="reports.products" icon="chart-pie">Produk Terlaris</x-nav-item>
                <x-nav-item route="reports.cashier" icon="user-circle">Laporan Kasir</x-nav-item>
                <x-nav-item route="reports.shift" icon="clock-rotate-left">Laporan Shift</x-nav-item>
                <x-nav-item route="reports.inventory" icon="archive">Laporan Inventory</x-nav-item>
                <x-nav-item route="reports.profit" icon="banknotes">Estimasi Profit</x-nav-item>
                <x-nav-item route="reports.profit-loss" icon="chart-bar">Laba Rugi</x-nav-item>
                <x-nav-item route="reports.tax" icon="badge-percent">Laporan Pajak</x-nav-item>
                <x-nav-item route="reports.returns" icon="arrows-right-left">Retur Transaksi</x-nav-item>
            </x-nav-group>

            <x-nav-group title="Keuangan" :routes="['expenses.*','expense-categories.*','suppliers.*','purchases.*','payables.*','receivables.*']">
                <x-nav-item route="expenses.index" icon="banknotes">Biaya</x-nav-item>
                <x-nav-item route="purchases.index" icon="shopping-cart">Pembelian</x-nav-item>
                <x-nav-item route="suppliers.index" icon="building-office">Supplier</x-nav-item>
                <x-nav-item route="payables.index" icon="clipboard-list">Hutang</x-nav-item>
                <x-nav-item route="receivables.index" icon="clipboard-list">Piutang</x-nav-item>
            </x-nav-group>

            <x-nav-group title="Pengaturan" :routes="['outlets.*','users.*','settings.*','audit.*']">
                <x-nav-item route="outlets.index" icon="building-storefront">Outlet</x-nav-item>
                <x-nav-item route="users.index" icon="user-group">Manajemen User</x-nav-item>
                <x-nav-item route="settings.business" icon="building-office">Profil Bisnis</x-nav-item>
                <x-nav-item route="settings.receipt" icon="receipt-percent">Pengaturan Struk</x-nav-item>
                <x-nav-item route="settings.telegram" icon="paper-airplane">Bot Telegram</x-nav-item>
                @if(config('pos.features.audit_log'))
                <x-nav-item route="audit.index" icon="clipboard-list">Audit Log</x-nav-item>
                @endif
            </x-nav-group>
            @endif

        </nav>

        @php $supportUrl = config('pos.support_url') ?: 'mailto:' . config('mail.from.address'); @endphp
        <div class="px-3 pb-3 pt-2 shrink-0" :class="collapsed ? 'lg:px-4' : ''">
            <div class="rounded-xl bg-[#F4F6F8] p-3" :class="collapsed ? 'lg:hidden' : ''">
                <div class="flex items-center gap-2.5">
                    <svg class="w-8 h-8 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.5"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 9.5a2.5 2.5 0 114 2c-.9.6-1.5 1.1-1.5 2M12 16.8v.01"/></svg>
                    <div class="min-w-0">
                        <p class="text-[13px] font-bold text-gray-900 leading-tight">Butuh bantuan?</p>
                        <p class="text-xs text-gray-500 leading-tight mt-0.5">Hubungi tim support kami.</p>
                    </div>
                </div>
                <a href="{{ $supportUrl }}" target="_blank" rel="noopener" data-tour="support" class="mt-3 h-10 flex items-center justify-center gap-2 rounded-lg bg-[#E1F5EA] text-[#0B8F5B] text-sm font-semibold hover:bg-[#CFEFDD] transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 14v-2a8 8 0 1116 0v2M4 14a2 2 0 002 2h1v-5H6a2 2 0 00-2 2zm16 0a2 2 0 01-2 2h-1v-5h1a2 2 0 012 2zM18 18a3 3 0 01-3 3h-2"/></svg>
                    Hubungi Support
                </a>
            </div>
            <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="hidden h-9 items-center justify-center rounded-lg text-[#0B8F5B] hover:bg-[#F4F6F8]" :class="collapsed ? 'lg:flex' : ''"
               @mouseenter="tipShow($event, 'Hubungi Support')" @mouseleave="tipHide()" aria-label="Hubungi Support">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 14v-2a8 8 0 1116 0v2M4 14a2 2 0 002 2h1v-5H6a2 2 0 00-2 2zm16 0a2 2 0 01-2 2h-1v-5h1a2 2 0 012 2zM18 18a3 3 0 01-3 3h-2"/></svg>
            </a>
        </div>

    </aside>
    @endunless

    {{-- Topbar --}}
    @php
        $me = auth()->user();
        $activeShift = $me->activeShift()->first();
        $canSettings = in_array($me->role, ['owner','admin']);
    @endphp

    @unless($focus)
    {{-- Status langganan berada paling atas, di atas header utama. --}}
    @if($hasTopSubscriptionBar)
        <x-subscription-status-bar :current-business="$currentBusiness" />
    @endif

    <header class="fixed inset-x-0 z-50 flex h-16 items-center gap-3 border-b border-white/10 bg-[#0A5C3A] px-3 text-white shadow-[0_1px_0_rgba(15,23,42,.08)] sm:gap-5 sm:px-5 {{ $hasTopSubscriptionBar ? 'top-12' : 'top-0' }}">
        {{-- Brand --}}
        <div class="flex shrink-0 items-center gap-2 lg:w-[235px] lg:justify-between">
            <button @click="sidebarOpen = !sidebarOpen" class="-ml-2 rounded-lg p-2 text-white/80 transition hover:bg-white/10 hover:text-white lg:hidden" aria-label="Menu">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="flex items-center gap-2">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5" aria-label="Yoka POS">
                <span class="flex h-9 w-9 items-center justify-center rounded-[10px] bg-white shadow-sm"><img src="{{ asset('images/brand/yoka-icon.png') }}" alt="" class="h-7 w-7 object-contain"></span>
                <span class="hidden text-[15px] font-semibold tracking-[-.01em] sm:inline">Yoka POS</span>
            </a>
            </div>
            <button @click="toggle()" class="-mr-1 hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg text-white/65 transition hover:bg-white/10 hover:text-white lg:inline-flex" :aria-label="collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'" :title="collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h16"/></svg>
        </button>
        </div>


        {{-- Pencarian global (menu & laporan) --}}
        <div class="group relative min-w-0 max-w-[690px] flex-1"
             :class="mob ? 'max-sm:!fixed max-sm:inset-x-0 {{ $hasTopSubscriptionBar ? 'max-sm:top-12' : 'max-sm:top-0' }} max-sm:h-16 max-sm:z-[60] max-sm:bg-[#0A5C3A] max-sm:px-3 max-sm:flex max-sm:items-center max-sm:!max-w-none' : 'max-sm:hidden'"
             @open-search.window="mob = true; $nextTick(() => $refs.q.focus())"
             x-data="{
                q: '', open: false, sel: 0, items: [], mob: false,
                load() { this.items = [...document.querySelectorAll('aside nav a[href]')].map(a => ({ label: a.textContent.trim(), href: a.href })) },
                get results() { const t = this.q.trim().toLowerCase(); return t ? this.items.filter(i => i.label.toLowerCase().includes(t)).slice(0, 8) : [] },
                go() { const r = this.results[this.sel]; if (r) window.location = r.href }
             }" @click.outside="open = false" @keydown.window="if ($event.key === '/' && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.q.focus() }">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-500 transition-colors group-focus-within:text-[#16A34A]" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.65"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
            <input x-ref="q" type="text" x-model="q" autocomplete="off"
                   @focus="load(); open = true" @input="sel = 0; open = true"
                   @keydown.arrow-down.prevent="sel = Math.min(sel + 1, results.length - 1)" @keydown.arrow-up.prevent="sel = Math.max(sel - 1, 0)"
                   @keydown.enter.prevent="go()" @keydown.escape="open = false; mob = false; $refs.q.blur()"
                   placeholder="Cari menu, produk, atau laporan..."
                   class="h-10 w-full rounded-xl border border-transparent bg-white pl-11 pr-14 text-[14px] font-semibold text-[#0B1B3F] shadow-sm transition-all duration-200 placeholder:font-medium placeholder:text-slate-500 hover:shadow-md focus:border-[#16A34A] focus:outline-none focus:ring-4 focus:ring-white/30">
            <kbd class="pointer-events-none absolute right-3 top-1/2 hidden h-5 min-w-[20px] -translate-y-1/2 items-center justify-center rounded-md border border-slate-200 bg-slate-100 px-1.5 text-[12px] font-bold text-slate-500 group-focus-within:hidden sm:inline-flex">/</kbd>
            <button type="button" x-show="mob" x-cloak @click="mob = false; open = false; q = ''" class="sm:hidden ml-2 px-2 h-9 text-sm font-semibold text-white/90 shrink-0">Batal</button>
            <div x-show="open && q.trim()" x-cloak class="absolute left-3 right-3 sm:left-0 sm:right-0 top-full mt-1 sm:mt-2 rounded-lg bg-white text-gray-800 shadow-xl border border-[#E5E7EB] py-1 z-50">
                <template x-for="(r, i) in results" :key="r.href">
                    <a :href="r.href" class="block px-3 py-2 text-sm font-medium" :class="i === sel ? 'bg-[#E8F7EF] text-[#0B8F5B]' : 'hover:bg-[#F4F6F8]'" x-text="r.label"></a>
                </template>
                <p x-show="!results.length" class="px-3 py-2 text-sm text-gray-500">Tidak ada hasil untuk "<span x-text="q"></span>"</p>
            </div>
        </div>

        <div class="ml-auto flex shrink-0 items-center gap-1.5 sm:gap-2.5">
            <button type="button" @click="$dispatch('open-search')" class="sm:hidden w-10 h-10 rounded-lg flex items-center justify-center text-white hover:bg-white/10" aria-label="Cari">
                <svg class="w-[22px] h-[22px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
            </button>
            @if($activeShift)
            <a href="{{ route('shifts.show', $activeShift) }}" class="hidden md:flex items-center gap-1.5 text-xs font-medium bg-white/15 text-white rounded-full px-3 py-1.5 hover:bg-white/15">
                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>Shift Aktif
            </a>
            @endif

            {{-- Bahasa --}}
            <x-lang-switch variant="dark" />

            {{-- Notifikasi --}}
            <div class="relative max-sm:hidden" x-data="{ n: false }" @click.outside="n = false" @keydown.escape.window="n = false">
                <button @click="n = !n" class="flex h-9 w-9 items-center justify-center rounded-lg text-white/75 transition-colors hover:bg-white/10 hover:text-white" :class="n ? 'bg-white/10' : ''" aria-label="Notifikasi">
                    <svg class="h-[21px] w-[21px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="stroke-width:1.65"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/></svg>
                </button>
                <div x-show="n" x-cloak x-transition.opacity.duration.100ms class="absolute right-0 mt-2 w-72 rounded-lg bg-white text-gray-800 border border-[#E5E7EB] shadow-xl z-50">
                    <p class="px-4 py-3 text-sm font-semibold border-b border-[#E5E7EB]">Notifikasi</p>
                    <p class="px-4 py-8 text-sm text-gray-500 text-center">Belum ada notifikasi baru.</p>
                </div>
            </div>

            <span class="mx-1 hidden h-7 w-px bg-white/20 sm:block"></span>

            {{-- Profil --}}
            <div class="relative" x-data="{ menu: false }" @click.outside="menu = false" @keydown.escape.window="menu = false">
                <button @click="menu = !menu" class="flex h-11 items-center gap-2.5 rounded-lg py-1 pl-1 pr-2 transition-colors hover:bg-white/10 max-sm:pr-1" :class="menu ? 'bg-white/10' : ''" aria-label="Menu akun" :aria-expanded="menu">
                    <x-avatar :user="$me" class="h-9 w-9 ring-2 ring-white/25" text="text-sm"/>
                    <span class="hidden max-w-[155px] text-left leading-tight md:block">
                        <span class="block truncate text-[13px] font-semibold">{{ $me->name }}</span>
                        <span class="mt-0.5 block text-[12px] capitalize text-white/65">{{ $me->role }}</span>
                    </span>
                    <svg class="max-sm:hidden w-4 h-4 text-white/75 transition-transform" :class="menu ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="menu" x-cloak x-transition.opacity.duration.100ms class="absolute right-0 mt-2 w-52 rounded-lg bg-white text-gray-800 border border-[#E5E7EB] shadow-xl py-1 z-50">
                    <a href="{{ route('profile') }}" class="flex items-center gap-2.5 px-3 py-2 text-sm font-medium hover:bg-[#F4F6F8]">
                        <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21a8 8 0 0116 0"/></svg>Profil Saya</a>
                    @if($canSettings)
                    <a href="{{ route('settings.business') }}" class="flex items-center gap-2.5 px-3 py-2 text-sm font-medium hover:bg-[#F4F6F8]">
                        <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3a1.7 1.7 0 013.4 0 1.7 1.7 0 002.5 1l.1-.1a1.7 1.7 0 012.4 2.4l-.1.1a1.7 1.7 0 001 2.5 1.7 1.7 0 010 3.4 1.7 1.7 0 00-1 2.5l.1.1a1.7 1.7 0 01-2.4 2.4l-.1-.1a1.7 1.7 0 00-2.5 1 1.7 1.7 0 01-3.4 0 1.7 1.7 0 00-2.5-1l-.1.1a1.7 1.7 0 01-2.4-2.4l.1-.1a1.7 1.7 0 00-1-2.5 1.7 1.7 0 010-3.4 1.7 1.7 0 001-2.5l-.1-.1a1.7 1.7 0 012.4-2.4l.1.1a1.7 1.7 0 002.5-1zM15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Pengaturan</a>
                    @endif
                    @if($me->isOwner())
                    <form method="POST" action="{{ route('onboarding.tour.restart') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-2.5 px-3 py-2 text-left text-sm font-medium hover:bg-[#F4F6F8]">
                            <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.6 16.4A8 8 0 0118.8 7M18.4 7.6A8 8 0 015.2 17"/></svg>Ulangi Panduan Yoka
                        </button>
                    </form>
                    @endif
                    <div class="my-1 border-t border-[#E5E7EB]"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Keluar</button>
                    </form>
                </div>
            </div>
        </div>
    </header>
    @endunless

    {{-- Main content --}}
    <div class="flex min-h-screen min-w-0 flex-col overflow-x-clip transition-all duration-200 {{ $focus ? '' : ($hasTopSubscriptionBar ? 'pt-28' : 'pt-16') }}" @if(!$focus) :class="collapsed ? 'lg:pl-[72px]' : 'lg:pl-[260px]'" @endif>

        {{-- Flash messages --}}
        <div class="px-4 sm:px-6 @if(session('success') || session('error') || session('info') || $errors->any()) pt-4 @endif">
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
        <main class="flex-1 min-w-0 {{ $focus ? 'p-0' : 'px-4 sm:px-6 pb-8' }}">
            @unless(request()->routeIs('dashboard', 'intelligence.index'))
            <h1 class="pt-5 pb-0.5 text-xl font-bold text-gray-900">@yield('page-title', 'Dashboard')</h1>
            @hasSection('page-subtitle')<p class="mb-3 text-sm text-[#64748B]">@yield('page-subtitle')</p>@endif
            @endunless
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
