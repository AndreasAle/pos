@extends('layouts.auth-split')
@section('title', 'Masuk')

@section('left-title', 'Selamat Datang Kembali di FNB POS')
@section('left-subtitle', 'Masuk untuk melanjutkan pengelolaan kasir, stok, shift, dan laporan bisnis F&B Anda.')

@section('content')
<h3 class="text-xl font-bold text-gray-900 mb-1 text-center lg:text-left">Masuk ke Akun Anda</h3>
<p class="text-sm text-gray-500 mb-6 text-center lg:text-left">Kelola transaksi dan pantau bisnis Anda kapan saja.</p>

<form method="POST" action="{{ route('login.post') }}" class="space-y-4">
    @csrf
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus
               class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent @error('email') border-red-400 @enderror"
               placeholder="email@bisnis.com">
        @error('email')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div x-data="{ show: false }">
        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <div class="relative">
            <input :type="show ? 'text' : 'password'" name="password" required
                   class="w-full px-3 py-2.5 pr-10 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                   placeholder="••••••••">
            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600">
                <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587a2 2 0 002.828 2.83M9.363 5.365A9.466 9.466 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411M6.423 6.423C4.726 7.654 3.405 9.64 2.458 12c.55 1.392 1.345 2.625 2.32 3.665"/></svg>
            </button>
        </div>
    </div>

    <div class="flex items-center">
        <input type="checkbox" name="remember" id="remember" class="h-4 w-4 text-emerald-600 rounded border-gray-300">
        <label for="remember" class="ml-2 text-sm text-gray-600">Ingat saya</label>
    </div>

    <button type="submit"
            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-4 rounded-xl text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 shadow-lg shadow-emerald-200">
        Masuk
    </button>
</form>

<p class="mt-5 text-center text-sm text-gray-500">
    Belum punya akun?
    <a href="{{ route('register') }}" class="text-emerald-600 font-medium hover:underline">Daftar &amp; coba gratis 7 hari</a>
</p>
@endsection
