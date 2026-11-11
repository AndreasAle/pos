@extends('layouts.auth-split')
@section('title', 'Password Baru')

@section('left-title', 'Buat Password Baru')
@section('left-subtitle', 'Pilih password yang kuat dan belum pernah Anda pakai di tempat lain.')

@section('content')
<h3 class="text-xl font-bold text-gray-900 mb-1 text-center lg:text-left">Password Baru</h3>
<p class="text-sm text-gray-500 mb-6 text-center lg:text-left">Minimal 8 karakter.</p>

<form method="POST" action="{{ route('password.update') }}" class="space-y-4" x-data="{ show: false }">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email', $email) }}" required readonly
               class="w-full px-3 border border-gray-300 text-sm bg-slate-50 text-slate-600">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password baru</label>
        <div class="relative">
            <input :type="show ? 'text' : 'password'" name="password" required autofocus autocomplete="new-password"
                   class="w-full px-3 pr-10 border border-gray-300 text-sm">
            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600" aria-label="Tampilkan password">
                <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587a2 2 0 002.828 2.83M9.363 5.365A9.466 9.466 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411M6.423 6.423C4.726 7.654 3.405 9.64 2.458 12c.55 1.392 1.345 2.625 2.32 3.665"/></svg>
            </button>
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Ulangi password baru</label>
        <input :type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password"
               class="w-full px-3 border border-gray-300 text-sm">
    </div>
    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-full text-[15px] transition-colors">
        Simpan Password
    </button>
</form>
@endsection
