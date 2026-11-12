<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kasir POS — {{ $business->name }}</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
    <style>
        /* Kolom angka (diskon, ongkir, uang diterima) tanpa tombol panah naik-turun bawaan browser */
        input[type=number] { -moz-appearance: textfield; appearance: textfield; }
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    </style>
</head>
<body class="h-full bg-gray-100 font-sans" x-data="posApp()" x-init="init()">

{{-- Top Bar --}}
<header class="relative z-30 h-14 px-3 md:px-5 flex items-center gap-2.5 md:gap-4 text-white
               bg-gradient-to-r from-[#0a7a43] via-[#0c874e] to-[#10975a] shadow-[0_2px_12px_rgba(6,78,59,.28)]">
    @if(auth()->user()->role !== 'cashier')
    <a href="{{ route('dashboard') }}" aria-label="Kembali ke dashboard"
       class="w-9 h-9 -ml-1 rounded-full flex items-center justify-center text-white/80 hover:text-white hover:bg-white/15 transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </a>
    <div class="w-px h-6 bg-white/25"></div>
    @endif

    <div class="flex items-center gap-1.5" title="Yoka POS">
        <img src="{{ asset('images/brand/yoka-logo-white.png') }}" alt="Yoka" class="h-6 w-auto">
        <span class="text-[17px] leading-none font-bold tracking-tight text-white">POS</span>
    </div>

    @if($activeShift)
    <div class="hidden md:flex items-center gap-2 text-xs font-semibold bg-white/15 border border-white/20 rounded-full px-3 py-1.5">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
        Shift aktif &middot; {{ $activeShift->outlet->name }}
    </div>
    @endif

    <div class="ml-auto flex items-center gap-2 md:gap-3">
        <button @click="loadDrafts()" x-show="draftCount > 0" x-cloak
                class="relative h-9 inline-flex items-center gap-1.5 text-xs font-bold text-amber-950 bg-amber-300 hover:bg-amber-200 px-3.5 rounded-full shadow-sm transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>Order Hold
            <span x-text="draftCount" class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[12px] font-bold rounded-full flex items-center justify-center ring-2 ring-[#0c874e]"></span>
        </button>

        <span class="hidden md:inline text-sm font-semibold text-white/80 tabular-nums" x-text="currentTime"></span>

        <span class="hidden sm:flex items-center gap-2 pl-1 pr-3.5 py-1 rounded-full bg-white/10">
            <span class="w-7 h-7 rounded-full bg-white text-emerald-700 text-xs font-extrabold flex items-center justify-center">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <span class="text-sm font-semibold">{{ auth()->user()->name }}</span>
        </span>

        {{-- How THIS device prints. A laptop and an Android phone at the same
             shop print differently, so the choice lives on the device. --}}
        <div class="relative" x-data="{ open: false, opts: [{ v: 'browser', l: 'Printer PC/laptop' }, { v: 'rawbt', l: 'Bluetooth (RawBT)' }, { v: 'none', l: 'Tanpa cetak' }] }"
             @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" @click="open = !open" title="Cara cetak di perangkat ini"
                    class="h-9 inline-flex items-center gap-2 pl-3 pr-2.5 rounded-full text-xs font-semibold transition-colors"
                    :class="open ? 'bg-white/25' : 'bg-white/10 hover:bg-white/20'">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span class="hidden sm:inline" x-text="(opts.find(o => o.v === printVia) || opts[0]).l"></span>
                <svg class="w-3.5 h-3.5 opacity-80 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                 class="absolute right-0 top-full mt-2 w-56 bg-white text-gray-800 rounded-xl border border-gray-200 shadow-[0_14px_36px_rgba(15,23,42,.20)] overflow-hidden py-1 z-40">
                <p class="px-3.5 pt-2 pb-1 text-[12px] font-bold uppercase tracking-wider text-gray-400">Cara cetak struk</p>
                <template x-for="o in opts" :key="o.v">
                    <button type="button" @click="printVia = o.v; savePrintVia(); open = false"
                            class="w-full flex items-center justify-between gap-3 px-3.5 py-2.5 text-sm text-left hover:bg-emerald-50 transition-colors"
                            :class="printVia === o.v ? 'text-emerald-700 font-bold' : 'text-gray-700 font-medium'">
                        <span x-text="o.l"></span>
                        <svg x-show="printVia === o.v" class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </button>
                </template>
            </div>
        </div>

        {{-- Hand the register to the next cashier: back to the PIN pad. --}}
        <form method="POST" action="{{ route('pin.logout') }}">
            @csrf
            <button type="submit" title="Ganti kasir"
                    class="h-9 inline-flex items-center gap-2 px-3 sm:px-3.5 rounded-full text-xs font-semibold bg-white/10 hover:bg-white/20 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span class="hidden sm:inline">Ganti Kasir</span>
            </button>
        </form>

        @if($activeShift)
        <a href="{{ route('shifts.close', $activeShift) }}"
           class="h-9 inline-flex items-center gap-1.5 px-3.5 rounded-full text-xs font-bold bg-white text-red-600 hover:bg-red-50 shadow-sm transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg><span class="hidden sm:inline">Tutup Shift</span>
        </a>
        @endif
    </div>
</header>

<div class="flex h-[calc(100vh-56px)] h-[calc(100dvh-56px)]">

    {{-- LEFT: Products --}}
    <div class="@container flex-1 flex flex-col min-w-0 overflow-hidden">
        {{-- Filter Bar --}}
        <div class="bg-white border-b border-gray-200 px-3 md:px-5 py-3 flex flex-wrap @4xl:flex-nowrap items-center gap-2.5 md:gap-3">
            {{-- Pencarian nama produk --}}
            <label class="group relative w-full @4xl:w-[320px] @4xl:flex-none">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-gray-400 group-focus-within:text-emerald-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" x-model="search" placeholder="Cari nama produk"
                       class="h-11 w-full pl-11 pr-9 rounded-xl border border-transparent bg-gray-100 text-sm text-gray-900 placeholder:text-gray-400
                              hover:bg-gray-100/70 focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all">
                <button type="button" x-show="search.length > 0" x-cloak @click="search = ''" aria-label="Hapus pencarian"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </label>

            <div class="hidden @4xl:block w-px h-7 bg-gray-200 shrink-0"></div>

            {{-- Kategori --}}
            <div class="relative min-w-0 w-full @4xl:w-auto @4xl:flex-1">
                <div class="flex gap-2 overflow-x-auto pr-8 py-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <button @click="activeCategory = null; showBundles = false"
                            :class="activeCategory === null && !showBundles ? 'bg-emerald-600 text-white shadow-[0_4px_12px_rgba(5,150,105,.30)]' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="shrink-0 h-9 inline-flex items-center gap-2 text-[13px] font-semibold pl-4 pr-3 rounded-full transition-all">
                        Semua
                        <span class="text-[12px] font-bold rounded-full px-1.5 py-0.5 min-w-[22px] text-center"
                              :class="activeCategory === null && !showBundles ? 'bg-white/25 text-white' : 'bg-white text-gray-500'"
                              x-text="allProducts.length"></span>
                    </button>
                    {{-- Bundle tab --}}
                    @if(count($bundlesJson) > 0)
                    <button @click="showBundles = true; activeCategory = null"
                            :class="showBundles ? 'bg-purple-600 text-white shadow-[0_4px_12px_rgba(147,51,234,.30)]' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="shrink-0 h-9 inline-flex items-center gap-2 text-[13px] font-semibold pl-4 pr-3 rounded-full transition-all">
                        Bundle
                        <span class="text-[12px] font-bold rounded-full px-1.5 py-0.5 min-w-[22px] text-center"
                              :class="showBundles ? 'bg-white/25 text-white' : 'bg-white text-gray-500'"
                              x-text="allBundles.length"></span>
                    </button>
                    @endif
                    @foreach($categories as $cat)
                    <button @click="activeCategory = {{ $cat->id }}; showBundles = false"
                            :style="activeCategory === {{ $cat->id }} && !showBundles ? 'background:{{ $cat->color ?? '#059669' }};box-shadow:0 4px 12px rgba(15,23,42,.18)' : ''"
                            :class="(activeCategory === {{ $cat->id }} && !showBundles) ? 'text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="shrink-0 h-9 inline-flex items-center gap-2 text-[13px] font-semibold pl-4 pr-3 rounded-full transition-all">
                        {{ $cat->name }}
                        <span class="text-[12px] font-bold rounded-full px-1.5 py-0.5 min-w-[22px] text-center"
                              :class="(activeCategory === {{ $cat->id }} && !showBundles) ? 'bg-white/25 text-white' : 'bg-white text-gray-500'"
                              x-text="allProducts.filter(p => p.cat_id == {{ $cat->id }}).length"></span>
                    </button>
                    @endforeach
                </div>
                {{-- pudar di tepi kanan: tanda masih bisa digeser --}}
                <div class="pointer-events-none absolute right-0 top-0 bottom-0 w-10 bg-gradient-to-l from-white to-transparent"></div>
            </div>
        </div>

        {{-- Product / Bundle Grid --}}
        <div class="flex-1 overflow-y-auto p-2 md:p-4 pb-24 md:pb-4 bg-[#cfeadb]">

            {{-- Bundle Grid --}}
            <div x-show="showBundles"
                 class="grid gap-2 md:gap-3 [grid-template-columns:repeat(auto-fill,minmax(140px,1fr))] md:[grid-template-columns:repeat(auto-fill,minmax(176px,1fr))]">
                <template x-for="bundle in allBundles" :key="'b'+bundle.id">
                <button type="button"
                        @click="addBundle(bundle)"
                        class="bg-white rounded-2xl border-2 border-purple-200 shadow-sm overflow-hidden text-left hover:shadow-md hover:border-purple-400 transition-all active:scale-95 select-none cursor-pointer">
                    <div class="aspect-square bg-purple-50 flex items-center justify-center">
                        <div class="text-center px-2">
                            <svg class="w-8 h-8 text-purple-300 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                            </svg>
                            <span class="text-xs text-purple-400 font-semibold">BUNDLE</span>
                        </div>
                    </div>
                    <div class="p-2.5">
                        <p class="text-xs font-semibold text-gray-900 leading-tight line-clamp-2" x-text="bundle.name"></p>
                        <p class="text-xs text-gray-400 mt-0.5 line-clamp-1"
                           x-text="bundle.items.map(i => i.qty+'× '+i.product_name).join(', ')"></p>
                        <p class="text-sm font-bold text-purple-700 mt-1" x-text="'Rp ' + fmt(bundle.price)"></p>
                    </div>
                </button>
                </template>
            </div>

            {{-- Product Grid --}}
            <div x-show="!showBundles"
                 class="grid gap-2 md:gap-3 [grid-template-columns:repeat(auto-fill,minmax(140px,1fr))] md:[grid-template-columns:repeat(auto-fill,minmax(176px,1fr))]">
                <template x-for="product in allProducts" :key="product.id">
                <button type="button"
                        x-show="matchesFilter(product.cat_id, product.name)"
                        @click="selectProduct(product)"
                        class="bg-white rounded-2xl border-[1.5px] border-emerald-700/25 shadow-[0_2px_8px_rgba(6,78,59,.10)] overflow-hidden text-left hover:border-emerald-500 hover:shadow-[0_6px_16px_rgba(6,78,59,.18)] hover:-translate-y-0.5 transition-all active:scale-[.97] select-none cursor-pointer">
                    <div class="aspect-[5/4] overflow-hidden relative" x-data="{ broken: false }"
                         :style="(!product.image || broken) ? 'background:' + tile(product.name).bg : ''"
                         :class="(product.image && !broken) ? 'bg-gray-100' : ''">
                        <template x-if="product.image && !broken">
                            <img :src="product.image" :alt="product.name" x-on:error="broken = true" class="w-full h-full object-cover pointer-events-none">
                        </template>
                        <template x-if="!product.image || broken">
                            <div class="w-full h-full flex items-center justify-center">
                                <span class="text-4xl md:text-5xl font-extrabold tracking-tight select-none"
                                      :style="'color:' + tile(product.name).fg" x-text="initials(product.name)"></span>
                            </div>
                        </template>
                    </div>
                    <div class="p-2.5 md:p-3.5">
                        <p class="text-xs font-semibold text-gray-900 leading-snug line-clamp-2" x-text="product.name"></p>
                        <p class="text-xs md:text-sm font-bold text-emerald-700 mt-0.5" x-text="'Rp ' + fmt(product.price)"></p>
                    </div>
                </button>
                </template>
            </div>
        </div>
    </div>

    {{-- RIGHT: Cart --}}
    <div :class="mobileCart ? 'fixed inset-0 z-40 flex' : 'hidden md:flex'"
         :style="isDesktop ? 'width:' + cartW + 'px' : ''"
         class="md:relative shrink-0 bg-white border-l border-gray-200 flex-col shadow-[-8px_0_24px_rgba(15,23,42,.04)]">

        {{-- Pegangan geser: tarik ke kiri untuk melebarkan pesanan, ke kanan untuk menyempitkan --}}
        <div class="hidden md:flex absolute inset-y-0 -left-2 w-4 z-30 items-center justify-center cursor-col-resize touch-none group"
             @pointerdown.prevent="startResize($event)" @pointermove="doResize($event)"
             @pointerup="endResize()" @pointercancel="endResize()" @dblclick="resetCartW()"
             title="Geser untuk mengubah lebar. Klik dua kali untuk mengembalikan.">
            <span class="absolute inset-y-0 left-1/2 -translate-x-1/2 w-0.5 transition-colors"
                  :class="resizing ? 'bg-emerald-500' : 'bg-transparent group-hover:bg-emerald-400'"></span>
            <span class="relative h-12 w-1.5 rounded-full transition-colors"
                  :class="resizing ? 'bg-emerald-500' : 'bg-gray-300 group-hover:bg-emerald-500'"></span>
        </div>

        {{-- Phone-only header for the cart sheet --}}
        <div class="md:hidden flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <p class="font-bold text-gray-900">Pesanan (<span x-text="cartCount()"></span>)</p>
            <button @click="mobileCart=false" class="text-sm font-semibold text-emerald-700 px-3 py-1.5 rounded-lg bg-emerald-50">+ Tambah menu</button>
        </div>

        {{-- Judul pesanan --}}
        <div class="flex items-center justify-between px-5 pt-3.5 pb-2.5">
            <div class="flex items-baseline gap-2">
                <h2 class="text-[17px] font-extrabold text-gray-900">Pesanan</h2>
                <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 rounded-full px-2 py-0.5"
                      x-show="cart.length > 0" x-text="cartCount() + ' item'"></span>
            </div>
            <button @click="clearCart()" x-show="cart.length > 0" x-cloak
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-400 hover:text-red-500 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Kosongkan
            </button>
        </div>

        {{-- Order Type Selector --}}
        <div class="px-5 pb-2.5 border-b border-gray-100">
            <div class="grid {{ $business->isRetail() ? 'grid-cols-2' : 'grid-cols-3' }} gap-1 p-1 bg-gray-100 rounded-xl">
                @unless($business->isRetail())
                <button @click="setOrderType('dine_in')"
                        :class="orderType==='dine_in' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                        class="text-xs font-semibold py-2 rounded-lg transition-all inline-flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 10V6l1-3h12l1 3v4M5 10v11h14V10M9 21v-6h6v6"/></svg>Dine In
                </button>
                @endunless
                <button @click="setOrderType('takeaway')"
                        :class="orderType==='takeaway' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                        class="text-xs font-semibold py-2 rounded-lg transition-all inline-flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>{{ $business->isRetail() ? 'Beli di Toko' : 'Takeaway' }}
                </button>
                <button @click="setOrderType('delivery')"
                        :class="orderType==='delivery' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                        class="text-xs font-semibold py-2 rounded-lg transition-all inline-flex items-center justify-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10m10 0H9m4 0h2m4 0h1a1 1 0 001-1v-4l-3-4h-4"/></svg>{{ $business->isRetail() ? 'Kirim / Online' : 'Delivery' }}
                </button>
            </div>

            {{-- Delivery fields --}}
            <div x-show="orderType === 'delivery'" x-cloak x-transition.opacity.duration.150ms class="mt-3 space-y-2.5"
                 x-data="{ open: false,
                           opts: [{ v: 'gofood', l: 'GoFood', c: '#ef4444' }, { v: 'grabfood', l: 'GrabFood', c: '#16a34a' },
                                  { v: 'shopeefood', l: 'ShopeeFood', c: '#f97316' }, { v: 'manual', l: 'Manual / Kurir Sendiri', c: '#64748b' }],
                           find(v) { return this.opts.find(o => o.v === v); } }"
                 @click.outside="open = false" @keydown.escape="open = false">
                <p class="text-[12px] font-bold uppercase tracking-wider text-gray-400">Detail pengiriman</p>

                {{-- Platform --}}
                <div class="relative">
                    <button type="button" @click="open = !open"
                            :class="open ? 'border-emerald-500 ring-4 ring-emerald-500/10' : 'border-gray-200 hover:border-gray-300'"
                            class="w-full flex items-center justify-between gap-2 border rounded-xl px-3 py-2.5 bg-white text-left transition-all">
                        <span class="inline-flex items-center gap-2 min-w-0">
                            <span x-show="find(deliveryPlatform)" class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background:' + (find(deliveryPlatform) ? find(deliveryPlatform).c : '')"></span>
                            <span class="truncate text-sm" :class="find(deliveryPlatform) ? 'font-semibold text-gray-900' : 'text-gray-400'"
                                  x-text="find(deliveryPlatform) ? find(deliveryPlatform).l : 'Pilih platform delivery'"></span>
                        </span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                         class="absolute z-30 left-0 right-0 mt-1.5 bg-white rounded-xl border border-gray-200 shadow-[0_12px_32px_rgba(15,23,42,.14)] overflow-hidden py-1">
                        <template x-for="o in opts" :key="o.v">
                            <button type="button" @click="deliveryPlatform = o.v; open = false"
                                    class="w-full flex items-center gap-3 px-3 py-2.5 text-left text-sm hover:bg-emerald-50 transition-colors">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background:' + o.c"></span>
                                <span class="flex-1 font-medium text-gray-800" x-text="o.l"></span>
                                <span x-show="deliveryPlatform === o.v"><svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- No. order & ongkir --}}
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" x-model="platformOrderNo" placeholder="No. order"
                           class="w-full text-sm rounded-xl border border-gray-200 bg-white px-3 py-2.5 placeholder:text-gray-400 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all">
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">Rp</span>
                        <input type="number" x-model.number="deliveryFee" min="0" step="1000" @change="calcTotals()"
                               placeholder="Ongkir"
                               class="pl-9 w-full text-sm rounded-xl border border-gray-200 bg-white px-3 py-2.5 placeholder:text-gray-400 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all">
                    </div>
                </div>

                <textarea x-model="deliveryAddress" rows="2" placeholder="Alamat pengiriman"
                          class="w-full text-sm rounded-xl border border-gray-200 bg-white px-3 py-2.5 placeholder:text-gray-400 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all resize-none"></textarea>
            </div>
        </div>

        {{-- Customer --}}
        <div class="px-5 py-2.5 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                @php
                    $custList = \App\Models\Customer::forBusiness(auth()->user()->business_id)->where('is_active', true)->orderBy('name')->limit(100)->get()
                        ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone])->values();
                @endphp
                <div class="relative flex-1 min-w-0"
                     x-data="{ open: false, q: '', list: {{ json_encode($custList) }},
                               idOf(id) { return this.list.find(c => String(c.id) === String(id)); },
                               get shown() { const t = this.q.trim().toLowerCase(); return t ? this.list.filter(c => (c.name + ' ' + (c.phone || '')).toLowerCase().includes(t)) : this.list; }, }"
                     @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" @click="open = !open; if (open) $nextTick(() => $refs.q.focus())"
                            :class="open ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-gray-200 hover:border-gray-300'"
                            class="w-full flex items-center justify-between gap-2 border rounded-xl px-3 py-2 bg-white text-left transition-all">
                        <span class="truncate text-sm" :class="idOf(customerId) ? 'font-semibold text-gray-900' : 'text-gray-400'"
                              x-text="idOf(customerId) ? idOf(customerId).name : 'Pelanggan (opsional)'"></span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                         class="absolute z-30 left-0 right-0 mt-1.5 bg-white rounded-xl border border-gray-200 shadow-[0_12px_32px_rgba(15,23,42,.14)] overflow-hidden">
                        <div class="p-2 border-b border-gray-100">
                            <input x-ref="q" x-model="q" type="text" placeholder="Cari nama atau no. HP"
                                   class="w-full text-sm rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 focus:bg-white">
                        </div>
                        <ul class="max-h-60 overflow-y-auto py-1">
                            <li>
                                <button type="button" @click="customerId = ''; open = false; q = ''; onCustomerChange()"
                                        class="w-full flex items-center justify-between px-3 py-2.5 text-sm text-left hover:bg-gray-50"
                                        :class="!idOf(customerId) ? 'text-emerald-700 font-semibold' : 'text-gray-500'">
                                    Tanpa pelanggan
                                    <span x-show="!idOf(customerId)"><svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                                </button>
                            </li>
                            <template x-for="c in shown" :key="c.id">
                                <li>
                                    <button type="button" @click="customerId = String(c.id); open = false; q = ''; onCustomerChange()"
                                            class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-emerald-50 transition-colors">
                                        <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center shrink-0"
                                              x-text="c.name.trim().slice(0, 1).toUpperCase()"></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-semibold text-gray-900 truncate" x-text="c.name"></span>
                                            <span class="block text-xs text-gray-400" x-text="c.phone || 'Tanpa nomor HP'"></span>
                                        </span>
                                        <span x-show="String(c.id) === String(customerId)"><svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                                    </button>
                                </li>
                            </template>
                            <li x-show="shown.length === 0" class="px-3 py-4 text-center text-sm text-gray-400">Pelanggan tidak ditemukan</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Loyalty Points Badge --}}
            <div x-show="customerPoints !== null && customerId !== ''" class="mt-2">
                <div class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                    <div>
                        <p class="text-xs text-amber-700 font-semibold">
                            <span x-text="customerPoints"></span> poin
                            <span class="font-normal text-amber-600" x-text="'(≈ Rp '+fmt(customerPoints * pointValue)+')'"></span>
                        </p>
                    </div>
                    <button @click="toggleRedeem()"
                            :class="redeemActive ? 'bg-amber-600 text-white border-amber-600' : 'bg-white text-amber-700 border-amber-300'"
                            class="text-xs font-semibold px-2.5 py-1 rounded-lg border transition-colors">
                        Tukar Poin
                    </button>
                </div>

                {{-- Redeem Input --}}
                <div x-show="redeemActive" class="mt-2 bg-amber-50 border border-amber-200 rounded-xl p-3 space-y-2">
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-amber-700 font-medium shrink-0">Pakai poin:</label>
                        <input type="number" x-model.number="redeemPoints"
                               :max="customerPoints" min="0" step="1"
                               @input="redeemPoints=Math.min(redeemPoints, customerPoints); calcTotals()"
                               class="flex-1 px-2 py-1 text-sm border border-amber-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white text-center font-bold">
                        <button @click="redeemPoints=customerPoints; calcTotals()"
                                class="text-xs text-amber-700 bg-white border border-amber-300 px-2 py-1 rounded-lg hover:bg-amber-100 transition-colors shrink-0">
                            Semua
                        </button>
                    </div>
                    <p class="text-xs text-amber-600 text-center"
                       x-text="redeemPoints > 0 ? 'Diskon poin: Rp '+fmt(redeemPoints * pointValue) : 'Masukkan jumlah poin'">
                    </p>
                </div>
            </div>
        </div>

        {{-- Cart Items --}}
        <div class="flex-1 overflow-y-auto px-5">
            <template x-if="cart.length === 0">
                <div class="flex flex-col items-center justify-center h-full min-h-[220px] text-center">
                    <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <p class="text-[15px] font-bold text-gray-800">Belum ada pesanan</p>
                    <p class="text-sm text-gray-400 mt-1">{{ $business->isRetail() ? 'Pilih produk untuk mulai' : 'Pilih menu untuk mulai' }}</p>
                </div>
            </template>

            <template x-for="(item, idx) in cart" :key="idx">
                <div class="py-3 border-b border-gray-100 last:border-0">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <template x-if="item.isBundle">
                                    <span class="text-[12px] bg-purple-100 text-purple-700 font-bold px-1.5 py-0.5 rounded shrink-0 tracking-wide">BUNDLE</span>
                                </template>
                                <p class="text-[15px] font-semibold text-gray-900 leading-snug" x-text="item.name"></p>
                            </div>
                            <template x-if="item.isBundle && item.bundleItems">
                                <p class="text-xs text-purple-600 mt-0.5"
                                   x-text="item.bundleItems.map(i => i.qty+'× '+i.product_name).join(', ')"></p>
                            </template>
                            <template x-for="addon in item.selectedAddons">
                                <p class="text-xs text-emerald-700 mt-0.5" x-text="'+ ' + addon.name + ' (Rp ' + fmt(addon.price) + ')'"></p>
                            </template>
                            <p x-show="item.notes" class="text-xs text-amber-600 italic mt-0.5" x-text="item.notes"></p>
                        </div>
                        <p class="text-[15px] font-bold text-gray-900 shrink-0 tabular-nums" x-text="'Rp ' + fmt(item.lineTotal())"></p>
                    </div>
                    <div class="flex items-center justify-between mt-2.5">
                        <div class="inline-flex items-center bg-gray-100 rounded-full p-0.5">
                            <button @click="changeQty(idx,-1)" aria-label="Kurangi"
                                    class="w-7 h-7 rounded-full flex items-center justify-center text-gray-600 hover:bg-white hover:shadow-sm transition-all">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
                            </button>
                            <span class="w-9 text-center text-sm font-bold tabular-nums" x-text="item.qty"></span>
                            <button @click="changeQty(idx,1)" aria-label="Tambah"
                                    class="w-7 h-7 rounded-full flex items-center justify-center text-gray-600 hover:bg-white hover:shadow-sm transition-all">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            </button>
                        </div>
                        <div class="flex items-center gap-0.5">
                            <template x-if="!item.isBundle">
                                <button @click="openNote(idx)" title="Catatan"
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-emerald-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                            </template>
                            <button @click="removeItem(idx)" title="Hapus"
                                    class="w-8 h-8 rounded-full flex items-center justify-center text-gray-300 hover:bg-red-50 hover:text-red-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Summary + Controls --}}
        <div class="border-t border-gray-200 bg-white px-5 pt-3 pb-4 space-y-2.5 shadow-[0_-6px_16px_rgba(15,23,42,.04)]">
            {{-- Promo & diskon (dilipat supaya daftar pesanan lebih lega) --}}
            @php
                $promoList = $promotions->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'label' => $p->label()])->values();
            @endphp
            <div x-data="{ open: false, pOpen: false, list: {{ json_encode($promoList) }},
                           idOf(id) { return this.list.find(p => String(p.id) === String(id)); }, }">
                <div x-show="list.length > 0 || promoId" x-cloak>
                {{-- Belum ada promo: ajakan bergaris putus-putus --}}
                <button type="button" x-show="!(promoId || manualDisc > 0)" @click="open = !open"
                        class="w-full flex items-center gap-3 rounded-xl border border-dashed border-emerald-300 bg-emerald-50/40 hover:bg-emerald-50 hover:border-emerald-400 px-3 py-2.5 text-left transition-colors">
                    <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-gray-800">Pakai promo / voucher</span>
                        <span class="block text-xs text-gray-400">Potong harga pesanan ini</span>
                    </span>
                    <span class="shrink-0 text-xs font-bold text-emerald-700 bg-white border border-emerald-200 rounded-full px-3 py-1" x-text="open ? 'Tutup' : '+ Pakai'"></span>
                </button>

                {{-- Sudah dipakai: tiket hijau berisi nama dan jumlah hemat --}}
                <div x-show="promoId || manualDisc > 0" x-cloak
                     class="flex items-center gap-3 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 text-white px-3 py-2.5 shadow-[0_6px_16px_rgba(5,150,105,.25)]">
                    <span class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-bold truncate" x-text="idOf(promoId) ? idOf(promoId).name : 'Diskon manual'"></span>
                        <span class="block text-xs text-emerald-50" x-text="'Hemat Rp ' + fmt((promoDiscount || 0) + (manualDiscActual || 0))"></span>
                    </span>
                    <button type="button" @click="open = !open" class="shrink-0 text-xs font-bold bg-white/20 hover:bg-white/30 rounded-full px-3 py-1 transition-colors" x-text="open ? 'Tutup' : 'Ubah'"></button>
                    <button type="button" @click="promoId = ''; manualDisc = 0; open = false; calcTotals()" aria-label="Lepas promo"
                            class="shrink-0 w-6 h-6 rounded-full hover:bg-white/20 flex items-center justify-center transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="mt-2 space-y-2">
                    <div class="relative" @click.outside="pOpen = false" @keydown.escape="pOpen = false">
                        <button type="button" @click="pOpen = !pOpen"
                                :class="pOpen ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-gray-200 hover:border-gray-300'"
                                class="w-full flex items-center justify-between gap-2 border rounded-xl px-3 py-2.5 bg-white text-left transition-all">
                            <span class="min-w-0">
                                <span class="block truncate text-sm" :class="idOf(promoId) ? 'font-semibold text-gray-900' : 'text-gray-400'" x-text="idOf(promoId) ? idOf(promoId).name : 'Pilih promo'"></span>
                                <span x-show="idOf(promoId)" class="block text-xs text-emerald-700" x-text="idOf(promoId) ? idOf(promoId).label : ''"></span>
                            </span>
                            <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform" :class="pOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="pOpen" x-cloak x-transition.opacity.duration.150ms
                             class="absolute z-30 left-0 right-0 bottom-full mb-1.5 bg-white rounded-xl border border-gray-200 shadow-[0_-12px_32px_rgba(15,23,42,.14)] overflow-hidden">
                            <ul class="max-h-56 overflow-y-auto py-1">
                                <li>
                                    <button type="button" @click="promoId = ''; pOpen = false; calcTotals()"
                                            class="w-full flex items-center justify-between px-3 py-2.5 text-sm text-left hover:bg-gray-50"
                                            :class="!idOf(promoId) ? 'text-emerald-700 font-semibold' : 'text-gray-500'">
                                        Tanpa promo
                                        <span x-show="!idOf(promoId)"><svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                                    </button>
                                </li>
                                <template x-for="p in list" :key="p.id">
                                    <li>
                                        <button type="button" @click="promoId = String(p.id); pOpen = false; calcTotals()"
                                                class="w-full flex items-center gap-3 px-3 py-2.5 text-left hover:bg-emerald-50 transition-colors">
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-sm font-semibold text-gray-900 truncate" x-text="p.name"></span>
                                                <span class="block text-xs text-emerald-700" x-text="p.label"></span>
                                            </span>
                                            <span x-show="String(p.id) === String(promoId)"><svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                                        </button>
                                    </li>
                                </template>
                                <li x-show="list.length === 0" class="px-3 py-4 text-center text-sm text-gray-400">Belum ada promo aktif</li>
                            </ul>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            {{-- Totals --}}
            <div class="space-y-1 text-[13px]">
                <div class="flex justify-between text-gray-500">
                    <span>Subtotal</span><span class="tabular-nums" x-text="'Rp '+fmt(subtotal)"></span>
                </div>
                <div x-show="promoDiscount > 0" class="flex justify-between text-red-500">
                    <span>Promo</span><span class="tabular-nums" x-text="'- Rp '+fmt(promoDiscount)"></span>
                </div>
                <div x-show="manualDiscActual > 0" class="flex justify-between text-red-500">
                    <span>Diskon Manual</span><span class="tabular-nums" x-text="'- Rp '+fmt(manualDiscActual)"></span>
                </div>
                <div x-show="pointsDiscountAmt > 0" class="flex justify-between text-amber-600">
                    <span>Tukar Poin (<span x-text="redeemPoints"></span> pts)</span>
                    <span class="tabular-nums" x-text="'- Rp '+fmt(pointsDiscountAmt)"></span>
                </div>
                @if(($settings['enable_tax'] ?? false))
                <div class="flex justify-between text-gray-500">
                    <span>Pajak ({{ $settings['tax_percent'] ?? 10 }}%)</span><span class="tabular-nums" x-text="'Rp '+fmt(tax)"></span>
                </div>
                @endif
                @if(($settings['enable_service'] ?? false))
                <div class="flex justify-between text-gray-500">
                    <span>Service</span><span class="tabular-nums" x-text="'Rp '+fmt(service)"></span>
                </div>
                @endif
                <div x-show="deliveryFee > 0" class="flex justify-between text-orange-600">
                    <span>Ongkos Kirim</span><span class="tabular-nums" x-text="'Rp '+fmt(deliveryFee)"></span>
                </div>
                <div class="flex items-baseline justify-between pt-2.5 mt-1.5 border-t border-dashed border-gray-300">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total</span>
                    <span class="text-2xl leading-none font-extrabold text-emerald-700 tabular-nums" x-text="'Rp '+fmt(grandTotal)"></span>
                </div>
            </div>

            <div x-show="holdName || tableNumber" x-cloak
                 class="flex items-center gap-2 text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                <span class="truncate" x-text="holdName"></span>
                <span x-show="tableNumber" class="ml-auto shrink-0 bg-amber-100 px-2 py-0.5 rounded-full" x-text="'Meja ' + tableNumber"></span>
            </div>

            <div class="grid grid-cols-[1fr_1.7fr] gap-2.5">
                <button @click="openHold()" :disabled="cart.length===0"
                        class="text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 py-3 rounded-xl transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    Hold Order
                </button>
                <button @click="openPayment()" :disabled="cart.length===0"
                        class="inline-flex items-center justify-center gap-2 text-[15px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 py-3 rounded-xl transition-all shadow-[0_8px_20px_rgba(5,150,105,.30)] disabled:opacity-40 disabled:shadow-none disabled:cursor-not-allowed">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>Bayar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Phone-only cart bar --}}
<button x-show="!mobileCart" @click="mobileCart=true"
        class="md:hidden fixed bottom-3 inset-x-3 z-30 flex items-center justify-between bg-emerald-600 text-white rounded-2xl px-4 py-3.5 shadow-xl active:scale-[0.98] transition-transform">
    <span class="text-sm font-semibold"><span x-text="cartCount()"></span> item</span>
    <span class="text-base font-bold" x-text="'Rp ' + fmt(grandTotal)"></span>
    <span class="text-sm font-semibold">Lihat &rsaquo;</span>
</button>

{{-- ══════════════════════════════════ MODALS ══════════════════════════════════ --}}

{{-- Dialog konfirmasi --}}
<div x-show="dlg.open" x-cloak
     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     @keydown.escape.window="dlg.open && dlgAnswer(false)"
     @click.self="dlgAnswer(false)"
     class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-[2px]"
     role="alertdialog" aria-modal="true" :aria-label="dlg.title">
    <div x-show="dlg.open"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         class="w-full max-w-sm bg-white rounded-2xl shadow-[0_24px_60px_rgba(15,23,42,.30)] p-6 text-center" @click.stop>
        <div class="w-14 h-14 rounded-full mx-auto flex items-center justify-center mb-4"
             :class="dlg.danger ? 'bg-red-50 text-red-500' : 'bg-emerald-50 text-emerald-600'">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </div>
        <h3 class="text-lg font-extrabold text-gray-900" x-text="dlg.title"></h3>
        <p class="mt-1.5 text-sm text-gray-500 leading-relaxed" x-text="dlg.message"></p>
        <div class="mt-6 grid grid-cols-2 gap-3">
            <button type="button" @click="dlgAnswer(false)" x-ref="dlgCancel"
                    class="py-3 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors" x-text="dlg.cancel"></button>
            <button type="button" @click="dlgAnswer(true)"
                    :class="dlg.danger ? 'bg-red-500 hover:bg-red-600 shadow-[0_8px_20px_rgba(239,68,68,.28)]' : 'bg-emerald-600 hover:bg-emerald-700 shadow-[0_8px_20px_rgba(5,150,105,.28)]'"
                    class="py-3 rounded-xl text-sm font-bold text-white transition-all" x-text="dlg.ok"></button>
        </div>
    </div>
</div>

{{-- Variant/Addon Modal --}}
<div x-show="variantModal" x-cloak @click.self="variantModal=false"
     class="fixed inset-0 z-50 flex overflow-y-auto p-3 sm:p-4 bg-black/60">
    <div class="m-auto bg-white rounded-2xl shadow-2xl w-full max-w-sm p-5" @click.stop>
        <h3 class="font-bold text-gray-900 text-base mb-4" x-text="selProd?.name"></h3>

        <template x-if="selProd?.variants?.length">
            <div class="mb-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Pilih Varian *</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="v in selProd.variants" :key="v.id">
                        <button @click="selVariant=v"
                                :class="selVariant?.id===v.id ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-700 border-gray-300 hover:border-emerald-400'"
                                class="text-xs px-3 py-2 rounded-xl border transition-colors font-medium">
                            <span x-text="v.name"></span>
                            <span class="opacity-70" x-text="v.price_adjustment!==0 ? (v.price_adjustment>0 ? ' (+Rp '+fmt(v.price_adjustment)+')' : ' (-Rp '+fmt(Math.abs(v.price_adjustment))+')') : ''"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="selProd?.addons?.length">
            <div class="mb-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Add-on / Topping</p>
                <div class="space-y-1">
                    <template x-for="a in selProd.addons" :key="a.id">
                        <label class="flex items-center justify-between p-2.5 rounded-xl hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center gap-2.5">
                                <input type="checkbox" :value="a.id" x-model="selAddons" class="h-4 w-4 text-emerald-600 rounded border-gray-300">
                                <span class="text-sm" x-text="a.name"></span>
                            </div>
                            <span class="text-sm font-semibold text-emerald-700" x-text="'+Rp '+fmt(a.price)"></span>
                        </label>
                    </template>
                </div>
            </div>
        </template>

        <div class="mb-5">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Jumlah</p>
            <div class="flex items-center gap-3">
                <button @click="mQty=Math.max(1,mQty-1)" class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center font-bold text-lg hover:bg-gray-200">−</button>
                <input type="number" x-model.number="mQty" min="1" class="w-16 text-center text-xl font-bold border-2 border-gray-200 rounded-xl py-1.5 focus:outline-none focus:border-emerald-500">
                <button @click="mQty++" class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center font-bold text-lg hover:bg-gray-200">+</button>
            </div>
        </div>

        <div class="flex gap-2">
            <button @click="variantModal=false" class="flex-1 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 py-3 rounded-xl">Batal</button>
            <button @click="confirmAdd()"
                    :disabled="selProd?.variants?.length>0 && !selVariant"
                    class="flex-1 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 py-3 rounded-xl disabled:opacity-40">
                Tambah ke Order
            </button>
        </div>
    </div>
</div>

{{-- Note Modal --}}
<div x-show="noteModal" x-cloak @click.self="noteModal=false"
     class="fixed inset-0 z-50 flex overflow-y-auto p-3 sm:p-4 bg-black/60">
    <div class="m-auto bg-white rounded-2xl shadow-xl w-full max-w-sm p-5" @click.stop>
        <h3 class="font-bold text-gray-900 mb-3">Catatan untuk Item</h3>
        <textarea x-model="noteText" rows="3" placeholder="Contoh: tanpa bawang, level pedas 2..."
                  class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  x-ref="noteInput"></textarea>
        <div class="flex gap-2 mt-3">
            <button @click="noteModal=false" class="flex-1 text-sm font-medium text-gray-700 bg-gray-100 py-2.5 rounded-xl">Batal</button>
            <button @click="saveNote()" class="flex-1 text-sm font-bold text-white bg-emerald-600 py-2.5 rounded-xl">Simpan</button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════ PAYMENT MODAL ═══════════════════════════ --}}
<div x-show="payModal" x-cloak @click.self="payModal=false"
     class="fixed inset-0 z-50 flex overflow-y-auto p-3 sm:p-4 bg-slate-900/55 backdrop-blur-[2px]">
    <div class="m-auto bg-white rounded-3xl shadow-[0_24px_60px_rgba(15,23,42,.30)] w-full overflow-hidden transition-[max-width]"
         :class="showQrisScreen ? 'max-w-md' : 'max-w-md md:max-w-3xl'" @click.stop>

        {{-- STEP 1: Pilih Metode Pembayaran (hanya Cash dan QRIS) --}}
        <div x-show="!showQrisScreen" class="flex flex-col max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-2rem)]">

            <div class="flex items-center justify-between px-4 sm:px-5 pt-3 sm:pt-4 pb-2.5 sm:pb-3 border-b border-gray-100 shrink-0">
                <h3 class="font-extrabold text-gray-900 text-lg">Pembayaran</h3>
                <button @click="payModal=false" class="w-9 h-9 -mr-2 rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 flex items-center justify-center transition-colors" aria-label="Tutup">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto md:grid md:grid-cols-[1fr_1.15fr]">

                {{-- KIRI: total + metode --}}
                <div class="p-4 sm:p-5 space-y-3 sm:space-y-4 md:bg-emerald-50/50 md:border-r md:border-gray-100">
                    <div class="rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-700 text-white px-4 sm:px-5 py-3 sm:py-4 shadow-[0_10px_24px_rgba(5,150,105,.28)]">
                        <p class="text-[12px] font-bold uppercase tracking-wider text-emerald-100">Total tagihan</p>
                        <p class="mt-1 text-3xl sm:text-4xl leading-none font-extrabold tabular-nums" x-text="'Rp '+fmt(grandTotal)"></p>
                        <p x-show="pointsDiscountAmt > 0" x-cloak class="mt-2 text-xs text-amber-200 font-medium">
                            Termasuk potongan poin Rp <span x-text="fmt(pointsDiscountAmt)"></span>
                        </p>
                    </div>

                    <div>
                        <p class="text-[12px] font-bold uppercase tracking-wider text-gray-400 mb-2">Metode pembayaran</p>
                        <div class="grid grid-cols-2 gap-2.5">
                            <button @click="selectPayMethod('cash')"
                                    :class="payMethod==='cash' ? 'bg-emerald-600 text-white border-emerald-600 shadow-[0_6px_16px_rgba(5,150,105,.28)]' : 'bg-white text-gray-700 border-gray-200 hover:border-emerald-400'"
                                    class="flex items-center justify-center gap-2.5 h-12 sm:h-14 rounded-xl border-2 text-[15px] font-bold transition-all">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Cash
                            </button>
                            <button @click="selectPayMethod('qris')"
                                    :class="payMethod==='qris' ? 'bg-emerald-600 text-white border-emerald-600 shadow-[0_6px_16px_rgba(5,150,105,.28)]' : 'bg-white text-gray-700 border-gray-200 hover:border-emerald-400'"
                                    class="flex items-center justify-center gap-2.5 h-14 rounded-xl border-2 text-[15px] font-bold transition-all">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>QRIS
                            </button>
                        </div>
                    </div>

                    {{-- Bayar terpisah --}}
                    <button type="button" @click="toggleSplit()" class="w-full flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-3.5 py-2 sm:py-2.5 text-left hover:border-emerald-300 transition-colors">
                        <span>
                            <span class="block text-sm font-semibold text-gray-800">Bayar terpisah</span>
                            <span class="hidden sm:block text-xs text-gray-400">Sebagian cash, sebagian QRIS</span>
                        </span>
                        <span class="relative w-10 h-6 rounded-full transition-colors shrink-0" :class="isSplit ? 'bg-emerald-600' : 'bg-gray-300'">
                            <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform" :class="isSplit ? 'translate-x-4' : ''"></span>
                        </span>
                    </button>
                </div>

                {{-- KANAN: rincian sesuai metode --}}
                <div class="px-4 pb-4 pt-1 sm:p-5 space-y-3 sm:space-y-4">

                    {{-- Cash --}}
                    <div x-show="payMethod==='cash' && !isSplit" class="space-y-3">
                        <p class="text-[12px] font-bold uppercase tracking-wider text-gray-400">Uang diterima</p>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-semibold">Rp</span>
                            <input type="number" x-model.number="paidAmt" min="0" step="1000" @input="calcChange()" inputmode="numeric"
                                   class="pl-12 pr-4 w-full h-12 sm:h-14 border-2 border-gray-200 rounded-xl text-2xl sm:text-3xl font-extrabold text-gray-900 text-right tabular-nums focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all">
                        </div>
                        <div class="flex gap-2 overflow-x-auto md:flex-wrap md:overflow-visible pb-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            <button @click="paidAmt=grandTotal; calcChange()"
                                    :class="paidAmt===grandTotal ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'"
                                    class="shrink-0 text-xs font-bold px-3.5 py-2 rounded-full border transition-colors">Uang pas</button>
                            <template x-for="amt in quickAmounts" :key="amt">
                                <button @click="paidAmt=amt; calcChange()"
                                        :class="paidAmt===amt ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white border-gray-200 text-gray-600 hover:border-emerald-400'"
                                        class="shrink-0 text-xs font-semibold px-3.5 py-2 rounded-full border transition-colors tabular-nums"
                                        x-text="'Rp '+fmt(amt)"></button>
                            </template>
                        </div>
                        <div class="min-h-[56px] sm:min-h-[64px]">
                            <div x-show="paidAmt >= grandTotal" class="flex justify-between items-center bg-sky-50 border border-sky-200 rounded-xl px-4 py-2.5 sm:py-3">
                                <span class="text-sm text-sky-700 font-semibold">Kembalian</span>
                                <span class="text-2xl font-extrabold text-sky-800 tabular-nums" x-text="'Rp '+fmt(change)"></span>
                            </div>
                            <div x-show="paidAmt > 0 && paidAmt < grandTotal" x-cloak class="flex justify-between items-center bg-red-50 border border-red-200 rounded-xl px-4 py-2.5 sm:py-3">
                                <span class="text-sm text-red-600 font-semibold">Kurang</span>
                                <span class="text-2xl font-extrabold text-red-600 tabular-nums" x-text="'Rp '+fmt(grandTotal - paidAmt)"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Petunjuk QRIS --}}
                    <div x-show="payMethod === 'qris' && !isSplit" x-cloak>
                        <p class="text-[12px] font-bold uppercase tracking-wider text-gray-400 mb-2">Pembayaran QRIS</p>
                    @if($qrisData['use_dynamic'])
                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex items-center gap-3 text-emerald-700">
                        <svg class="w-6 h-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <div>
                            <p class="text-sm font-semibold text-emerald-800">QRIS Dinamis — nominal terkunci</p>
                            <p class="text-xs text-emerald-600">QR dibuat otomatis sesuai total belanja</p>
                        </div>
                    </div>
                    @elseif($midtransEnabled)
                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex items-center gap-3 text-emerald-700">
                        <svg class="w-6 h-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <div>
                            <p class="text-sm font-semibold text-emerald-800">QRIS Dinamis (Midtrans)</p>
                            <p class="text-xs text-emerald-600">QR unik akan dibuat otomatis per transaksi</p>
                        </div>
                    </div>
                    @elseif($qrisData['is_set'])
                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex items-center gap-3 text-emerald-700">
                        <svg class="w-6 h-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        <div>
                            <p class="text-sm font-semibold text-emerald-800">QR Code siap ditampilkan</p>
                            <p class="text-xs text-emerald-600">Klik "Tampilkan QR" untuk lanjut ke layar pembayaran QRIS</p>
                        </div>
                    </div>
                    @else
                    <div class="bg-orange-50 border border-orange-200 rounded-xl p-3 flex items-center gap-3 text-emerald-700">
                        <svg class="w-6 h-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <div>
                            <p class="text-sm font-semibold text-orange-800">QR QRIS belum dikonfigurasi</p>
                            <p class="text-xs text-orange-600">
                                <a href="{{ route('settings.qris') }}" target="_blank" class="underline">Klik di sini</a>
                                untuk upload QR QRIS di Pengaturan
                            </p>
                        </div>
                    </div>
                    @endif
                    </div>

                    {{-- Bayar terpisah: baris pembayaran --}}
                    <div x-show="isSplit" x-cloak class="space-y-2.5">
                        <p class="text-[12px] font-bold uppercase tracking-wider text-gray-400">Rincian pembayaran</p>
                        <template x-for="(sp, si) in splitPayments" :key="si">
                            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white p-2">
                                <div class="grid grid-cols-2 gap-0.5 p-0.5 bg-gray-100 rounded-lg shrink-0">
                                    <button type="button" @click="sp.method = 'cash'"
                                            :class="sp.method === 'cash' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500'"
                                            class="text-xs font-bold px-2.5 py-1.5 rounded-md transition-all">Cash</button>
                                    <button type="button" @click="sp.method = 'qris'"
                                            :class="sp.method === 'qris' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500'"
                                            class="text-xs font-bold px-2.5 py-1.5 rounded-md transition-all">QRIS</button>
                                </div>
                                <div class="relative flex-1 min-w-0">
                                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                                    <input type="number" x-model.number="sp.amount" min="0" step="1000" @input="calcSplitChange()" inputmode="numeric"
                                           class="pl-8 pr-2 w-full h-9 text-sm font-bold text-right border border-gray-200 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 tabular-nums">
                                </div>
                                <button type="button" @click="removeSplit(si)" x-show="splitPayments.length > 1" aria-label="Hapus baris"
                                        class="w-8 h-8 rounded-full text-gray-300 hover:bg-red-50 hover:text-red-500 flex items-center justify-center shrink-0 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                        <button type="button" @click="addSplit()"
                                class="w-full text-sm font-semibold text-emerald-700 border border-dashed border-emerald-300 py-2.5 rounded-xl hover:bg-emerald-50 transition-colors">
                            + Tambah cara bayar
                        </button>
                        <div class="flex justify-between text-xs font-semibold px-1"
                             :class="splitTotal < grandTotal ? 'text-red-600' : (splitTotal > grandTotal ? 'text-sky-700' : 'text-emerald-700')">
                            <span>Total dibayar</span>
                            <span class="tabular-nums" x-text="'Rp '+fmt(splitTotal)+ (splitTotal < grandTotal ? ' (kurang Rp '+fmt(grandTotal-splitTotal)+')' : (splitTotal > grandTotal ? ' (lebih Rp '+fmt(splitTotal-grandTotal)+')' : ' (pas)'))"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tombol aksi: selalu terlihat, tidak ikut tergulung --}}
            <div class="shrink-0 px-4 sm:px-5 py-3 sm:py-3.5 bg-white border-t border-gray-100 flex gap-3">
                <button @click="payModal=false"
                        class="flex-1 text-[15px] font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 h-12 rounded-xl transition-colors">
                    Batal
                </button>
                <button x-show="payMethod==='qris' && !isSplit"
                        @click="openQrisScreen()"
                        :disabled="!qrisIsSet"
                        class="flex-[1.6] inline-flex items-center justify-center gap-2 text-[15px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 h-12 rounded-xl disabled:opacity-40 disabled:cursor-not-allowed shadow-[0_8px_20px_rgba(5,150,105,.28)] transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>Tampilkan QR
                </button>
                <button x-show="payMethod!=='qris' || isSplit"
                        @click="processPayment()"
                        :disabled="processing||((!isSplit)&&payMethod==='cash'&&paidAmt<grandTotal)||(isSplit&&splitTotal<grandTotal)"
                        class="flex-[1.6] inline-flex items-center justify-center gap-2 text-[15px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 h-12 rounded-xl disabled:opacity-40 disabled:cursor-not-allowed shadow-[0_8px_20px_rgba(5,150,105,.28)] transition-colors">
                    <span x-show="!processing" class="inline-flex items-center gap-2"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Proses bayar</span>
                    <span x-show="processing">Memproses...</span>
                </button>
            </div>
        </div>

        {{-- STEP 2: QRIS Screen --}}
        <div x-show="showQrisScreen" class="text-center">
            <div class="bg-gradient-to-b from-emerald-600 to-emerald-700 rounded-t-2xl px-5 py-4">
                <div class="flex items-center justify-between">
                    <button @click="cancelQrisScreen()"
                            class="text-white/70 hover:text-white text-sm flex items-center gap-1">
                        ← Kembali
                    </button>
                    <p class="text-xs text-emerald-200 font-medium uppercase tracking-wider">Pembayaran QRIS</p>
                    <div class="w-16"></div>
                </div>
                <p class="text-white text-xs mt-3 opacity-80">Nominal yang harus dibayar</p>
                <p class="text-white text-4xl font-extrabold mt-1" x-text="'Rp '+fmt(grandTotal)"></p>
            </div>
            <div class="px-5 py-4">

                {{-- QR Image: loading / Midtrans dynamic / static --}}
                <div class="bg-white border-4 border-gray-100 rounded-2xl p-4 inline-block shadow-lg mx-auto">
                    {{-- Loading spinner (Midtrans generating QR) --}}
                    <div x-show="qrisLoading" class="w-52 h-52 flex flex-col items-center justify-center gap-3">
                        <svg class="w-10 h-10 text-emerald-500 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <p class="text-xs text-gray-500">Membuat QR...</p>
                    </div>
                    {{-- Midtrans dynamic QR --}}
                    <div x-show="!qrisLoading && midtransEnabled && midtransQrUrl">
                        <img :src="midtransQrUrl" alt="QR QRIS Midtrans" class="w-52 h-52 object-contain">
                    </div>
                    {{-- Expired / error state --}}
                    <div x-show="!qrisLoading && midtransEnabled && !midtransQrUrl && !qrisExpired" class="w-52 h-52 bg-gray-50 flex items-center justify-center rounded-xl">
                        <p class="text-gray-400 text-sm">QR gagal dibuat</p>
                    </div>
                    {{-- Locally generated dynamic QRIS --}}
                    <div x-show="!qrisLoading && qrisUseDynamic && qrisSvg" x-html="qrisSvg" class="w-52 h-52 [&>svg]:w-full [&>svg]:h-full"></div>

                    {{-- Static QRIS image --}}
                    @if($qrisData['image'])
                    <div x-show="!midtransEnabled && !qrisUseDynamic">
                        <img src="{{ $qrisData['image'] }}" alt="QR QRIS" class="w-52 h-52 object-contain">
                    </div>
                    @else
                    {{-- Only a real fallback: with dynamic QRIS on, the QR above is the one to scan. --}}
                    <div x-show="!midtransEnabled && !qrisUseDynamic" class="w-52 h-52 bg-gray-50 flex items-center justify-center rounded-xl">
                        <p class="text-gray-400 text-sm">QR tidak tersedia</p>
                    </div>
                    @endif
                </div>

                <div class="mt-3 mb-1">
                    <p class="text-base font-bold text-gray-900">{{ $qrisData['merchant_name'] }}</p>
                    @if($qrisData['nmid'])
                    <p class="text-xs text-gray-400 font-mono mt-0.5">NMID: {{ $qrisData['nmid'] }}</p>
                    @endif
                </div>
                <div class="flex items-center justify-center gap-2 mt-2 mb-3">
                    <span class="bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full">QRIS</span>
                    <span class="text-xs text-gray-400">Berlaku untuk semua aplikasi pembayaran</span>
                </div>

                {{-- Auto-polling status (Midtrans only) --}}
                <div x-show="midtransEnabled && midtransQrUrl && !qrisLoading" class="bg-blue-50 border border-blue-200 rounded-xl p-3 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <p class="text-xs text-blue-700">Menunggu pembayaran... QR akan otomatis terkonfirmasi saat pelanggan bayar.</p>
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 text-sm text-amber-800">
                    <p class="font-semibold mb-0.5">Instruksi untuk pelanggan:</p>
                    <ol class="text-xs text-amber-700 space-y-1 text-left">
                        <li>1. Buka aplikasi pembayaran (GoPay, OVO, DANA, dll)</li>
                        <li>2. Pilih <strong>Scan QR</strong> atau <strong>Bayar</strong></li>
                        <li>3. Arahkan kamera ke QR Code ini</li>
                        <li>4. Nominal sudah tercantum — tinggal konfirmasi</li>
                        <li>5. Kasir klik "Konfirmasi Sudah Dibayar" jika tidak otomatis</li>
                    </ol>
                </div>

                <button @click="confirmQrisPayment()" :disabled="processing || qrisLoading"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-4 rounded-2xl text-base transition-colors shadow-lg shadow-emerald-200 disabled:opacity-50">
                    <span x-show="!processing" class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        Konfirmasi Sudah Dibayar
                    </span>
                    <span x-show="processing" class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Memproses...
                    </span>
                </button>
                <button @click="cancelQrisScreen()"
                        class="w-full mt-2 text-sm text-gray-400 hover:text-gray-600 py-2 transition-colors">
                    Batalkan Pembayaran
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Draft Modal --}}
<div x-show="draftModal" x-cloak @click.self="draftModal=false" @keydown.escape.window="draftModal && (draftModal = false)"
     x-transition.opacity.duration.150ms
     class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/55 backdrop-blur-[2px]">
    <div class="w-full max-w-lg bg-white rounded-3xl shadow-[0_24px_60px_rgba(15,23,42,.30)] overflow-hidden flex flex-col max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-2rem)]" @click.stop>

        <div class="flex items-center justify-between px-5 pt-4 pb-3 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2.5">
                <h3 class="font-extrabold text-gray-900 text-lg">Order Hold</h3>
                <span x-show="drafts.length" class="text-xs font-bold text-amber-800 bg-amber-100 rounded-full px-2.5 py-0.5" x-text="drafts.length + ' pesanan'"></span>
            </div>
            <button @click="draftModal=false" aria-label="Tutup"
                    class="w-9 h-9 -mr-2 rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3 bg-gray-50/60">
            <template x-for="d in drafts" :key="d.id">
                <div class="rounded-2xl border border-gray-200 bg-white p-4 hover:border-emerald-300 hover:shadow-[0_6px_16px_rgba(15,23,42,.06)] transition-all">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-[15px] font-bold text-gray-900 truncate"
                                   x-text="d.hold_name || d.customer?.name || d.order_number"></p>
                                <span x-show="d.table_number"
                                      class="inline-flex items-center gap-1 text-xs font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 10V6l1-3h12l1 3v4M5 10v11h14V10M9 21v-6h6v6"/></svg><span x-text="'Meja ' + d.table_number"></span>
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5" x-text="'Disimpan ' + draftTime(d.created_at) + ' · ' + (d.items || []).length + ' item'"></p>
                        </div>
                        <p class="text-lg font-extrabold text-emerald-700 shrink-0 tabular-nums" x-text="'Rp ' + fmt(d.grand_total)"></p>
                    </div>
                    <p class="mt-2.5 text-[13px] text-gray-500 leading-snug line-clamp-2"
                       x-text="(d.items||[]).map(i => fmtQty(i.qty) + 'x ' + i.product_name + (i.variant_name ? ' (' + i.variant_name + ')' : '')).join(', ')"></p>
                    <div class="mt-3.5 flex gap-2">
                        <button @click="resumeDraft(d.id)"
                                class="flex-1 h-11 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-[0_6px_16px_rgba(5,150,105,.25)] transition-colors">
                            Lanjutkan pesanan
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        </button>
                        <button @click="deleteDraft(d)" aria-label="Hapus order hold" title="Hapus"
                                class="w-11 h-11 rounded-xl border border-gray-200 text-gray-400 hover:border-red-200 hover:bg-red-50 hover:text-red-500 flex items-center justify-center transition-colors">
                            <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            </template>

            <template x-if="!drafts.length">
                <div class="py-12 text-center">
                    <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <p class="text-[15px] font-bold text-gray-800">Belum ada order hold</p>
                    <p class="text-sm text-gray-400 mt-1">Pesanan yang Anda tahan akan muncul di sini.</p>
                </div>
            </template>
        </div>
    </div>
</div>

{{-- Hold Modal: who the order is for and where they sit, so it is easy to find again --}}
<div x-show="holdModal" x-cloak @click.self="holdModal=false" @keydown.escape.window="holdModal && (holdModal = false)"
     x-transition.opacity.duration.150ms
     class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/55 backdrop-blur-[2px]">
    <form class="w-full max-w-sm bg-white rounded-3xl shadow-[0_24px_60px_rgba(15,23,42,.30)] overflow-hidden" @click.stop @submit.prevent="holdOrder()">
        <div class="flex items-start justify-between px-5 pt-5 pb-3">
            <div>
                <h3 class="font-extrabold text-gray-900 text-lg">Simpan ke Hold</h3>
                <p class="text-sm text-gray-400 mt-0.5">Beri tanda supaya mudah dicari lagi.</p>
            </div>
            <button type="button" @click="holdModal=false" aria-label="Tutup"
                    class="w-9 h-9 -mr-2 -mt-1 rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="px-5 pb-5 space-y-4">
            <div class="flex items-center justify-between rounded-2xl bg-emerald-50 border border-emerald-100 px-4 py-3">
                <span class="text-sm font-semibold text-emerald-800" x-text="cart.length + ' item'"></span>
                <span class="text-lg font-extrabold text-emerald-700 tabular-nums" x-text="'Rp ' + fmt(grandTotal)"></span>
            </div>

            <div>
                <label class="block text-[12px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Nama pemesan <span class="text-red-500">*</span></label>
                <input type="text" x-model="holdName" x-ref="holdNameInput" maxlength="60" required
                       placeholder="Contoh: Pak Budi"
                       class="w-full h-12 px-3.5 border border-gray-200 rounded-xl text-[15px] text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all">
            </div>
            <div>
                <label class="block text-[12px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Nomor meja</label>
                <input type="text" x-model="tableNumber" maxlength="20" inputmode="text"
                       placeholder="Kosongkan kalau takeaway"
                       class="w-full h-12 px-3.5 border border-gray-200 rounded-xl text-[15px] text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all">
            </div>

            <p x-show="holdError" x-cloak class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl px-3 py-2" x-text="holdError"></p>

            <div class="flex gap-3 pt-1">
                <button type="button" @click="holdModal=false"
                        class="flex-1 h-12 rounded-xl border border-gray-300 bg-white text-[15px] font-semibold text-gray-700 hover:bg-gray-50 transition-colors">Batal</button>
                <button type="submit" :disabled="holding"
                        class="flex-[1.5] h-12 inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[15px] font-bold shadow-[0_8px_20px_rgba(5,150,105,.28)] transition-colors disabled:opacity-60">
                    <span x-show="!holding" class="inline-flex items-center gap-2"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Simpan ke hold</span>
                    <span x-show="holding">Menyimpan…</span>
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Success Modal --}}
<div x-show="successModal" x-cloak
     class="fixed inset-0 z-[60] flex items-center justify-center p-4"
     style="background:rgba(0,0,0,0.65)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">

        {{-- Green Header — inline styles agar tidak bergantung pada Tailwind build --}}
        <div class="text-center px-6 pt-8 pb-6"
             style="background:linear-gradient(160deg,#059669 0%,#047857 100%)">

            {{-- Animated ring + checkmark --}}
            <div class="mx-auto mb-4 flex items-center justify-center"
                 style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.18);box-shadow:0 0 0 6px rgba(255,255,255,0.12)">
                <div class="flex items-center justify-center bg-white shadow-lg"
                     style="width:56px;height:56px;border-radius:50%">
                    <svg style="width:28px;height:28px;color:#059669" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"
                              style="stroke-dasharray:30;stroke-dashoffset:0;animation:checkDraw 0.5s ease-out forwards"/>
                    </svg>
                </div>
            </div>

            <h3 class="font-extrabold text-white" style="font-size:1.4rem;margin-bottom:4px">
                Pembayaran Berhasil!
            </h3>
            <p style="color:rgba(209,250,229,0.9);font-size:0.8rem" x-text="lastOrderNo"></p>
        </div>

        {{-- Content --}}
        <div class="px-6 py-5 space-y-3">

            {{-- Kembalian --}}
            <div x-show="lastChange > 0"
                 class="rounded-2xl p-4 text-center"
                 style="background:#eff6ff;border:1px solid #bfdbfe">
                <p class="text-xs font-semibold uppercase tracking-wide mb-1" style="color:#2563eb">Kembalian</p>
                <p class="font-extrabold" style="font-size:1.7rem;color:#1d4ed8" x-text="'Rp ' + fmt(lastChange)"></p>
            </div>

            {{-- Poin ditukar --}}
            <div x-show="lastPointsRedeemed > 0"
                 class="rounded-xl p-3 text-center"
                 style="background:#fffbeb;border:1px solid #fde68a">
                <p class="text-xs font-semibold" style="color:#92400e">
                    <span x-text="lastPointsRedeemed"></span> poin ditukar
                    = Rp <span x-text="fmt(lastPointsRedeemed * pointValue)"></span>
                </p>
            </div>

            {{-- Buttons --}}
            <div class="grid grid-cols-2 gap-2 pt-1">
                <button type="button" x-show="printVia !== 'none'" @click="printReceipt()" :disabled="printing"
                   class="flex items-center justify-center gap-1.5 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 py-3 rounded-xl transition-colors text-center disabled:opacity-60">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span x-text="printing ? 'Menyiapkan…' : 'Cetak Struk'"></span>
                </button>
                <button @click="newTransaction()"
                        class="flex items-center justify-center gap-1.5 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 py-3 rounded-xl transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Transaksi Baru
                </button>
                <button type="button" x-show="kitchenEnabled" @click="printKitchen()"
                        class="col-span-2 flex items-center justify-center gap-1.5 text-sm font-semibold text-orange-700 bg-orange-50 hover:bg-orange-100 border border-orange-200 py-2.5 rounded-xl transition-colors">
                    <span x-text="inApp ? 'Cetak ulang ke Dapur' : 'Cetak ke Dapur'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes checkDraw {
    from { stroke-dashoffset: 30; }
    to   { stroke-dashoffset: 0; }
}
</style>

{{-- Barcode Error Toast --}}
<div x-show="barcodeErrorModal" x-cloak x-transition
     class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 bg-red-600 text-white text-sm font-semibold px-5 py-3 rounded-2xl shadow-xl">
    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
    </svg>
    <span x-text="barcodeErrorMsg"></span>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function posApp() {
    return {
        // Data dari server
        allProducts:   {!! json_encode($productsJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
        allBundles:    {!! json_encode($bundlesJson,  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
        settings:      {!! json_encode($settings ?? new stdClass) !!},
        promotionData: {!! json_encode($promotions->map(fn($p) => ['id'=>$p->id,'type'=>$p->type,'value'=>(float)$p->value,'min_order'=>(float)$p->min_order,'buy'=>(int)$p->buy_qty,'get'=>(int)$p->get_qty,'cat'=>$p->product_category_id])->values()) !!},
        qrisIsSet:       {!! $qrisData['is_set'] ? 'true' : 'false' !!},
        qrisUseDynamic:  {!! $qrisData['use_dynamic'] ? 'true' : 'false' !!},
        qrisSvg:         '',
        midtransEnabled: {!! $midtransEnabled ? 'true' : 'false' !!},
        staticQrisSet:   {!! $qrisData['qris_image'] ?? false ? 'true' : 'false' !!},

        // Midtrans dynamic QRIS state
        midtransQrUrl:   null,
        midtransOrderId: null,
        midtransPolling: null,
        qrisLoading:     false,
        qrisExpired:     false,

        // Barcode
        barcodeBuffer: '', barcodeLastKey: 0, barcodeTimer: null,

        // Cart
        cart: [],
        mobileCart: false,
        cartW: 340, resizing: false, isDesktop: true,
        customerId: '', promoId: '', manualDisc: 0,
        subtotal: 0, promoDiscount: 0, manualDiscActual: 0, pointsDiscountAmt: 0,
        tax: 0, service: 0, grandTotal: 0,
        paidAmt: 0, change: 0, payMethod: 'cash',
        processing: false, currentTime: '',
        draftCount: 0, drafts: [],
        // Hold: who and where. resumedDraftId is the held order now in the
        // cart, removed by the server once this cart is paid or held again.
        holdModal: false, holding: false, holdError: '',
        holdName: '', tableNumber: '', resumedDraftId: null,
        showBundles: false,

        // Order type + Delivery
        // Retail shops have no dine-in; a counter sale is the starting point.
        defaultOrderType: @json($business->isRetail() ? 'takeaway' : 'dine_in'),
        orderType: @json($business->isRetail() ? 'takeaway' : 'dine_in'),
        deliveryPlatform: '',
        deliveryFee: 0,
        deliveryAddress: '',
        platformOrderNo: '',

        // Loyalty
        customerPoints: null,
        pointValue: {{ (float)($settings['point_value_rupiah'] ?? 1) }},
        redeemActive: false,
        redeemPoints: 0,

        // Split billing
        isSplit: false,
        splitPayments: [{ method: 'cash', amount: 0 }],
        splitTotal: 0,

        // Modals
        variantModal: false, noteModal: false, payModal: false,
        draftModal: false, successModal: false,
        dlg: { open: false, title: '', message: '', ok: 'Ya', cancel: 'Batal', danger: true, resolve: null },
        showQrisScreen: false,
        barcodeErrorModal: false, barcodeErrorMsg: '',

        // Temp
        selProd: null, selVariant: null, selAddons: [], mQty: 1,
        noteIdx: null, noteText: '',
        lastOrderNo: '', lastReceiptUrl: '', lastChange: 0, printing: false,
        printVia: @json(($settings['print_method'] ?? 'browser') === 'rawbt' ? 'rawbt' : 'browser'),
        lastPointsRedeemed: 0,
        // Some category is marked "Kirim ke dapur", so there is a kitchen to print for.
        kitchenEnabled: @json($categories->contains('send_to_kitchen', true)),
        // Only the tablet app can print to the kitchen printer unattended; a
        // browser would pop a print dialog after every sale.
        inApp: /POSKasirApp/.test(navigator.userAgent),
        activeCategory: null, search: '',

        get quickAmounts() {
            const g = this.grandTotal;
            const ceil = Math.ceil(g / 10000) * 10000;
            const set = new Set([ceil]);
            [50000,100000,200000,500000].forEach(v => { if(v >= g) set.add(v); });
            set.add(ceil + 10000); set.add(ceil + 20000);
            return [...set].filter(v => v >= g).sort((a,b)=>a-b).slice(0,5);
        },

        init() {
            // Lebar panel pesanan: pilihan terakhir perangkat ini, kalau tidak ada pakai bawaan.
            const mq = window.matchMedia('(min-width: 768px)');
            this.isDesktop = mq.matches;
            mq.addEventListener('change', e => { this.isDesktop = e.matches; });
            let savedW = 0;
            try { savedW = parseInt(localStorage.getItem('pos_cart_w')) || 0; } catch (e) {}
            this.cartW = this.clampCartW(savedW || this.defaultCartW());
            window.addEventListener('resize', () => { this.cartW = this.clampCartW(this.cartW); });
            // The device's own choice wins over the business default.
            try {
                const saved = localStorage.getItem('pos_print_via');
                if (['browser', 'rawbt', 'none'].includes(saved)) this.printVia = saved;
            } catch (e) {}
            this.tick();
            setInterval(() => this.tick(), 1000);
            this.fetchDrafts();
            this.initBarcodeListener();
            // Every way a sale can succeed (cash, manual QRIS, Midtrans) ends by
            // opening the success screen, so the kitchen ticket hangs off that.
            this.$watch('successModal', open => {
                if (!open) return;
                // Tablet app 2.7+: fetch the receipt now, so the Cetak tap only
                // has to write to the printer.
                if (this.printVia !== 'none' && this.lastReceiptUrl && window.PosApp?.prefetchReceipt) {
                    try { window.PosApp.prefetchReceipt(this.lastReceiptUrl); } catch (e) {}
                }
                if (this.kitchenEnabled && this.inApp) this.printKitchen();
            });
        },

        tick() { this.currentTime = new Date().toLocaleTimeString('id-ID'); },

        // ── Loyalty ──────────────────────────────────────────────────────────────
        async onCustomerChange() {
            this.redeemActive = false;
            this.redeemPoints = 0;
            this.customerPoints = null;
            if (!this.customerId) { this.calcTotals(); return; }

            try {
                const r = await fetch(`/pos/customer/${this.customerId}/points`);
                const d = await r.json();
                this.customerPoints = d.loyalty_points;
                this.pointValue     = d.point_value;
            } catch { this.customerPoints = 0; }
            this.calcTotals();
        },

        toggleRedeem() {
            this.redeemActive = !this.redeemActive;
            if (!this.redeemActive) { this.redeemPoints = 0; this.calcTotals(); }
        },

        // ── Barcode ────────────────────────────────────────────────────────────
        initBarcodeListener() {
            document.addEventListener('keydown', (e) => {
                const tag = document.activeElement?.tagName;
                const isBarcodeInput = document.activeElement === this.$refs.barcodeInput;
                if ((tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') && !isBarcodeInput) return;
                if (this.payModal || this.variantModal || this.noteModal) return;

                const now = Date.now();
                const timeDiff = now - this.barcodeLastKey;
                this.barcodeLastKey = now;

                if (timeDiff > 300) { if (!isBarcodeInput) this.barcodeBuffer = ''; }
                if (e.key === 'Enter') {
                    if (this.barcodeBuffer.length >= 3) { e.preventDefault(); this.scanBarcode(); }
                    return;
                }
                if (e.key.length === 1 && !e.ctrlKey && !e.altKey) {
                    if (!isBarcodeInput) {
                        this.barcodeBuffer += e.key;
                        clearTimeout(this.barcodeTimer);
                        this.barcodeTimer = setTimeout(() => { if (this.barcodeBuffer.length >= 3) this.scanBarcode(); }, 100);
                    }
                }
            });
        },

        scanBarcode() {
            const query = this.barcodeBuffer.trim();
            if (!query) return;
            const found = this.allProducts.find(p => p.sku && p.sku.toLowerCase() === query.toLowerCase())
                       || this.allProducts.find(p => p.name.toLowerCase().includes(query.toLowerCase()));
            if (found) {
                this.barcodeBuffer = ''; this.search = '';
                this.selectProduct(found);
                this.$nextTick(() => {
                    const el = this.$refs.barcodeInput;
                    if (el) { el.value = ''; el.classList.add('ring-emerald-500','border-emerald-400'); setTimeout(()=>el.classList.remove('ring-emerald-500','border-emerald-400'),500); }
                });
            } else {
                this.barcodeErrorMsg = `Produk dengan kode "${query}" tidak ditemukan.`;
                this.barcodeErrorModal = true;
                this.barcodeBuffer = '';
                setTimeout(() => this.barcodeErrorModal = false, 3000);
            }
        },

        fmt(n) { return Math.round(n || 0).toLocaleString('id-ID'); },
        // Ubin produk tanpa foto: inisial berwarna, warnanya tetap untuk nama yang sama.
        initials(name) {
            const w = String(name || '?').trim().split(/\s+/);
            return (w.length > 1 ? w[0][0] + w[1][0] : (w[0] || '?').slice(0, 2)).toUpperCase();
        },
        tile(name) {
            const pal = [['#dcfce7','#166534'],['#fef3c7','#92400e'],['#ffedd5','#9a3412'],['#e0f2fe','#075985'],
                         ['#fce7f3','#9d174d'],['#ede9fe','#5b21b6'],['#ecfccb','#3f6212'],['#f1f5f9','#334155']];
            let h = 0; for (const c of String(name || '')) h = (h * 31 + c.charCodeAt(0)) >>> 0;
            const [bg, fg] = pal[h % pal.length];
            return { bg, fg };
        },
        fmtQty(n) { return parseFloat(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 3 }); },
        draftTime(iso) {
            try { return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }); }
            catch { return ''; }
        },

        matchesFilter(catId, name) {
            const catOk  = this.activeCategory === null || this.activeCategory === catId;
            const srchOk = this.search === '' || name.toLowerCase().includes(this.search.toLowerCase());
            return catOk && srchOk;
        },

        // ── Bundle ─────────────────────────────────────────────────────────────
        addBundle(bundle) {
            const ex = this.cart.findIndex(i => i.isBundle && i.bundleId === bundle.id);
            if (ex >= 0) { this.cart[ex].qty += 1; this.calcTotals(); return; }
            this.cart.push({
                isBundle:    true,
                bundleId:    bundle.id,
                productId:   null,
                name:        bundle.name,
                bundleItems: bundle.items,
                unitPrice:   parseFloat(bundle.price),
                costPrice:   0,
                qty:         1,
                selectedAddons: [],
                notes:       '',
                lineTotal()  { return this.unitPrice * this.qty; }
            });
            this.calcTotals();
        },

        // ── Products ───────────────────────────────────────────────────────────
        selectProduct(prod) {
            if (prod.variants?.length > 0 || prod.addons?.length > 0) {
                this.selProd    = prod;
                this.selVariant = prod.variants?.length === 1 ? prod.variants[0] : null;
                this.selAddons  = [];
                this.mQty       = 1;
                this.variantModal = true;
            } else {
                this.pushCart(prod, null, [], 1);
            }
        },

        confirmAdd() {
            if (this.selProd.variants?.length > 0 && !this.selVariant) return;
            // A checkbox hands x-model its value as a string ("5") while the
            // add-on ids are numbers (5); compare as strings or every topping
            // silently drops out of the cart.
            const picked = this.selAddons.map(String);
            const addons = this.selProd.addons?.filter(a => picked.includes(String(a.id))) || [];
            this.pushCart(this.selProd, this.selVariant, addons, this.mQty);
            this.variantModal = false;
        },

        pushCart(prod, variant, addons, qty) {
            const price = variant
                ? parseFloat(prod.price) + parseFloat(variant.price_adjustment)
                : parseFloat(prod.price);

            if (!addons.length) {
                const ex = this.cart.findIndex(i => !i.isBundle && i.productId===prod.id && i.variantId===(variant?.id||null) && !i.selectedAddons.length);
                if (ex >= 0) { this.cart[ex].qty += qty; this.calcTotals(); return; }
            }

            this.cart.push({
                isBundle:      false,
                productId:     prod.id,
                name:          prod.name + (variant ? ' — '+variant.name : ''),
                variantId:     variant?.id || null,
                variantName:   variant?.name || null,
                unitPrice:     price,
                costPrice:     parseFloat(prod.cost_price) || 0,
                qty,
                selectedAddons: addons,
                notes:         '',
                lineTotal()    {
                    const addonSum = this.selectedAddons.reduce((s,a) => s + parseFloat(a.price), 0);
                    return (this.unitPrice + addonSum) * this.qty;
                }
            });
            this.calcTotals();
        },

        changeQty(idx, d) { this.cart[idx].qty = Math.max(1, this.cart[idx].qty + d); this.calcTotals(); },
        removeItem(idx)   { this.cart.splice(idx, 1); this.calcTotals(); },
        // ── Lebar panel pesanan (bisa digeser) ─────────────────────
        defaultCartW() { return window.innerWidth >= 1280 ? 400 : 340; },
        clampCartW(w) {
            const max = Math.max(300, Math.min(720, window.innerWidth - 380)); // kolom produk minimal ~380px
            return Math.round(Math.max(300, Math.min(max, w)));
        },
        startResize(e) {
            this.resizing = true;
            try { e.currentTarget.setPointerCapture(e.pointerId); } catch (err) {}
            document.body.classList.add('select-none');
        },
        doResize(e) {
            if (!this.resizing) return;
            this.cartW = this.clampCartW(window.innerWidth - e.clientX);
        },
        endResize() {
            if (!this.resizing) return;
            this.resizing = false;
            document.body.classList.remove('select-none');
            try { localStorage.setItem('pos_cart_w', String(this.cartW)); } catch (e) {}
        },
        resetCartW() {
            this.cartW = this.clampCartW(this.defaultCartW());
            try { localStorage.removeItem('pos_cart_w'); } catch (e) {}
        },

        // Dialog konfirmasi sendiri (pengganti confirm() bawaan browser). Mengembalikan Promise<boolean>.
        ask(o) {
            return new Promise(resolve => {
                this.dlg = Object.assign({ open: true, title: 'Yakin?', message: '', ok: 'Ya', cancel: 'Batal', danger: true, resolve }, o);
            });
        },
        dlgAnswer(v) { const r = this.dlg.resolve; this.dlg.open = false; if (r) r(v); },
        async clearCart() {
            const ok = await this.ask({
                title: 'Kosongkan pesanan?',
                message: 'Semua item di keranjang akan dihapus.',
                ok: 'Ya, Kosongkan',
            });
            if (!ok) return;
            this.cart = []; this.redeemPoints = 0; this.mobileCart = false; this.calcTotals();
        },
        // Print without leaving the register: the print view calls window.print()
        // on load, so loading it into a hidden iframe opens the print dialog
        // (or prints straight away under --kiosk-printing) right here.
        // RawBT needs a top-level navigation to its rawbt: link, so that mode
        // still opens the print page.
        // The kitchen ticket: only the order's kitchen items. In the tablet app
        // the hidden frame is intercepted and printed on the kitchen printer
        // (nothing at all if the order has no kitchen items); in a browser it
        // opens the print dialog.
        printKitchen() {
            if (!this.lastReceiptUrl) return;
            document.getElementById('kitchen-print-frame')?.remove();
            const frame = document.createElement('iframe');
            frame.id = 'kitchen-print-frame';
            frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
            frame.src = this.lastReceiptUrl.replace(/\/$/, '') + '/kitchen';
            document.body.appendChild(frame);
        },
        printReceipt() {
            if (!this.lastReceiptUrl) return;
            if (this.printVia === 'none') return;
            const url = this.lastReceiptUrl.replace(/\/$/, '') + '/print?via=' + this.printVia;

            if (this.printVia === 'rawbt') {
                window.location.href = url;
                return;
            }

            this.printing = true;
            document.getElementById('receipt-print-frame')?.remove();
            const frame = document.createElement('iframe');
            frame.id = 'receipt-print-frame';
            frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
            frame.onload = () => setTimeout(() => { this.printing = false; }, 800);
            frame.src = url;
            document.body.appendChild(frame);
        },
        // Mirrors Promotion::buyGetDiscount on the server, which has the final say.
        buyGetDiscount(p) {
            const group = p.buy + p.get;
            const units = [];
            this.cart.filter(i => !i.isBundle).forEach(i => {
                const cat = this.allProducts.find(x => x.id == i.productId)?.cat_id ?? null;
                if (p.cat && cat != p.cat) return;
                for (let n = 0; n < Math.floor(i.qty); n++) units.push(i.unitPrice);
            });
            units.sort((a, b) => b - a);
            let free = 0;
            for (let s = 0; s + group <= units.length; s += group) {
                free += units.slice(s + p.buy, s + group).reduce((a, b) => a + b, 0);
            }
            return free;
        },
        savePrintVia() {
            try { localStorage.setItem('pos_print_via', this.printVia); } catch (e) {}
        },
        cartCount()       { return this.cart.reduce((n, i) => n + (Number(i.qty) || 0), 0); },

        openNote(idx) {
            this.noteIdx  = idx;
            this.noteText = this.cart[idx].notes || '';
            this.noteModal = true;
            this.$nextTick(() => this.$refs.noteInput?.focus());
        },
        saveNote() { if (this.noteIdx !== null) this.cart[this.noteIdx].notes = this.noteText; this.noteModal = false; },

        calcTotals() {
            this.subtotal = this.cart.reduce((s, i) => s + i.lineTotal(), 0);

            // Promo discount
            let promoDisc = 0;
            if (this.promoId) {
                const p = this.promotionData.find(x => x.id == this.promoId);
                if (p && this.subtotal >= p.min_order) {
                    promoDisc = p.type === 'percent' ? this.subtotal * p.value / 100
                              : p.type === 'buy_get' ? this.buyGetDiscount(p)
                              : p.value;
                }
            }
            this.promoDiscount = promoDisc;

            // Manual discount
            const manualRaw = parseFloat(this.manualDisc) || 0;
            this.manualDiscActual = Math.max(0, manualRaw - promoDisc);

            // Take the larger of promo vs manual (backend does the same)
            const baseDisc = Math.max(promoDisc, manualRaw);

            // Points discount
            const pts = parseInt(this.redeemPoints) || 0;
            this.pointsDiscountAmt = pts * this.pointValue;

            const totalDisc = Math.min(this.subtotal, baseDisc + this.pointsDiscountAmt);
            const base      = Math.max(0, this.subtotal - totalDisc);

            this.tax     = this.settings.enable_tax     ? Math.round(base * (this.settings.tax_percent     || 0) / 100) : 0;
            this.service = this.settings.enable_service  ? Math.round(base * (this.settings.service_percent || 0) / 100) : 0;
            const fee    = this.orderType === 'delivery' ? (parseFloat(this.deliveryFee) || 0) : 0;
            this.grandTotal = base + this.tax + this.service + fee;
            this.calcSplitChange();
            this.calcChange();
        },

        calcChange() { this.change = Math.max(0, this.paidAmt - this.grandTotal); },

        // ── Order type & Delivery ─────────────────────────────────────────────
        setOrderType(type) {
            this.orderType = type;
            if (type !== 'delivery') { this.deliveryFee = 0; }
            this.calcTotals();
        },

        // ── Split Billing ─────────────────────────────────────────────────────
        toggleSplit() {
            this.isSplit = !this.isSplit;
            if (this.isSplit) {
                this.splitPayments = [
                    { method: 'cash',  amount: Math.ceil(this.grandTotal / 2 / 1000) * 1000 },
                    { method: 'qris',  amount: 0 },
                ];
                this.calcSplitChange();
            }
        },

        addSplit() {
            this.splitPayments.push({ method: 'cash', amount: 0 });
        },

        removeSplit(idx) {
            this.splitPayments.splice(idx, 1);
            this.calcSplitChange();
        },

        calcSplitChange() {
            this.splitTotal = this.splitPayments.reduce((s, p) => s + (parseFloat(p.amount) || 0), 0);
        },

        openPayment() {
            if (!this.cart.length) return;
            this.calcTotals();
            this.paidAmt        = this.payMethod === 'cash' ? Math.ceil(this.grandTotal / 1000) * 1000 : this.grandTotal;
            this.showQrisScreen = false;
            this.isSplit        = false;
            this.splitPayments  = [{ method: 'cash', amount: 0 }];
            this.calcChange();
            this.payModal = true;
        },

        selectPayMethod(method) {
            this.payMethod = method;
            if (method !== 'cash') { this.paidAmt = this.grandTotal; this.calcChange(); }
        },

        async openQrisScreen() {
            if (!this.qrisIsSet) return;
            this.showQrisScreen = true;

            // Locally generated dynamic QRIS: no gateway, the QR just carries the
            // exact amount. The cashier confirms once the customer has paid.
            if (this.qrisUseDynamic) {
                this.qrisLoading = true;
                this.qrisSvg     = '';
                try {
                    const r = await fetch('/pos/qris/dynamic', {
                        method:  'POST',
                        headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                        body:    JSON.stringify({ amount: this.grandTotal }),
                    });
                    const data = await this._readJson(r);
                    if (!data.success) {
                        throw new Error(data.message
                            || (data.errors && Object.values(data.errors)[0][0])
                            || 'Gagal membuat QRIS');
                    }
                    this.qrisSvg = data.svg;
                } catch (e) {
                    alert('Gagal membuat QRIS: ' + e.message);
                    this.showQrisScreen = false;
                }
                this.qrisLoading = false;
                return;
            }

            // Dynamic Midtrans QRIS flow
            if (this.midtransEnabled) {
                this.qrisLoading    = true;
                this.midtransQrUrl  = null;
                this.midtransOrderId = null;
                this.qrisExpired    = false;

                try {
                    // Step 1: Create pending order (get order_id)
                    const cartBody = this._buildCartBody();
                    const draftRes = await fetch('/pos/qris-draft', {
                        method:  'POST',
                        headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                        body:    JSON.stringify(cartBody),
                    });
                    const draft = await this._readJson(draftRes);
                    if (!draft.success) throw new Error(draft.message || 'Gagal membuat order');

                    this.midtransOrderId = draft.order_id;
                    this.lastOrderNo     = draft.order_number;

                    // Step 2: Create Midtrans QRIS QR
                    const qrisRes = await fetch('/midtrans/qris/create', {
                        method:  'POST',
                        headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                        body:    JSON.stringify({ order_id: draft.order_id, grand_total: draft.grand_total }),
                    });
                    const qris = await this._readJson(qrisRes);
                    if (qris.error) throw new Error(qris.error);

                    this.midtransQrUrl = qris.qr_url;
                    this.qrisLoading   = false;

                    // Step 3: Auto-poll payment status every 3 seconds
                    this.midtransPolling = setInterval(async () => {
                        try {
                            const stRes = await fetch('/midtrans/qris/status', {
                                method:  'POST',
                                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                                body:    JSON.stringify({ order_id: this.midtransOrderId }),
                            });
                            const st = await stRes.json();
                            if (st.status === 'paid') {
                                clearInterval(this.midtransPolling);
                                this.midtransPolling = null;
                                this.showQrisScreen = false;
                                this.lastReceiptUrl = '{{ url("/receipt") }}/' + this.midtransOrderId;
                                this.successModal   = true;
                                this.fetchDrafts();
                            }
                        } catch(e) { /* silent */ }
                    }, 3000);

                } catch(e) {
                    this.qrisLoading = false;
                    alert('Gagal membuat QRIS: ' + e.message);
                    this.showQrisScreen = false;
                }
            }
            // Static QRIS: just show the screen (no API call needed)
        },

        cancelQrisScreen() {
            // Stop polling if active
            if (this.midtransPolling) {
                clearInterval(this.midtransPolling);
                this.midtransPolling = null;
            }
            this.showQrisScreen  = false;
            this.qrisSvg         = '';
            this.midtransQrUrl   = null;
            this.midtransOrderId = null;
            this.qrisLoading     = false;
        },

        async confirmQrisPayment() {
            this.paidAmt = this.grandTotal;

            // Midtrans flow: confirm pending order manually
            if (this.midtransEnabled && this.midtransOrderId) {
                if (this.midtransPolling) {
                    clearInterval(this.midtransPolling);
                    this.midtransPolling = null;
                }
                this.processing = true;
                try {
                    const r = await fetch('/pos/qris-confirm/' + this.midtransOrderId, {
                        method:  'POST',
                        headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                        body:    JSON.stringify({}),
                    });
                    const data = await this._readJson(r);
                    if (data.success) {
                        this.lastOrderNo    = data.order_number;
                        this.lastReceiptUrl = data.receipt_url;
                        this.lastChange     = 0;
                        this.showQrisScreen = false;
                        this.successModal   = true;
                        this.fetchDrafts();
                    } else {
                        alert('Error: ' + (data.message || JSON.stringify(data)));
                    }
                } catch(e) { alert('Koneksi error: ' + e.message); }
                this.processing = false;
                return;
            }

            // Static QRIS flow: use normal processPayment
            await this.processPayment();
        },

        // Server selalu balas JSON di endpoint POS. Kalau ternyata bukan, itu
        // pertanda sesi habis, route hilang, atau error server — tampilkan
        // sesuatu yang bisa ditindaklanjuti, bukan galat parsing JSON.
        async _readJson(response) {
            const text = await response.text();

            try {
                return JSON.parse(text);
            } catch (e) {
                if (response.status === 419) {
                    throw new Error('Sesi kasir habis. Muat ulang halaman lalu masuk lagi.');
                }
                if (response.status === 401 || response.status === 403) {
                    throw new Error('Sesi kasir berakhir. Masuk kembali dengan PIN.');
                }
                throw new Error('Server membalas tidak semestinya (HTTP ' + response.status + '). Cek koneksi, lalu coba lagi.');
            }
        },

        _buildCartBody() {
            const baseItems = this.cart.map(i => {
                if (i.isBundle) return { bundle_id: i.bundleId, price: i.unitPrice, qty: i.qty };
                return { product_id: i.productId, variant_id: i.variantId, variant_name: i.variantName,
                         price: i.unitPrice, qty: i.qty, notes: i.notes,
                         addons: i.selectedAddons.map(a => ({addon_id: a.id, name: a.name, price: a.price})) };
            });
            return {
                items:           baseItems,
                promotion_id:    this.promoId || null,
                manual_discount: parseFloat(this.manualDisc) || 0,
                customer_id:     this.customerId || null,
                draft_id:        this.resumedDraftId,
                table_number:    this.tableNumber || null,
                hold_name:       this.holdName || null,
                redeem_points:   (this.redeemActive && this.customerId) ? (parseInt(this.redeemPoints) || 0) : 0,
                order_type:      this.orderType,
                ...(this.orderType === 'delivery' && {
                    delivery_platform:     this.deliveryPlatform || null,
                    delivery_fee:          parseFloat(this.deliveryFee) || 0,
                    customer_address:      this.deliveryAddress || null,
                    platform_order_number: this.platformOrderNo || null,
                }),
            };
        },

        async processPayment() {
            if (this.processing) return;
            if (this.payMethod === 'cash' && this.paidAmt < this.grandTotal) return;
            this.processing = true;

            const body = {
                ...this._buildCartBody(),
                ...(this.isSplit ? {
                    split_payments: this.splitPayments.map(p => ({ method: p.method, amount: parseFloat(p.amount) || 0 })),
                } : {
                    payment_method: this.payMethod,
                    paid_amount:    this.paidAmt,
                }),
            };

            try {
                const r = await fetch('/pos/order', {
                    method:  'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                    body:    JSON.stringify(body),
                });
                const data = await this._readJson(r);
                if (data.success) {
                    this.lastOrderNo         = data.order_number;
                    this.lastReceiptUrl      = data.receipt_url;
                    // Server-side total wins: the cart preview can be stale if a
                    // price changed after this page was loaded.
                    this.lastChange          = data.change ?? this.change;
                    // Server decides how many points were actually spent — it caps
                    // them at what the bill could absorb.
                    this.lastPointsRedeemed  = data.points_redeemed ?? 0;
                    this.payModal       = false;
                    this.showQrisScreen = false;
                    this.successModal   = true;
                    this.fetchDrafts();
                } else {
                    alert('Error: ' + (data.message || JSON.stringify(data)));
                }
            } catch(e) { alert('Koneksi error: ' + e.message); }
            this.processing = false;
        },

        newTransaction() {
            this.cart = []; this.mobileCart = false; this.customerId = ''; this.promoId = '';
            this.resumedDraftId = null; this.holdName = ''; this.tableNumber = '';
            this.manualDisc = 0; this.payMethod = 'cash'; this.paidAmt = 0;
            this.redeemActive = false; this.redeemPoints = 0;
            this.customerPoints = null;
            this.orderType = this.defaultOrderType; this.deliveryPlatform = '';
            this.deliveryFee = 0; this.deliveryAddress = ''; this.platformOrderNo = '';
            this.isSplit = false; this.splitPayments = [{ method: 'cash', amount: 0 }];
            this.successModal = false; this.showQrisScreen = false;
            // Reset Midtrans QRIS state
            if (this.midtransPolling) { clearInterval(this.midtransPolling); this.midtransPolling = null; }
            this.midtransQrUrl = null; this.midtransOrderId = null;
            this.qrisLoading = false; this.qrisExpired = false;
            this.calcTotals();
        },

        openHold() {
            if (!this.cart.length) return;
            if (this.cart.some(i => i.isBundle)) {
                alert('Paket (bundle) belum bisa di-hold. Hapus paket dari keranjang dulu, atau langsung bayar.');
                return;
            }
            // Re-holding a resumed order keeps its name and table.
            if (!this.holdName && this.customerId) {
                const opt = document.querySelector('select[x-model="customerId"] option[value="' + this.customerId + '"]');
                this.holdName = opt ? opt.textContent.replace(/\s*\(.*\)\s*$/, '').trim() : '';
            }
            this.holdError = '';
            this.holdModal = true;
            this.$nextTick(() => this.$refs.holdNameInput?.focus());
        },

        async holdOrder() {
            if (!this.cart.length || this.holding) return;
            if (!this.holdName.trim()) { this.holdError = 'Isi nama pemesan dulu.'; return; }

            this.holding = true;
            this.holdError = '';
            try {
                const r = await fetch('/pos/hold', {
                    method:  'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                    body: JSON.stringify({
                        // Variant, add-ons and notes travel with the item so the
                        // order comes back exactly as it was rung up.
                        items: this.cart.map(i => ({
                            product_id: i.productId, variant_id: i.variantId, qty: i.qty, notes: i.notes || null,
                            addons: i.selectedAddons.map(a => ({ addon_id: a.id })),
                        })),
                        hold_name:    this.holdName.trim(),
                        table_number: this.tableNumber.trim() || null,
                        customer_id:  this.customerId || null,
                        draft_id:     this.resumedDraftId,
                    }),
                });
                const data = await this._readJson(r);
                if (!data.success) {
                    this.holdError = data.message || 'Gagal menyimpan hold.';
                    return;
                }
                this.holdModal = false;
                this.newTransaction();
                await this.fetchDrafts();
            } catch (e) {
                this.holdError = e.message || 'Gagal menyimpan hold. Cek koneksi lalu coba lagi.';
            } finally {
                this.holding = false;
            }
        },

        async deleteDraft(d) {
            const label = d.hold_name || d.order_number;
            const ok = await this.ask({
                title: 'Hapus order hold?',
                message: '"' + label + '" akan dihapus dan tidak bisa dikembalikan.',
                ok: 'Ya, Hapus',
            });
            if (!ok) return;
            try {
                const r = await fetch('/pos/drafts/' + d.id, {
                    method:  'DELETE',
                    headers: {'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                });
                const data = await this._readJson(r);
                if (!data.success) { alert(data.message || 'Gagal menghapus.'); return; }
                if (this.resumedDraftId === d.id) this.resumedDraftId = null;
                await this.fetchDrafts();
                if (!this.drafts.length) this.draftModal = false;
            } catch (e) { alert(e.message || 'Gagal menghapus. Cek koneksi lalu coba lagi.'); }
        },

        async fetchDrafts() {
            try {
                const r = await fetch('/pos/drafts', { headers: { 'Accept': 'application/json' } });
                // An expired session answers with the login page; keep the old
                // list rather than replacing it with garbage.
                if (!r.ok) return;
                const list = await r.json();
                if (!Array.isArray(list)) return;
                this.drafts = list;
                this.draftCount = list.length;
            } catch {}
        },

        async loadDrafts() { await this.fetchDrafts(); this.draftModal = true; },

        async resumeDraft(id) {
            let r;
            try {
                r = await fetch('/pos/drafts/'+id+'/load', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                    body: '{}',
                });
            } catch {
                alert('Gagal memuat order hold: koneksi internet terputus. Cek WiFi lalu coba lagi.');
                return;
            }

            // Say why, so the cashier knows what to do next.
            if (!r.ok) {
                if (r.status === 401 || r.status === 419) {
                    alert('Sesi login sudah habis. Halaman akan dimuat ulang — order hold tetap aman tersimpan.');
                    location.reload();
                } else if (r.status === 404 || r.status === 422) {
                    alert('Order hold ini sudah tidak ada — mungkin sudah dibayar, dihapus, atau di-hold ulang dari perangkat lain.');
                    await this.fetchDrafts();
                    if (!this.drafts.length) this.draftModal = false;
                } else if (r.status === 403) {
                    alert('Order hold ini bukan milik toko Anda.');
                } else {
                    alert('Gagal memuat order hold: server error (kode ' + r.status + '). Coba lagi sebentar lagi.');
                }
                return;
            }

            try {
                const order = await r.json();
                this.newTransaction();
                this.resumedDraftId = order.id;
                this.holdName       = order.hold_name || '';
                this.tableNumber    = order.table_number || '';
                if (order.customer_id) { this.customerId = String(order.customer_id); this.onCustomerChange?.(); }
                (order.items || []).forEach(item => {
                    this.cart.push({
                        isBundle:      false,
                        productId:     item.product_id,
                        name:          item.product_name + (item.variant_name ? ' — ' + item.variant_name : ''),
                        variantId:     item.product_variant_id,
                        variantName:   item.variant_name,
                        unitPrice:     parseFloat(item.price),
                        costPrice:     parseFloat(item.cost_price),
                        qty:           parseFloat(item.qty),
                        selectedAddons:(item.addons || []).map(a => ({ id: a.product_addon_id, name: a.addon_name, price: parseFloat(a.price) })),
                        notes:         item.notes || '',
                        lineTotal()    {
                            const addonSum = this.selectedAddons.reduce((s,a) => s + parseFloat(a.price), 0);
                            return (this.unitPrice + addonSum) * this.qty;
                        }
                    });
                });
                this.calcTotals();
                this.draftModal = false;
            } catch {
                alert('Gagal memuat order hold: data dari server tidak terbaca. Muat ulang halaman lalu coba lagi.');
            }
        },
    };
}
</script>
</body>
</html>
