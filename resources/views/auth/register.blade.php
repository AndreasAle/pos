<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar — {{ config('app.name') }}</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">
    <style>
        [x-cloak]{display:none!important}
        .reg input:not([type=hidden]){height:3rem;border-radius:.75rem;border:1px solid #cbd5e1;padding:0 .875rem;font-size:.9375rem;width:100%;background:#fff}
        .reg input:focus{border-color:#059669;box-shadow:0 0 0 3px rgba(5,150,105,.18);outline:none}
        .reg input.err{border-color:#f87171}
        .reg label{display:block;font-size:.8125rem;font-weight:600;color:#334155;margin-bottom:.375rem}
    </style>
</head>
<body class="min-h-full font-sans text-slate-800 bg-[#f2f9f5]">
@php
    $err = fn ($f) => $errors->first($f);
    $oldPhone = preg_replace('/^0+/', '', preg_replace('/\D/', '', old('phone', '')));
@endphp
<div class="min-h-screen">
    <div class="max-w-[1200px] mx-auto px-6 pt-8 pb-12 lg:pt-10">
        <a href="{{ route('landing') }}" class="inline-flex items-center gap-2" aria-label="Yoka POS">
            <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka" class="h-9 w-auto">
            <span class="text-[22px] leading-none font-bold tracking-tight text-emerald-700">POS</span>
        </a>

        <div class="mt-8 lg:mt-12 grid lg:grid-cols-[1fr_480px] gap-12 xl:gap-20 items-start">

            {{-- Kiri: pesan + gambar --}}
            <div class="hidden lg:block lg:sticky lg:top-10">
                <h1 class="text-[40px] xl:text-[44px] font-extrabold leading-[1.15] text-emerald-900 max-w-[560px]">
                    Mulai Jualan dengan Kasir yang Rapi
                </h1>
                <p class="mt-4 text-lg text-slate-600 max-w-[520px]">Buat akun dalam satu menit. Setelah email terverifikasi, kami bantu menyiapkan bisnis Anda langkah demi langkah.</p>

                <div class="relative mt-10 rounded-[32px] bg-[#01a05b] overflow-hidden max-w-[600px]">
                    <img src="{{ asset('images/hero/slide-kasir.webp') }}" alt="Tablet kasir dan printer struk Yoka POS" class="w-full block">
                    <div class="absolute left-5 top-5 flex flex-col items-start gap-2">
                        @foreach (['Coba gratis 7 hari', 'Semua fitur terbuka', 'Tanpa kartu kredit'] as $chip)
                            <span class="px-4 py-2 rounded-full bg-white text-emerald-800 text-sm font-bold shadow-md">{{ $chip }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Kanan: form --}}
            <div class="w-full max-w-[480px] mx-auto lg:mx-0 bg-white rounded-3xl shadow-[0_10px_40px_rgba(6,95,70,.10)] p-7 sm:p-9">
                <h2 class="text-2xl font-extrabold text-slate-900">Daftar Akun</h2>
                <p class="text-sm text-slate-500 mt-1 mb-6">Langkah 1 dari 3 · Buat akun pemilik.</p>

                @if ($errors->any())
                    <div class="mb-5 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                        @foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('register.post') }}" class="reg space-y-4"
                      x-data="{ show: false, pw: '', phone: '{{ $oldPhone }}',
                        get rules() { return [
                            ['Minimal 8 karakter', this.pw.length >= 8],
                            ['Huruf besar', /[A-Z]/.test(this.pw)],
                            ['Huruf kecil', /[a-z]/.test(this.pw)],
                            ['Angka', /\d/.test(this.pw)],
                        ] } }">
                    @csrf

                    <div>
                        <label for="name">Nama Lengkap</label>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus placeholder="Nama pemilik bisnis">
                    </div>
                    <div>
                        <label for="phone_local">Nomor HP</label>
                        <div class="flex">
                            <span class="flex items-center gap-2 px-3 border border-r-0 border-slate-300 rounded-l-xl bg-slate-50 text-[15px] text-slate-700 shrink-0">
                                <svg class="w-5 h-[14px] rounded-[2px] ring-1 ring-slate-200" viewBox="0 0 3 2"><rect width="3" height="1" fill="#e11d2e"/><rect y="1" width="3" height="1" fill="#fff"/></svg>
                                +62
                            </span>
                            <input id="phone_local" type="tel" inputmode="numeric" x-model="phone" placeholder="812xxxxxxxx" style="border-radius:0 .75rem .75rem 0">
                        </div>
                        <input type="hidden" name="phone" :value="phone ? '0' + phone.replace(/\D/g,'').replace(/^0+/,'') : ''">
                    </div>
                    <div>
                        <label for="email">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required
                               placeholder="email@bisnis.com" class="{{ $err('email') ? 'err' : '' }}">
                    </div>
                    <div>
                        <label for="password">Password</label>
                        <div class="relative">
                            <input id="password" :type="show ? 'text' : 'password'" name="password" x-model="pw" required
                                   class="{{ $err('password') ? 'err' : '' }}" style="padding-right:2.75rem" autocomplete="new-password">
                            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3.5 flex items-center text-slate-400 hover:text-slate-600" aria-label="Tampilkan password">
                                <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <svg x-show="show" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587a2 2 0 002.828 2.83M9.363 5.365A9.466 9.466 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411M6.423 6.423C4.726 7.654 3.405 9.64 2.458 12c.55 1.392 1.345 2.625 2.32 3.665"/></svg>
                            </button>
                        </div>
                        <input type="hidden" name="password_confirmation" :value="pw">
                        <ul class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-[13px]">
                            <template x-for="r in rules" :key="r[0]">
                                <li class="flex items-center gap-2" :class="r[1] ? 'text-emerald-700' : 'text-slate-400'">
                                    <span class="w-4 h-4 rounded-full flex items-center justify-center border" :class="r[1] ? 'bg-emerald-600 border-emerald-600' : 'border-slate-300'">
                                        <svg x-show="r[1]" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                    <span x-text="r[0]"></span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <button type="submit" class="w-full h-12 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[15px] transition-colors">
                        Buat Akun &amp; Verifikasi Email
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-600">
                    Sudah punya akun?
                    <a href="{{ route('login') }}" class="text-emerald-700 font-bold hover:underline">Masuk</a>
                </p>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
