<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Masuk — {{ config('app.name') }}</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <style>[x-cloak]{display:none!important}</style>
</head>
{{-- Masuk versi aplikasi Android: satu layar bersih seperti Daftar. Tanpa tombol
     Google karena Google menolak login di dalam WebView aplikasi. --}}
@php
    $field = 'w-full h-14 rounded-2xl bg-[#F4F6F8] border border-transparent px-4 text-[16px] text-[#0B1B3F] placeholder:text-[#94A3B8] focus:outline-none focus:bg-white focus:border-[#16A34A] focus:ring-4 focus:ring-[#16A34A]/10 transition';
    $bad   = '!border-[#F87171] !bg-[#FEF2F2]';
    $lbl   = 'block mb-2 text-[13px] font-medium text-[#64748B]';
@endphp
<body class="in-app min-h-full bg-white font-sans text-[#0B1B3F]">
<div class="min-h-screen">
    <header class="sticky top-0 z-10 h-14 bg-white/95 backdrop-blur border-b border-[#EEF0F3] px-3 flex items-center gap-1">
        <button type="button" onclick="history.length > 1 ? history.back() : null" aria-label="Kembali"
                class="w-10 h-10 -ml-1 rounded-full flex items-center justify-center active:bg-[#F1F5F9]">
            <svg class="w-[22px] h-[22px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <span class="text-[17px] font-semibold">Masuk</span>
    </header>

    <main class="max-w-md mx-auto px-6 pt-8 pb-10">
        <h1 class="text-[28px] leading-tight font-bold tracking-[-0.02em]">Selamat datang kembali</h1>
        <p class="mt-2 text-[15px] text-[#64748B]">Masuk dengan akun pemilik untuk menghubungkan perangkat ini.</p>

        @if(session('success'))
            <p class="mt-5 text-[14px] text-[#15803D]">{{ session('success') }}</p>
        @endif

        <form method="POST" action="{{ route('login.post') }}" class="mt-8 space-y-5" x-data="{ show: false }">
            @csrf
            <div>
                <label for="email" class="{{ $lbl }}">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" inputmode="email"
                       placeholder="nama@usaha.com" class="{{ $field }} {{ $errors->has('email') ? $bad : '' }}">
                @error('email')<p class="mt-1.5 text-[13px] text-[#DC2626]">{{ $message }}</p>@enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between">
                    <label for="password" class="{{ $lbl }}">Password</label>
                    <a href="{{ route('password.request') }}" class="text-[13px] font-semibold text-[#15803D]">Lupa password?</a>
                </div>
                <div class="relative">
                    <input id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password"
                           class="{{ $field }} pr-14 {{ $errors->has('password') ? $bad : '' }}">
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 w-14 flex items-center justify-center text-[#94A3B8]" :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                        <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <svg x-show="show" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587a2 2 0 002.828 2.83M9.363 5.365A9.466 9.466 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411M6.423 6.423C4.726 7.654 3.405 9.64 2.458 12c.55 1.392 1.345 2.625 2.32 3.665"/></svg>
                    </button>
                </div>
                @error('password')<p class="mt-1.5 text-[13px] text-[#DC2626]">{{ $message }}</p>@enderror
            </div>

            <label class="flex items-center gap-3 text-[15px] text-[#475569]">
                <input type="checkbox" name="remember" value="1" class="w-5 h-5 rounded-md border-[#CBD5E1] text-[#16A34A] focus:ring-[#16A34A]/30">
                Ingat saya di perangkat ini
            </label>

            <button type="submit" class="!mt-8 w-full h-14 rounded-2xl bg-[#16A34A] active:bg-[#15803D] text-white text-[16px] font-semibold shadow-[0_8px_20px_rgba(22,163,74,.22)]">
                Masuk
            </button>
        </form>

        <a href="{{ route('pin.show') }}" class="mt-4 flex items-center justify-center w-full h-14 rounded-2xl bg-[#F1F5F9] active:bg-[#E2E8F0] text-[16px] font-semibold">
            Kasir? Masuk pakai PIN
        </a>

        <p class="mt-10 text-center text-[15px] text-[#64748B]">
            Belum punya akun?
            <a href="{{ route('register') }}" class="font-semibold text-[#15803D]">Daftar gratis</a>
        </p>
    </main>
</div>
@vite('resources/js/alpine.js')
</body>
</html>
