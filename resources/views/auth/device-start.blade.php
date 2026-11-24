@if(\App\Support\InApp::check())
{{-- Versi aplikasi Android: bar atas + dua bagian tenang, tombol mulai menempel di bawah. --}}
@php
    $field = 'w-full h-14 rounded-2xl bg-[#F4F6F8] border border-transparent px-4 text-[16px] text-[#0B1B3F] placeholder:text-[#94A3B8] focus:outline-none focus:bg-white focus:border-[#16A34A] focus:ring-4 focus:ring-[#16A34A]/10 transition';
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $ready = $products->isNotEmpty();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Siapkan kasir — {{ config('app.name') }}</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
</head>
<body class="in-app min-h-screen bg-white font-sans text-[#0B1B3F]">
    <header class="sticky top-0 z-20 h-14 bg-white/95 backdrop-blur border-b border-[#EEF0F3] px-5 flex items-center justify-between">
        <span class="text-[17px] font-semibold">Siapkan kasir</span>
        <span class="text-[13px] text-[#94A3B8]">Langkah terakhir</span>
    </header>

    <main class="max-w-md mx-auto px-6 pt-8 pb-36">
        <h1 class="text-[28px] leading-tight font-bold tracking-[-0.02em]">Tambah produk pertamamu</h1>
        <p class="mt-2 text-[15px] leading-relaxed text-[#64748B]">Cukup nama dan harga. Kategori, varian, dan stok bisa diatur nanti.</p>

        @if(session('success'))
            <p class="mt-5 text-[14px] text-[#15803D]">{{ session('success') }}</p>
        @endif

        {{-- Produk --}}
        <section class="mt-8">
            <div class="flex items-baseline justify-between">
                <h2 class="text-[13px] font-semibold text-[#64748B]">Produk</h2>
                <span class="text-[13px] {{ $ready ? 'text-[#15803D]' : 'text-[#94A3B8]' }}">{{ $ready ? $products->count() . ' produk' : 'Belum ada' }}</span>
            </div>
            @if($ready)
                <ul class="mt-2 divide-y divide-[#F1F5F9] border-y border-[#F1F5F9]">
                    @foreach($products as $p)
                        <li class="flex justify-between gap-4 py-3.5 text-[16px]"><span class="truncate">{{ $p->name }}</span><span class="shrink-0 tabular-nums font-semibold">{{ $rp($p->price) }}</span></li>
                    @endforeach
                </ul>
            @endif
            <form method="POST" action="{{ route('device.start.product') }}" class="mt-4 space-y-3">
                @csrf
                <input name="name" required maxlength="120" placeholder="Nama produk, mis. Es Teh Manis" value="{{ old('name') }}" class="{{ $field }}">
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[16px] text-[#64748B]">Rp</span>
                    <input name="price" required type="number" min="0" step="100" inputmode="numeric" placeholder="Harga jual" value="{{ old('price') }}" class="{{ $field }} pl-12">
                </div>
                @error('name')<p class="text-[13px] text-[#DC2626]">{{ $message }}</p>@enderror
                @error('price')<p class="text-[13px] text-[#DC2626]">{{ $message }}</p>@enderror
                <button class="w-full h-12 rounded-2xl {{ $ready ? 'bg-[#F1F5F9] text-[#0B1B3F] active:bg-[#E2E8F0]' : 'bg-[#16A34A] text-white active:bg-[#15803D]' }} text-[15px] font-semibold">
                    {{ $ready ? 'Tambah produk lain' : 'Tambah produk' }}
                </button>
            </form>
        </section>

        {{-- Kasir --}}
        <section class="mt-12">
            <div class="flex items-baseline justify-between">
                <h2 class="text-[13px] font-semibold text-[#64748B]">Kasir <span class="font-normal text-[#94A3B8]">(opsional)</span></h2>
                <span class="text-[13px] text-[#94A3B8]">{{ $cashiers->isEmpty() ? 'Kamu sendiri' : $cashiers->count() . ' kasir' }}</span>
            </div>
            <p class="mt-1 text-[14px] text-[#94A3B8]">Tambah kalau ada karyawan yang ikut jaga kasir.</p>
            @if($cashiers->isNotEmpty())
                <ul class="mt-2 divide-y divide-[#F1F5F9] border-y border-[#F1F5F9]">
                    @foreach($cashiers as $c)
                        <li class="flex justify-between gap-4 py-3.5 text-[16px]"><span class="truncate">{{ $c->name }}</span><span class="shrink-0 text-[13px] text-[#94A3B8]">{{ $c->pin ? 'PIN aktif' : 'Belum ada PIN' }}</span></li>
                    @endforeach
                </ul>
            @endif
            <form method="POST" action="{{ route('device.start.cashier') }}" class="mt-4 space-y-3">
                @csrf
                <input name="name" required maxlength="80" placeholder="Nama kasir" class="{{ $field }}">
                <input name="pin" required type="password" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="6" placeholder="PIN kasir (4–6 angka)" class="{{ $field }}">
                @error('pin')<p class="text-[13px] text-[#DC2626]">{{ $message }}</p>@enderror
                <button class="w-full h-12 rounded-2xl bg-[#F1F5F9] active:bg-[#E2E8F0] text-[15px] font-semibold">Tambah kasir</button>
            </form>
        </section>
    </main>

    {{-- Mulai jualan: selalu terlihat, aktif setelah ada produk --}}
    <div class="fixed inset-x-0 bottom-0 z-20 bg-white/95 backdrop-blur border-t border-[#EEF0F3] px-6 pt-3 pb-[calc(14px+env(safe-area-inset-bottom))]">
        <div class="max-w-md mx-auto">
            @if($ready)
                <a href="{{ route('pos.index') }}" class="block w-full h-14 leading-[56px] text-center rounded-2xl bg-[#0B1B3F] active:bg-[#1E293B] text-white text-[16px] font-semibold">Mulai jualan</a>
            @else
                <span class="block w-full h-14 leading-[56px] text-center rounded-2xl bg-[#F1F5F9] text-[#94A3B8] text-[16px] font-semibold">Tambah minimal satu produk dulu</span>
            @endif
        </div>
    </div>
</body>
</html>
@else
<x-device-shell title="Siapkan kasir" subtitle="Dua hal sebelum mulai jualan. Detail lain (kategori, varian, stok, laporan) bisa diatur nanti di back office lewat browser." width="max-w-2xl">
    @if(session('success'))
        <div class="mb-4 rounded-xl bg-[#ECFDF3] border border-[#BBF7D0] text-[#15803D] px-4 py-3 text-sm font-semibold">{{ session('success') }}</div>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        {{-- Produk --}}
        <section class="bg-white rounded-xl p-5 shadow-2xl">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-[#0B1B3F]">1. Produk</h2>
                <x-pill :tone="$products->isEmpty() ? 'amber' : 'green'">{{ $products->isEmpty() ? 'Belum ada' : $products->count() . ' produk' }}</x-pill>
            </div>
            <form method="POST" action="{{ route('device.start.product') }}" class="mt-4 space-y-3">
                @csrf
                <input name="name" required maxlength="120" placeholder="Nama produk, mis. Es Teh Manis" value="{{ old('name') }}"
                       class="w-full px-3.5 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#16A34A]">
                <div class="relative">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-500">Rp</span>
                    <input name="price" required type="number" min="0" step="100" inputmode="numeric" placeholder="Harga jual" value="{{ old('price') }}"
                           class="w-full pl-10 pr-3.5 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#16A34A]">
                </div>
                @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @error('price')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button class="w-full bg-[#16A34A] hover:bg-emerald-700 text-white font-bold py-3 rounded-xl text-sm">Tambah produk</button>
            </form>
            @if($products->isNotEmpty())
                <ul class="mt-4 divide-y divide-gray-100 text-sm">
                    @foreach($products as $p)
                        <li class="flex justify-between py-2"><span>{{ $p->name }}</span><span class="font-semibold">Rp {{ number_format($p->price, 0, ',', '.') }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Kasir --}}
        <section class="bg-white rounded-xl p-5 shadow-2xl">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-[#0B1B3F]">2. Kasir <span class="font-normal text-gray-400 text-sm">(opsional)</span></h2>
                <x-pill :tone="$cashiers->isEmpty() ? 'gray' : 'green'">{{ $cashiers->isEmpty() ? 'Anda sendiri' : $cashiers->count() . ' kasir' }}</x-pill>
            </div>
            <form method="POST" action="{{ route('device.start.cashier') }}" class="mt-4 space-y-3">
                @csrf
                <input name="name" required maxlength="80" placeholder="Nama kasir"
                       class="w-full px-3.5 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#16A34A]">
                <input name="pin" required type="password" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="6" placeholder="PIN kasir (4–6 angka)"
                       class="w-full px-3.5 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#16A34A]">
                @error('pin')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button class="w-full border border-[#16A34A] text-[#16A34A] hover:bg-[#ECFDF3] font-bold py-3 rounded-xl text-sm">Tambah kasir</button>
            </form>
            @if($cashiers->isNotEmpty())
                <ul class="mt-4 divide-y divide-gray-100 text-sm">
                    @foreach($cashiers as $c)
                        <li class="flex justify-between py-2"><span>{{ $c->name }}</span><span class="text-gray-500">{{ $c->pin ? 'PIN aktif' : 'Belum ada PIN' }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <a href="{{ route('pos.index') }}"
       class="block mt-5 text-center font-bold py-4 rounded-xl text-lg transition-colors
              {{ $products->isEmpty() ? 'bg-white/20 text-white/70 pointer-events-none' : 'bg-white text-[#0B1B3F] hover:bg-emerald-50' }}">
        {{ $products->isEmpty() ? 'Tambah minimal satu produk dulu' : 'Mulai jualan' }}
    </a>
</x-device-shell>
@endif
