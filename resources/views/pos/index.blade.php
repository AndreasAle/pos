<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kasir POS — {{ $business->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-100 font-sans" x-data="posApp()" x-init="init()">

{{-- Top Bar --}}
<header class="bg-white border-b border-gray-200 px-4 py-2.5 flex items-center gap-4">
    <a href="{{ route('dashboard') }}" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
    </a>
    <div class="w-px h-5 bg-gray-200"></div>
    <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-lg bg-emerald-600 flex items-center justify-center">
            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4"/>
            </svg>
        </div>
        <span class="font-bold text-gray-900 text-sm">Kasir POS</span>
    </div>

    @if($activeShift)
    <div class="flex items-center gap-1.5 text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full px-3 py-1">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
        Shift Aktif &middot; {{ $activeShift->outlet->name }}
    </div>
    @endif

    <div class="ml-auto flex items-center gap-3">
        <button @click="loadDrafts()" x-show="draftCount > 0"
                class="relative text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg transition-colors">
            Draft
            <span x-text="draftCount" class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center"></span>
        </button>
        <span class="text-sm text-gray-500 font-mono" x-text="currentTime"></span>
        <span class="text-sm font-medium text-gray-700">{{ auth()->user()->name }}</span>
        @if($activeShift)
        <a href="{{ route('shifts.close', $activeShift) }}"
           class="text-xs font-medium text-red-600 border border-red-200 hover:bg-red-50 px-3 py-1.5 rounded-lg transition-colors">
            Tutup Shift
        </a>
        @endif
    </div>
</header>

<div class="flex h-[calc(100vh-49px)]">

    {{-- LEFT: Products --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        {{-- Filter Bar --}}
        <div class="bg-white border-b border-gray-200 px-4 py-2.5 flex items-center gap-3">
            {{-- Barcode Scanner Input --}}
            <div class="relative shrink-0" style="width:220px">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
                <input type="text" x-ref="barcodeInput"
                       x-model="barcodeBuffer"
                       @keydown.enter.prevent="scanBarcode()"
                       placeholder="Scan barcode / Cari SKU..."
                       class="pl-9 pr-4 py-2 w-full border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50 font-mono"
                       autocomplete="off">
                <div x-show="barcodeBuffer.length > 0"
                     class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                    <span class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">Enter</span>
                    <button @click="barcodeBuffer=''; search=''" class="text-gray-300 hover:text-gray-500">✕</button>
                </div>
            </div>
            {{-- Text search --}}
            <div class="relative" style="width:180px">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" x-model="search" placeholder="Cari nama produk..."
                       class="pl-9 pr-4 py-2 w-full border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-gray-50">
            </div>
            <div class="flex gap-2 overflow-x-auto flex-1 pb-0.5">
                <button @click="activeCategory = null; showBundles = false"
                        :class="activeCategory === null && !showBundles ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-200'"
                        class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-full border transition-colors">
                    Semua
                </button>
                {{-- Bundle tab --}}
                @if(count($bundlesJson) > 0)
                <button @click="showBundles = true; activeCategory = null"
                        :class="showBundles ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200'"
                        class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-full border transition-colors">
                    📦 Bundle
                </button>
                @endif
                @foreach($categories as $cat)
                <button @click="activeCategory = {{ $cat->id }}; showBundles = false"
                        :style="activeCategory === {{ $cat->id }} && !showBundles ? 'background:{{ $cat->color ?? '#10b981' }};border-color:{{ $cat->color ?? '#10b981' }};color:#fff' : ''"
                        :class="(activeCategory !== {{ $cat->id }} || showBundles) ? 'bg-white text-gray-600 border-gray-200 hover:border-gray-400' : ''"
                        class="shrink-0 text-xs font-semibold px-3 py-1.5 rounded-full border transition-colors">
                    {{ $cat->name }}
                </button>
                @endforeach
            </div>
        </div>

        {{-- Product / Bundle Grid --}}
        <div class="flex-1 overflow-y-auto p-3">

            {{-- Bundle Grid --}}
            <div x-show="showBundles"
                 class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3">
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
                 class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3">
                <template x-for="product in allProducts" :key="product.id">
                <button type="button"
                        x-show="matchesFilter(product.cat_id, product.name)"
                        @click="selectProduct(product)"
                        class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden text-left hover:shadow-md hover:border-emerald-300 transition-all active:scale-95 select-none cursor-pointer">
                    <div class="aspect-square bg-gray-50 overflow-hidden">
                        <template x-if="product.image">
                            <img :src="product.image" :alt="product.name" class="w-full h-full object-cover pointer-events-none">
                        </template>
                        <template x-if="!product.image">
                        <div class="w-full h-full flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                            </svg>
                        </div>
                        </template>
                    </div>
                    <div class="p-2.5">
                        <p class="text-xs font-semibold text-gray-900 leading-tight line-clamp-2" x-text="product.name"></p>
                        <p class="text-sm font-bold text-emerald-700 mt-1" x-text="'Rp ' + fmt(product.price)"></p>
                    </div>
                </button>
                </template>
            </div>
        </div>
    </div>

    {{-- RIGHT: Cart --}}
    <div class="w-80 xl:w-96 shrink-0 bg-white border-l border-gray-200 flex flex-col shadow-lg">

        {{-- Order Type Selector --}}
        <div class="px-4 pt-3 pb-2 border-b border-gray-100">
            <div class="grid grid-cols-3 gap-1.5">
                <button @click="setOrderType('dine_in')"
                        :class="orderType==='dine_in' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-200'"
                        class="text-xs font-semibold py-1.5 rounded-xl border transition-colors">
                    🍽 Dine In
                </button>
                <button @click="setOrderType('takeaway')"
                        :class="orderType==='takeaway' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200'"
                        class="text-xs font-semibold py-1.5 rounded-xl border transition-colors">
                    🛍 Takeaway
                </button>
                <button @click="setOrderType('delivery')"
                        :class="orderType==='delivery' ? 'bg-orange-500 text-white border-orange-500' : 'bg-white text-gray-600 border-gray-200'"
                        class="text-xs font-semibold py-1.5 rounded-xl border transition-colors">
                    🛵 Delivery
                </button>
            </div>

            {{-- Delivery fields --}}
            <div x-show="orderType === 'delivery'" class="mt-2 space-y-1.5">
                <select x-model="deliveryPlatform"
                        class="w-full text-xs border border-gray-200 rounded-xl px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-orange-400 bg-gray-50">
                    <option value="">-- Platform Delivery --</option>
                    <option value="gofood">GoFood</option>
                    <option value="grabfood">GrabFood</option>
                    <option value="shopeefood">ShopeeFood</option>
                    <option value="manual">Manual / Kurir Sendiri</option>
                </select>
                <input type="text" x-model="platformOrderNo" placeholder="No. Order Platform (opsional)"
                       class="w-full text-xs border border-gray-200 rounded-xl px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-orange-400 bg-gray-50">
                <div class="relative">
                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                    <input type="number" x-model.number="deliveryFee" min="0" step="1000" @change="calcTotals()"
                           placeholder="Ongkos kirim"
                           class="pl-6 w-full text-xs border border-orange-200 rounded-xl px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-orange-400 bg-orange-50">
                </div>
                <textarea x-model="deliveryAddress" rows="2" placeholder="Alamat pengiriman..."
                          class="w-full text-xs border border-gray-200 rounded-xl px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-orange-400 bg-gray-50 resize-none"></textarea>
            </div>
        </div>

        {{-- Customer --}}
        <div class="px-4 pt-3 pb-2 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <select x-model="customerId" @change="onCustomerChange()"
                        class="flex-1 text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-gray-50">
                    <option value="">-- Pelanggan (opsional) --</option>
                    @foreach(\App\Models\Customer::forBusiness(auth()->user()->business_id)->where('is_active',true)->orderBy('name')->limit(100)->get() as $cust)
                    <option value="{{ $cust->id }}">{{ $cust->name }}{{ $cust->phone ? ' ('.$cust->phone.')' : '' }}</option>
                    @endforeach
                </select>
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
        <div class="flex-1 overflow-y-auto px-3 py-2 space-y-2">
            <template x-if="cart.length === 0">
                <div class="flex flex-col items-center justify-center h-48 text-gray-300">
                    <svg class="w-14 h-14 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <p class="text-sm">Pilih menu untuk mulai</p>
                </div>
            </template>

            <template x-for="(item, idx) in cart" :key="idx">
                <div class="bg-gray-50 rounded-xl p-2.5 border border-gray-100"
                     :class="item.isBundle ? 'border-purple-200 bg-purple-50' : ''">
                    <div class="flex items-start gap-1.5">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <template x-if="item.isBundle">
                                    <span class="text-xs bg-purple-100 text-purple-600 font-bold px-1.5 py-0.5 rounded shrink-0">BUNDLE</span>
                                </template>
                                <p class="text-sm font-semibold text-gray-900 leading-tight" x-text="item.name"></p>
                            </div>
                            <template x-if="item.isBundle && item.bundleItems">
                                <p class="text-xs text-purple-500 mt-0.5"
                                   x-text="item.bundleItems.map(i => i.qty+'× '+i.product_name).join(', ')"></p>
                            </template>
                            <template x-for="addon in item.selectedAddons">
                                <p class="text-xs text-emerald-600" x-text="'+ ' + addon.name + ' (Rp ' + fmt(addon.price) + ')'"></p>
                            </template>
                            <p x-show="item.notes" class="text-xs text-orange-500 italic mt-0.5" x-text="'📝 ' + item.notes"></p>
                        </div>
                        <button @click="removeItem(idx)" class="text-gray-300 hover:text-red-500 p-0.5 shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <div class="flex items-center gap-1">
                            <button @click="changeQty(idx,-1)" class="w-6 h-6 rounded-lg bg-white border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-red-50 hover:border-red-300 transition-colors">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4"/></svg>
                            </button>
                            <span class="w-8 text-center text-sm font-bold" x-text="item.qty"></span>
                            <button @click="changeQty(idx,1)" class="w-6 h-6 rounded-lg bg-white border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-emerald-50 hover:border-emerald-300 transition-colors">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <template x-if="!item.isBundle">
                                <button @click="openNote(idx)" class="text-xs text-gray-400 hover:text-orange-500">📝</button>
                            </template>
                            <p class="text-sm font-bold text-gray-900" x-text="'Rp ' + fmt(item.lineTotal())"></p>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Summary + Controls --}}
        <div class="border-t border-gray-200 px-4 pt-3 pb-4 space-y-2.5">
            {{-- Promo --}}
            <div class="grid grid-cols-2 gap-2">
                <select x-model="promoId" @change="calcTotals()" class="col-span-2 text-xs border border-gray-200 rounded-xl px-2.5 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-gray-50">
                    <option value="">-- Pilih Promo --</option>
                    @foreach($promotions as $promo)
                    <option value="{{ $promo->id }}" data-type="{{ $promo->type }}" data-value="{{ $promo->value }}" data-min="{{ $promo->min_order }}">
                        {{ $promo->name }} — {{ $promo->type === 'percent' ? $promo->value.'%' : 'Rp '.number_format($promo->value,0,',','.') }}
                    </option>
                    @endforeach
                </select>
                <div class="relative">
                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                    <input type="number" x-model.number="manualDisc" min="0" step="1000" @change="calcTotals()"
                           placeholder="Diskon manual"
                           class="pl-6 w-full text-xs border border-gray-200 rounded-xl px-2.5 py-2 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
                <button @click="clearCart()" class="text-xs font-medium text-red-500 border border-red-200 hover:bg-red-50 rounded-xl py-2 transition-colors">
                    🗑 Kosongkan
                </button>
            </div>

            {{-- Totals --}}
            <div class="space-y-1 text-sm bg-gray-50 rounded-xl p-3">
                <div class="flex justify-between text-gray-500">
                    <span>Subtotal</span><span x-text="'Rp '+fmt(subtotal)"></span>
                </div>
                <div x-show="promoDiscount > 0" class="flex justify-between text-red-500">
                    <span>Promo</span><span x-text="'- Rp '+fmt(promoDiscount)"></span>
                </div>
                <div x-show="manualDiscActual > 0" class="flex justify-between text-red-500">
                    <span>Diskon Manual</span><span x-text="'- Rp '+fmt(manualDiscActual)"></span>
                </div>
                <div x-show="pointsDiscountAmt > 0" class="flex justify-between text-amber-600">
                    <span>Tukar Poin (<span x-text="redeemPoints"></span> pts)</span>
                    <span x-text="'- Rp '+fmt(pointsDiscountAmt)"></span>
                </div>
                @if(($settings['enable_tax'] ?? false))
                <div class="flex justify-between text-gray-500">
                    <span>Pajak ({{ $settings['tax_percent'] ?? 10 }}%)</span><span x-text="'Rp '+fmt(tax)"></span>
                </div>
                @endif
                @if(($settings['enable_service'] ?? false))
                <div class="flex justify-between text-gray-500">
                    <span>Service</span><span x-text="'Rp '+fmt(service)"></span>
                </div>
                @endif
                <div x-show="deliveryFee > 0" class="flex justify-between text-orange-600">
                    <span>Ongkos Kirim</span><span x-text="'Rp '+fmt(deliveryFee)"></span>
                </div>
                <div class="flex justify-between font-bold text-gray-900 text-base border-t border-gray-200 pt-1 mt-1">
                    <span>TOTAL</span><span x-text="'Rp '+fmt(grandTotal)"></span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <button @click="holdOrder()" :disabled="cart.length===0"
                        class="text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 border border-gray-200 py-3 rounded-xl transition-colors disabled:opacity-40">
                    Hold Order
                </button>
                <button @click="openPayment()" :disabled="cart.length===0"
                        class="text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 py-3 rounded-xl transition-colors disabled:opacity-40 shadow-sm">
                    💳 Bayar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════ MODALS ══════════════════════════════════ --}}

{{-- Variant/Addon Modal --}}
<div x-show="variantModal" x-cloak @click.self="variantModal=false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-5" @click.stop>
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
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-5" @click.stop>
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
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md" @click.stop>

        {{-- STEP 1: Pilih Metode Pembayaran --}}
        <div x-show="!showQrisScreen">
            <div class="p-5">
                <h3 class="font-bold text-gray-900 text-lg mb-4">Konfirmasi Pembayaran</h3>

                {{-- Total --}}
                <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 text-center mb-4">
                    <p class="text-xs text-emerald-600 font-medium uppercase tracking-wide mb-1">Total Tagihan</p>
                    <p class="text-4xl font-bold text-emerald-800" x-text="'Rp '+fmt(grandTotal)"></p>
                    <div x-show="pointsDiscountAmt > 0" class="mt-1 text-xs text-amber-600 font-medium">
                        Termasuk potongan poin Rp <span x-text="fmt(pointsDiscountAmt)"></span>
                    </div>
                </div>

                {{-- Metode --}}
                <div class="mb-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Metode Pembayaran</p>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach([['cash','💵','Cash'],['qris','📷','QRIS'],['transfer','🏦','Transfer'],['ewallet','💳','E-Wallet'],['debit','💳','Debit'],['other','💰','Lainnya']] as [$v,$icon,$label])
                        <button @click="selectPayMethod('{{ $v }}')"
                                :class="payMethod==='{{ $v }}'
                                    ? 'border-emerald-600 shadow-md {{ $v === 'qris' ? 'bg-gradient-to-b from-emerald-500 to-emerald-700' : 'bg-emerald-600' }} text-white'
                                    : 'bg-white text-gray-700 border-gray-200 hover:border-emerald-400'"
                                class="flex flex-col items-center gap-1 py-3 rounded-xl border-2 text-xs font-semibold transition-all">
                            <span class="text-2xl">{{ $icon }}</span>
                            <span>{{ $label }}</span>
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Cash input --}}
                <div x-show="payMethod==='cash'" class="mb-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Uang Diterima</p>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-medium">Rp</span>
                        <input type="number" x-model.number="paidAmt" min="0" step="1000" @input="calcChange()"
                               class="pl-10 w-full py-3 border-2 border-gray-300 rounded-xl text-2xl font-bold focus:outline-none focus:border-emerald-500 text-center">
                    </div>
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <template x-for="amt in quickAmounts" :key="amt">
                            <button @click="paidAmt=amt; calcChange()"
                                    :class="paidAmt===amt ? 'bg-emerald-100 border-emerald-400 text-emerald-800' : 'bg-white border-gray-200 text-gray-600'"
                                    class="text-xs font-medium px-2.5 py-1.5 rounded-xl border transition-colors"
                                    x-text="'Rp '+fmt(amt)">
                            </button>
                        </template>
                        <button @click="paidAmt=grandTotal; calcChange()"
                                class="text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1.5 rounded-xl">
                            Uang Pas
                        </button>
                    </div>
                    <div x-show="paidAmt >= grandTotal" class="mt-3 flex justify-between items-center bg-blue-50 border border-blue-200 rounded-xl p-3">
                        <span class="text-sm text-blue-700 font-semibold">Kembalian</span>
                        <span class="text-2xl font-bold text-blue-800" x-text="'Rp '+fmt(change)"></span>
                    </div>
                    <div x-show="paidAmt > 0 && paidAmt < grandTotal" class="mt-3 bg-red-50 border border-red-200 rounded-xl p-3 text-sm text-red-600 font-medium">
                        ⚠ Kurang Rp <span x-text="fmt(grandTotal - paidAmt)"></span>
                    </div>
                </div>

                {{-- Non-cash info --}}
                <div x-show="payMethod !== 'cash' && payMethod !== 'qris'" class="mb-4 bg-gray-50 rounded-xl p-3 text-sm text-gray-500 text-center">
                    Pembayaran akan dikonfirmasi manual oleh kasir
                </div>

                {{-- Split Billing Toggle --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Split Billing</p>
                        <button @click="toggleSplit()"
                                :class="isSplit ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600'"
                                class="text-xs font-semibold px-3 py-1 rounded-lg transition-colors">
                            <span x-text="isSplit ? '✓ Aktif' : 'Bayar Terpisah'"></span>
                        </button>
                    </div>

                    {{-- Split payment rows --}}
                    <div x-show="isSplit" class="mt-2 space-y-2">
                        <template x-for="(sp, si) in splitPayments" :key="si">
                            <div class="flex items-center gap-1.5 bg-purple-50 rounded-xl p-2 border border-purple-200">
                                <select x-model="sp.method" class="text-xs border border-purple-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none w-28 shrink-0">
                                    <option value="cash">💵 Cash</option>
                                    <option value="qris">📷 QRIS</option>
                                    <option value="transfer">🏦 Transfer</option>
                                    <option value="ewallet">💳 E-Wallet</option>
                                    <option value="debit">💳 Debit</option>
                                    <option value="other">💰 Lain</option>
                                </select>
                                <div class="relative flex-1">
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                                    <input type="number" x-model.number="sp.amount" min="0" step="1000"
                                           @input="calcSplitChange()"
                                           class="pl-6 w-full text-xs border border-purple-200 rounded-lg py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400 bg-white">
                                </div>
                                <button @click="removeSplit(si)" x-show="splitPayments.length > 1"
                                        class="text-red-400 hover:text-red-600 shrink-0 p-0.5">✕</button>
                            </div>
                        </template>
                        <button @click="addSplit()"
                                class="w-full text-xs font-medium text-purple-700 border border-dashed border-purple-300 py-2 rounded-xl hover:bg-purple-50 transition-colors">
                            + Tambah Cara Bayar
                        </button>
                        <div class="flex justify-between text-xs font-semibold px-1"
                             :class="splitTotal < grandTotal ? 'text-red-600' : (splitTotal > grandTotal ? 'text-blue-700' : 'text-emerald-700')">
                            <span>Total Split:</span>
                            <span x-text="'Rp '+fmt(splitTotal)+ (splitTotal < grandTotal ? ' (kurang Rp '+fmt(grandTotal-splitTotal)+')' : (splitTotal > grandTotal ? ' (lebih Rp '+fmt(splitTotal-grandTotal)+')' : ' ✓'))"></span>
                        </div>
                    </div>
                </div>

                {{-- QRIS hint --}}
                <div x-show="payMethod === 'qris' && !isSplit" class="mb-4">
                    @if($midtransEnabled)
                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex items-center gap-3">
                        <span class="text-2xl">⚡</span>
                        <div>
                            <p class="text-sm font-semibold text-emerald-800">QRIS Dinamis (Midtrans)</p>
                            <p class="text-xs text-emerald-600">QR unik akan dibuat otomatis per transaksi</p>
                        </div>
                    </div>
                    @elseif($qrisData['is_set'])
                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex items-center gap-3">
                        <span class="text-2xl">📷</span>
                        <div>
                            <p class="text-sm font-semibold text-emerald-800">QR Code siap ditampilkan</p>
                            <p class="text-xs text-emerald-600">Klik "Tampilkan QR" untuk lanjut ke layar pembayaran QRIS</p>
                        </div>
                    </div>
                    @else
                    <div class="bg-orange-50 border border-orange-200 rounded-xl p-3 flex items-center gap-3">
                        <span class="text-2xl">⚠️</span>
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

                {{-- Action buttons --}}
                <div class="flex gap-3 pt-1">
                    <button @click="payModal=false"
                            class="flex-1 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 py-3 rounded-xl">
                        Batal
                    </button>
                    <button x-show="payMethod==='qris' && !isSplit"
                            @click="openQrisScreen()"
                            :disabled="!qrisIsSet"
                            class="flex-1 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 py-3 rounded-xl disabled:opacity-40 transition-colors">
                        📷 Tampilkan QR
                    </button>
                    <button x-show="payMethod!=='qris' || isSplit"
                            @click="processPayment()"
                            :disabled="processing||((!isSplit)&&payMethod==='cash'&&paidAmt<grandTotal)||(isSplit&&splitTotal<grandTotal)"
                            class="flex-1 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 py-3 rounded-xl disabled:opacity-40 shadow-sm transition-colors">
                        <span x-show="!processing">✓ Proses Bayar</span>
                        <span x-show="processing">Memproses...</span>
                    </button>
                </div>
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
                    {{-- Static QRIS image --}}
                    @if($qrisData['image'])
                    <div x-show="!midtransEnabled">
                        <img src="{{ $qrisData['image'] }}" alt="QR QRIS" class="w-52 h-52 object-contain">
                    </div>
                    @else
                    <div x-show="!midtransEnabled" class="w-52 h-52 bg-gray-50 flex items-center justify-center rounded-xl">
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
<div x-show="draftModal" x-cloak @click.self="draftModal=false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-bold text-gray-900">Order Hold / Draft</h3>
            <button @click="draftModal=false" class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100">✕</button>
        </div>
        <div class="p-4 space-y-2 max-h-72 overflow-y-auto">
            <template x-for="d in drafts" :key="d.id">
                <div class="flex items-center justify-between bg-gray-50 rounded-xl p-3 border border-gray-100">
                    <div>
                        <p class="text-sm font-bold text-gray-900" x-text="d.order_number"></p>
                        <p class="text-xs text-gray-500" x-text="(d.items?.length||0)+' item · Rp '+fmt(d.grand_total)"></p>
                    </div>
                    <button @click="resumeDraft(d.id)"
                            class="text-xs font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-3 py-2 rounded-xl transition-colors">
                        Lanjut →
                    </button>
                </div>
            </template>
            <template x-if="!drafts.length">
                <p class="text-sm text-gray-400 text-center py-8">Tidak ada order draft</p>
            </template>
        </div>
    </div>
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
                    ⭐ <span x-text="lastPointsRedeemed"></span> poin ditukar
                    = Rp <span x-text="fmt(lastPointsRedeemed * pointValue)"></span>
                </p>
            </div>

            {{-- Buttons --}}
            <div class="grid grid-cols-2 gap-2 pt-1">
                <a :href="lastReceiptUrl" target="_blank"
                   class="flex items-center justify-center gap-1.5 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 py-3 rounded-xl transition-colors text-center">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Cetak Struk
                </a>
                <button @click="newTransaction()"
                        class="flex items-center justify-center gap-1.5 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 py-3 rounded-xl transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Transaksi Baru
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
        promotionData: {!! json_encode($promotions->map(fn($p) => ['id'=>$p->id,'type'=>$p->type,'value'=>(float)$p->value,'min_order'=>(float)$p->min_order])->values()) !!},
        qrisIsSet:       {!! $qrisData['is_set'] ? 'true' : 'false' !!},
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
        customerId: '', promoId: '', manualDisc: 0,
        subtotal: 0, promoDiscount: 0, manualDiscActual: 0, pointsDiscountAmt: 0,
        tax: 0, service: 0, grandTotal: 0,
        paidAmt: 0, change: 0, payMethod: 'cash',
        processing: false, currentTime: '',
        draftCount: 0, drafts: [],
        showBundles: false,

        // Order type + Delivery
        orderType: 'dine_in',
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
        showQrisScreen: false,
        barcodeErrorModal: false, barcodeErrorMsg: '',

        // Temp
        selProd: null, selVariant: null, selAddons: [], mQty: 1,
        noteIdx: null, noteText: '',
        lastOrderNo: '', lastReceiptUrl: '', lastChange: 0,
        lastPointsRedeemed: 0,
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
            this.tick();
            setInterval(() => this.tick(), 1000);
            this.fetchDrafts();
            this.initBarcodeListener();
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
            const addons = this.selProd.addons?.filter(a => this.selAddons.includes(a.id)) || [];
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
        clearCart()       { if (confirm('Kosongkan semua item?')) { this.cart = []; this.redeemPoints = 0; this.calcTotals(); } },

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
                    promoDisc = p.type === 'percent' ? this.subtotal * p.value / 100 : p.value;
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
                        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                        body:    JSON.stringify(cartBody),
                    });
                    const draft = await draftRes.json();
                    if (!draft.success) throw new Error(draft.message || 'Gagal membuat order');

                    this.midtransOrderId = draft.order_id;
                    this.lastOrderNo     = draft.order_number;

                    // Step 2: Create Midtrans QRIS QR
                    const qrisRes = await fetch('/midtrans/qris/create', {
                        method:  'POST',
                        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                        body:    JSON.stringify({ order_id: draft.order_id, grand_total: draft.grand_total }),
                    });
                    const qris = await qrisRes.json();
                    if (qris.error) throw new Error(qris.error);

                    this.midtransQrUrl = qris.qr_url;
                    this.qrisLoading   = false;

                    // Step 3: Auto-poll payment status every 3 seconds
                    this.midtransPolling = setInterval(async () => {
                        try {
                            const stRes = await fetch('/midtrans/qris/status', {
                                method:  'POST',
                                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
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
                        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                        body:    JSON.stringify({}),
                    });
                    const data = await r.json();
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
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                    body:    JSON.stringify(body),
                });
                const data = await r.json();
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
            this.cart = []; this.customerId = ''; this.promoId = '';
            this.manualDisc = 0; this.payMethod = 'cash'; this.paidAmt = 0;
            this.redeemActive = false; this.redeemPoints = 0;
            this.customerPoints = null;
            this.orderType = 'dine_in'; this.deliveryPlatform = '';
            this.deliveryFee = 0; this.deliveryAddress = ''; this.platformOrderNo = '';
            this.isSplit = false; this.splitPayments = [{ method: 'cash', amount: 0 }];
            this.successModal = false; this.showQrisScreen = false;
            // Reset Midtrans QRIS state
            if (this.midtransPolling) { clearInterval(this.midtransPolling); this.midtransPolling = null; }
            this.midtransQrUrl = null; this.midtransOrderId = null;
            this.qrisLoading = false; this.qrisExpired = false;
            this.calcTotals();
        },

        async holdOrder() {
            if (!this.cart.length) return;
            const holdItems = this.cart.filter(i => !i.isBundle).map(i => ({product_id: i.productId, qty: i.qty, price: i.unitPrice}));
            if (!holdItems.length) { alert('Bundle tidak bisa di-hold, hanya produk reguler.'); return; }
            await fetch('/pos/hold', {
                method:  'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify({ items: holdItems }),
            });
            this.newTransaction();
            await this.fetchDrafts();
        },

        async fetchDrafts() {
            try { const r = await fetch('/pos/drafts'); this.drafts = await r.json(); this.draftCount = this.drafts.length; } catch {}
        },

        async loadDrafts() { await this.fetchDrafts(); this.draftModal = true; },

        async resumeDraft(id) {
            try {
                const r = await fetch('/pos/drafts/'+id+'/load', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                    body: '{}',
                });
                const order = await r.json();
                this.newTransaction();
                (order.items || []).forEach(item => {
                    this.cart.push({
                        isBundle:      false,
                        productId:     item.product_id,
                        name:          item.product_name,
                        variantId:     item.product_variant_id,
                        variantName:   item.variant_name,
                        unitPrice:     parseFloat(item.price),
                        costPrice:     parseFloat(item.cost_price),
                        qty:           parseFloat(item.qty),
                        selectedAddons:[],
                        notes:         item.notes || '',
                        lineTotal()    { return this.unitPrice * this.qty; }
                    });
                });
                this.calcTotals();
                this.draftModal = false;
            } catch { alert('Gagal memuat draft.'); }
        },
    };
}
</script>
</body>
</html>
