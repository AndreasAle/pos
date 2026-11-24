<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Verifikasi WhatsApp — {{ config('app.name') }}</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <style>[x-cloak]{display:none!important}</style>
</head>
@php
    $digits = preg_replace('/\D/', '', (string) $phone);
    $local  = ltrim(preg_replace('/^62/', '', $digits), '0');
    $pretty = '+62 ' . trim(chunk_split($local, 4, ' '));
@endphp
<body class="min-h-full bg-white font-sans text-[#0B1B3F] {{ \App\Support\InApp::check() ? 'in-app' : '' }}">
<div class="min-h-screen flex flex-col">
    <header class="sticky top-0 z-10 h-14 bg-white/95 backdrop-blur border-b border-[#EEF0F3] px-3 flex items-center gap-1">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" aria-label="Kembali" class="w-10 h-10 -ml-1 rounded-full flex items-center justify-center active:bg-[#F1F5F9]">
                <svg class="w-[22px] h-[22px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
        </form>
        <span class="text-[17px] font-semibold">Verifikasi</span>
    </header>

    <main class="flex-1 w-full max-w-md mx-auto px-6 pt-8 pb-10"
          x-data="{
              code: '',
              wait: {{ (int) $wait }},
              init() { this.$refs.code.focus(); setInterval(() => { if (this.wait > 0) this.wait-- }, 1000) },
              clock() { const m = Math.floor(this.wait / 60), s = this.wait % 60; return m + ':' + String(s).padStart(2, '0') },
              typed(e) {
                  this.code = e.target.value.replace(/\D/g, '').slice(0, 6);
                  e.target.value = this.code;
                  if (this.code.length === 6) this.$nextTick(() => this.$refs.form.requestSubmit());
              }
          }">
        <p class="text-[13px] font-semibold text-[#15803D]">Langkah 2 dari 3</p>
        <h1 class="mt-1 text-[28px] leading-tight font-bold tracking-[-0.02em]">Cek WhatsApp kamu</h1>
        <p class="mt-2 text-[15px] leading-relaxed text-[#64748B]">
            Kami kirim 6 digit kode ke <span class="font-semibold text-[#0B1B3F] whitespace-nowrap">{{ $pretty }}</span>.
            Kode berlaku {{ \App\Services\PhoneOtpService::TTL_MINUTES }} menit.
        </p>

        @if(session('success'))
            <p class="mt-5 text-[14px] text-[#15803D]">{{ session('success') }}</p>
        @endif

        <form x-ref="form" method="POST" action="{{ route('verification.whatsapp.verify') }}" class="mt-8">
            @csrf
            {{-- Satu input sungguhan di balik enam kotak: paste dan isi otomatis tetap jalan. --}}
            <label class="relative block" @click="$refs.code.focus()">
                <input x-ref="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                       @input="typed($event)" aria-label="Kode verifikasi 6 digit"
                       class="absolute inset-0 w-full h-full opacity-0 caret-transparent">
                <span class="grid grid-cols-6 gap-2.5" aria-hidden="true">
                    <template x-for="i in 6" :key="i">
                        <span class="h-16 rounded-2xl flex items-center justify-center text-[26px] font-semibold tabular-nums transition-colors"
                              :class="code.length === i - 1 ? 'bg-white ring-2 ring-[#16A34A]' : (code[i - 1] ? 'bg-[#F4F6F8]' : 'bg-[#F4F6F8]')"
                              x-text="code[i - 1] || ''"></span>
                    </template>
                </span>
            </label>

            @if(session('otp_error'))
                <p class="mt-4 text-[14px] text-[#DC2626]">{{ session('otp_error') }}</p>
            @endif

            <button type="submit" :disabled="code.length !== 6"
                    class="mt-8 w-full h-14 rounded-2xl bg-[#16A34A] active:bg-[#15803D] disabled:bg-[#BBF7D0] text-white text-[16px] font-semibold transition-colors">
                Verifikasi
            </button>
        </form>

        <form method="POST" action="{{ route('verification.whatsapp.send') }}" class="mt-6 text-center">
            @csrf
            <p class="text-[15px] text-[#64748B]">
                Belum dapat kode?
                <span x-show="wait > 0">Kirim ulang dalam <span class="tabular-nums font-semibold text-[#0B1B3F]" x-text="clock()"></span></span>
                <button x-show="wait <= 0" x-cloak type="submit" class="font-semibold text-[#15803D]">Kirim ulang</button>
            </p>
        </form>

        <div class="mt-12 pt-6 border-t border-[#F1F5F9] space-y-4 text-center text-[14px]">
            <a href="{{ route('verification.notice', ['via' => 'email']) }}" class="block text-[#64748B]">Pakai verifikasi email saja</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-[#64748B]">Salah nomor? <span class="font-semibold text-[#15803D]">Keluar dan daftar lagi</span></button>
            </form>
        </div>
    </main>
</div>
@vite('resources/js/alpine.js')
</body>
</html>
