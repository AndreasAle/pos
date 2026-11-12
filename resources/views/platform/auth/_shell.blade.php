<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Yoka Platform')</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
    <style>
        .pf-grid{background-image:linear-gradient(rgba(255,255,255,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.045) 1px,transparent 1px);background-size:44px 44px;mask-image:radial-gradient(ellipse 75% 70% at 30% 35%,#000 35%,transparent 100%);-webkit-mask-image:radial-gradient(ellipse 75% 70% at 30% 35%,#000 35%,transparent 100%)}
        @keyframes pf-pulse{0%,100%{opacity:.45;transform:scale(1)}50%{opacity:1;transform:scale(1.25)}}
        @keyframes pf-rise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
        .pf-dot{animation:pf-pulse 2s ease-in-out infinite}
        .pf-rise{animation:pf-rise .55s cubic-bezier(.22,.8,.3,1) both}
        @media (prefers-reduced-motion:reduce){.pf-dot,.pf-rise{animation:none}}
    </style>
</head>
<body class="min-h-full bg-[#F3F6F5] text-[#0B1B3F]">
<main class="grid min-h-screen lg:grid-cols-[minmax(0,1.08fr)_minmax(0,1fr)]">

    {{-- Panel kiri: ruang kendali --}}
    <section class="relative hidden overflow-hidden bg-[#06241A] text-white lg:flex lg:flex-col lg:justify-between lg:p-14">
        <div class="pf-grid absolute inset-0"></div>
        <div class="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-[#16A34A]/25 blur-3xl"></div>
        <div class="absolute -bottom-32 right-0 h-[28rem] w-[28rem] rounded-full bg-[#0A5C3A]/60 blur-3xl"></div>

        <div class="relative flex items-center gap-3">
            <img src="{{ asset('images/brand/yoka-logo-white.png') }}" class="h-10 w-auto" alt="Yoka">
            <span class="rounded-md border border-white/25 px-2.5 py-1 text-[12px] font-bold uppercase tracking-[0.2em] text-emerald-200">Platform</span>
        </div>

        <div class="pf-rise relative max-w-xl">
            <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3.5 py-1.5 text-[13px] font-semibold text-emerald-200">
                <span class="pf-dot h-2 w-2 rounded-full bg-emerald-400"></span> Akses internal tim Yoka
            </span>
            <h1 class="mt-6 text-[44px] font-bold leading-[1.08] tracking-tight">Ruang kendali seluruh ekosistem Yoka POS.</h1>
            <p class="mt-5 max-w-md text-[16px] leading-relaxed text-emerald-100/80">Kelola toko, langganan, bantuan teknis, dan transaksi sensitif dari satu tempat yang terpisah dan terlindungi.</p>

            <ul class="mt-9 grid gap-3.5 text-[15px] text-emerald-50/90">
                @foreach([
                    ['Kelola semua toko', 'Status, trial, langganan, dan outlet per tenant.', 'M3 7l9-4 9 4M5 10v9h14v-9M9 19v-5h6v5'],
                    ['Bantuan teknis berizin', 'Masuk sebagai toko lewat mode bantuan, setiap sesi tercatat.', 'M12 15a3 3 0 100-6 3 3 0 000 6zM2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z'],
                    ['Penarikan dan keuangan', 'Persetujuan penarikan saldo dengan jejak audit.', 'M3 10h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2zM7 15h3'],
                ] as [$t, $d, $p])
                <li class="flex gap-3.5">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-emerald-300 ring-1 ring-white/10"><svg class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $p }}"/></svg></span>
                    <span><span class="block font-bold text-white">{{ $t }}</span><span class="text-[14px] text-emerald-100/70">{{ $d }}</span></span>
                </li>
                @endforeach
            </ul>
        </div>

        <div class="relative flex flex-wrap items-center gap-x-6 gap-y-2 text-[13px] text-emerald-200/80">
            <span class="inline-flex items-center gap-2"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6l8-3z"/></svg> Verifikasi dua langkah wajib</span>
            <span class="inline-flex items-center gap-2"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Setiap akses dicatat</span>
        </div>
    </section>

    {{-- Panel kanan: formulir --}}
    <section class="flex items-center justify-center px-5 py-12 sm:px-8">
        <div class="pf-rise w-full max-w-[440px]">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0A5C3A]"><img src="{{ asset('images/brand/yoka-icon.png') }}" class="h-7 w-7 object-contain" alt="Yoka"></span>
                <span class="text-[16px] font-bold tracking-wide text-[#0A5C3A]">YOKA PLATFORM</span>
            </div>

            {{-- Langkah --}}
            <ol class="mb-6 flex items-center gap-3 text-[13px] font-semibold" aria-label="Langkah masuk">
                <li class="flex items-center gap-2 {{ ($step ?? 1) >= 1 ? 'text-[#0A5C3A]' : 'text-slate-400' }}"><span class="flex h-6 w-6 items-center justify-center rounded-full text-[12px] font-bold {{ ($step ?? 1) > 1 ? 'bg-[#16A34A] text-white' : 'bg-[#0A5C3A] text-white' }}">@if(($step ?? 1) > 1)<svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>@else 1 @endif</span>Kredensial</li>
                <span class="h-px w-8 bg-slate-300"></span>
                <li class="flex items-center gap-2 {{ ($step ?? 1) >= 2 ? 'text-[#0A5C3A]' : 'text-slate-400' }}"><span class="flex h-6 w-6 items-center justify-center rounded-full text-[12px] font-bold {{ ($step ?? 1) >= 2 ? 'bg-[#0A5C3A] text-white' : 'bg-slate-200 text-slate-500' }}">2</span>Kode email</li>
            </ol>

            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-7 shadow-[0_20px_50px_-20px_rgba(6,36,26,.25)] sm:p-9">
                @yield('card')
            </div>

            <p class="mt-6 text-center text-[13px] text-slate-500">@yield('footer')</p>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
