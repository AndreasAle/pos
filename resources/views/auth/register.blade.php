@extends('layouts.auth-split')
@section('title', 'Daftar Bisnis')

@section('left-title', 'Daftar Sekarang dan Nikmati Trial 7 Hari — Semua Fitur Terbuka!')
@section('left-subtitle', 'Coba seluruh fitur FNB POS tanpa batasan selama 7 hari penuh: kasir, QRIS, stok, shift, laporan profit, hingga audit log. Tanpa kartu kredit.')

@section('content')
<div class="text-center lg:text-left mb-6">
    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold mb-3">
        🎁 Trial 7 Hari · Semua Fitur Terbuka
    </span>
    <h3 class="text-xl font-bold text-gray-900">Daftarkan Bisnis Anda</h3>
    <p class="text-sm text-gray-500 mt-1">Gratis 7 hari coba semua fitur — tidak perlu kartu kredit</p>
</div>

<form method="POST" action="{{ route('register.post') }}" class="space-y-4">
    @csrf

    <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100">
        <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide mb-3">Informasi Bisnis</p>
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Bisnis <span class="text-red-500">*</span></label>
                <input type="text" name="business_name" value="{{ old('business_name') }}" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('business_name') border-red-400 @enderror"
                       placeholder="Contoh: Cafe Kopi Nusantara">
                @error('business_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Outlet Utama <span class="text-red-500">*</span></label>
                <input type="text" name="outlet_name" value="{{ old('outlet_name') }}" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('outlet_name') border-red-400 @enderror"
                       placeholder="Contoh: Outlet Pusat">
                @error('outlet_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Akun Owner</p>
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                       placeholder="Nama Anda">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('email') border-red-400 @enderror"
                       placeholder="email@bisnis.com">
                @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label>
                <input type="text" name="phone" value="{{ old('phone') }}"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                       placeholder="08xx-xxxx-xxxx">
            </div>
            <div x-data="{ show: false }">
                <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input :type="show ? 'text' : 'password'" name="password" required
                           class="w-full px-3 py-2.5 pr-10 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('password') border-red-400 @enderror"
                           placeholder="Minimal 8 karakter">
                    <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600">
                        <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587a2 2 0 002.828 2.83M9.363 5.365A9.466 9.466 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411M6.423 6.423C4.726 7.654 3.405 9.64 2.458 12c.55 1.392 1.345 2.625 2.32 3.665"/></svg>
                    </button>
                </div>
                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="mt-1.5 text-xs text-gray-400">Kombinasikan minimal 8 karakter, huruf besar, huruf kecil, dan angka.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                <input type="password" name="password_confirmation" required
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                       placeholder="Ulangi password">
            </div>
        </div>
    </div>

    <button type="submit"
            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-4 rounded-xl text-sm transition-colors shadow-lg shadow-emerald-200">
        Mulai Trial Gratis 7 Hari
    </button>
    <p class="text-center text-xs text-gray-400">Dengan mendaftar, akun Anda otomatis mendapat akses penuh selama 7 hari — tanpa kartu kredit.</p>
</form>

<p class="mt-4 text-center text-sm text-gray-500">
    Sudah punya akun?
    <a href="{{ route('login') }}" class="text-emerald-600 font-medium hover:underline">Masuk</a>
</p>
@endsection
