@extends('platform.auth._shell', ['step' => 1])
@section('title', 'Login Tim Yoka')

@section('card')
@php $field = 'h-12 w-full rounded-xl border border-[#E5E7EB] bg-white pl-11 pr-4 text-[15px] font-medium text-[#0B1B3F] placeholder:text-slate-400 transition focus:border-[#16A34A] focus:outline-none focus:ring-4 focus:ring-[#16A34A]/10'; @endphp
<div class="flex items-center gap-2 text-[12px] font-bold uppercase tracking-[0.18em] text-[#0A5C3A]">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6l8-3z"/></svg>
    Akun tim Yoka
</div>
<h2 class="mt-3 text-[30px] font-bold leading-tight tracking-tight">Masuk ke backoffice</h2>
<p class="mt-2 text-[15px] text-slate-500">Setelah email dan password benar, kode 6 digit dikirim ke email kamu.</p>

@if($errors->any())
<div class="mt-5 flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[14px] font-semibold text-red-700" role="alert">
    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.3L2.6 18a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 4.3a2 2 0 00-3.4 0z"/></svg>
    <span>{{ $errors->first() }}</span>
</div>
@endif

<form method="POST" action="{{ route('platform.login.store') }}" class="mt-7 space-y-5" x-data="{ show: false, busy: false }" @submit="busy = true">
    @csrf
    <div>
        <label for="email" class="mb-1.5 block text-[14px] font-semibold text-slate-700">Email platform</label>
        <div class="relative">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nama@yokapos.com" class="{{ $field }}">
        </div>
    </div>
    <div>
        <label for="password" class="mb-1.5 block text-[14px] font-semibold text-slate-700">Password</label>
        <div class="relative">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 10-8 0v4m-1 0h10a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-6a2 2 0 012-2z"/></svg>
            <input id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password" class="{{ $field }} pr-12">
            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-400 hover:text-slate-600" :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 6.2A9.8 9.8 0 0112 6c6 0 9.5 6 9.5 6a16 16 0 01-3.1 3.8M6.5 6.8C3.9 8.5 2.5 12 2.5 12S6 18 12 18c1.5 0 2.8-.4 4-.9"/></svg>
            </button>
        </div>
    </div>
    <button type="submit" :disabled="busy" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#0A5C3A] text-[15px] font-bold text-white shadow-sm transition hover:bg-[#08482E] disabled:opacity-70">
        <span x-text="busy ? 'Memeriksa…' : 'Lanjutkan ke verifikasi'"></span>
        <svg x-show="!busy" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6"/></svg>
    </button>
</form>

<div class="mt-6 flex items-start gap-2.5 rounded-xl bg-[#F4FBF7] px-4 py-3 text-[13px] text-[#0A5C3A]">
    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <span>Halaman khusus tim Yoka. Setiap percobaan masuk dicatat dan dibatasi 5 kali per menit.</span>
</div>
@endsection

@section('footer')
Bukan tim Yoka? <a href="{{ route('login') }}" class="font-semibold text-[#0A5C3A] hover:underline">Masuk ke akun toko</a>
@endsection
