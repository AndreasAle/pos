<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Daftar dengan Google — {{ config('app.name') }}</title>
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
</head>
<body class="min-h-full bg-[#F3F7F5] font-sans text-[#10241B]">
    <header class="border-b border-emerald-950/10 bg-white">
        <div class="mx-auto flex h-20 max-w-[1180px] items-center justify-between px-5 sm:px-8">
            <a href="{{ route('landing') }}" class="inline-flex items-center gap-2" aria-label="Yoka POS">
                <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka" class="h-9 w-auto">
                <span class="text-xl font-bold text-emerald-700">POS</span>
            </a>
            <div class="hidden items-center gap-2 text-sm font-medium text-slate-500 sm:flex">
                <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5-2a9 9 0 11-16 0 9 9 0 0116 0z"/></svg>
                Identitas diverifikasi oleh Google
            </div>
        </div>
    </header>

    <main class="mx-auto grid min-h-[calc(100vh-80px)] max-w-[1180px] items-center gap-12 px-5 py-12 sm:px-8 lg:grid-cols-[1fr_460px] lg:gap-20 lg:py-16">
        <section>
            <p class="text-sm font-bold uppercase tracking-[.18em] text-emerald-700">Akun baru</p>
            <h1 class="mt-5 max-w-[650px] text-4xl font-extrabold leading-[1.08] tracking-[-.04em] sm:text-5xl lg:text-[58px]">
                Satu langkah sebelum menyiapkan bisnis Anda.
            </h1>
            <p class="mt-6 max-w-[590px] text-lg leading-8 text-slate-600">
                Email Google ini belum memiliki akun Yoka POS. Daftarkan sebagai akun pemilik, lalu kami akan membantu menyiapkan profil bisnis dan outlet utama.
            </p>

            <div class="mt-10 max-w-[620px] border-y border-slate-300">
                @foreach([
                    ['01', 'Daftarkan akun pemilik', 'Gunakan identitas Google yang baru saja Anda pilih.'],
                    ['02', 'Lengkapi informasi bisnis', 'Tentukan jenis usaha, lokasi, dan outlet utama.'],
                    ['03', 'Mulai operasional', 'Kenali dashboard melalui panduan interaktif Yoka.'],
                ] as $item)
                <div class="grid grid-cols-[52px_1fr] gap-4 border-b border-slate-300 py-5 last:border-b-0">
                    <span class="font-mono text-sm font-bold text-emerald-700">{{ $item[0] }}</span>
                    <div><p class="font-bold">{{ $item[1] }}</p><p class="mt-1 text-sm leading-6 text-slate-500">{{ $item[2] }}</p></div>
                </div>
                @endforeach
            </div>
        </section>

        <section class="border border-slate-200 bg-white p-6 shadow-[0_24px_70px_rgba(15,64,43,.10)] sm:p-8">
            <div class="flex items-center justify-between border-b border-slate-200 pb-5">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.16em] text-slate-400">Akun terpilih</p>
                    <p class="mt-1 text-lg font-bold">Daftar ke Yoka POS</p>
                </div>
                <svg class="h-7 w-7" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.07H12v3.92h5.38a4.6 4.6 0 0 1-2 3.02v2.54h3.24c1.9-1.75 2.98-4.33 2.98-7.41Z"/><path fill="#34A853" d="M12 22c2.7 0 4.98-.9 6.64-2.43l-3.24-2.54c-.9.6-2.05.96-3.4.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.62A10 10 0 0 0 12 22Z"/><path fill="#FBBC05" d="M6.39 13.86A6.02 6.02 0 0 1 6.07 12c0-.65.11-1.28.32-1.86V7.52H3.04A10 10 0 0 0 2 12c0 1.61.38 3.13 1.04 4.48l3.35-2.62Z"/><path fill="#EA4335" d="M12 6.01c1.47 0 2.79.5 3.83 1.5l2.88-2.88A9.65 9.65 0 0 0 12 2a10 10 0 0 0-8.96 5.52l3.35 2.62C7.18 7.77 9.39 6 12 6Z"/></svg>
            </div>

            <div class="flex items-center gap-4 py-7">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center bg-emerald-100 text-xl font-extrabold text-emerald-700">{{ mb_strtoupper(mb_substr($pending['name'], 0, 1)) }}</span>
                <div class="min-w-0"><p class="truncate font-bold text-slate-900">{{ $pending['name'] }}</p><p class="mt-1 truncate text-sm text-slate-500">{{ $pending['email'] }}</p></div>
                <span class="ml-auto flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
                </span>
            </div>

            <div class="border-l-2 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-900">
                Email sudah diverifikasi Google. Anda tidak perlu melakukan verifikasi email ulang.
            </div>

            <form method="POST" action="{{ route('auth.google.register.store') }}" class="mt-6">
                @csrf
                <button type="submit" class="flex h-[52px] w-full items-center justify-center gap-2 bg-emerald-600 px-5 font-bold text-white transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-600/20">
                    Daftar dengan akun ini
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </button>
            </form>

            <a href="{{ route('auth.google') }}" class="mt-3 flex h-12 w-full items-center justify-center border border-slate-300 bg-white px-5 text-sm font-bold text-slate-700 transition-colors hover:bg-slate-50">Gunakan akun Google lain</a>
            <p class="mt-6 text-center text-xs leading-5 text-slate-400">Dengan melanjutkan, akun ini akan menjadi pemilik bisnis di Yoka POS.</p>
        </section>
    </main>
</body>
</html>
