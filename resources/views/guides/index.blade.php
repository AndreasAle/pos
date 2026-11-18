<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panduan Yoka POS — Cara Pakai Aplikasi Kasir Langkah demi Langkah</title>
    <meta name="description" content="Panduan lengkap Yoka POS: mulai pakai, printer struk, menu dan harga, shift kasir, stok dan resep, laporan, Bot Telegram, dan cara berlangganan.">
    <link rel="canonical" href="{{ route('guides.index') }}">
    @include('layouts._fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/yoka-icon.png') }}">
</head>
<body class="bg-white text-slate-800 antialiased">
    @php $waLink = 'https://wa.me/6285891111795?text=Halo%20saya%20butuh%20bantuan%20memakai%20Yoka%20POS'; @endphp
    @include('partials.guide-header')

    <main class="pt-[60px] sm:pt-[68px] lg:pt-[76px]">
        <section class="bg-emerald-600 text-white">
            <div class="max-w-[1200px] mx-auto px-5 sm:px-6 py-12 sm:py-16 lg:py-20">
                <p class="text-xs sm:text-sm font-bold uppercase tracking-[.16em] text-emerald-100">Panduan</p>
                <h1 class="mt-3 max-w-[720px] text-[32px] sm:text-[42px] lg:text-[52px] font-extrabold leading-[1.08] tracking-[-.03em]">Belajar Yoka POS, langkah demi langkah</h1>
                <p class="mt-4 max-w-[600px] text-[16px] sm:text-[18px] leading-relaxed text-emerald-50">Dari daftar akun sampai laporan harian. Tiap panduan singkat, bisa dibaca dari HP sambil jaga kasir.</p>
            </div>
        </section>

        <section class="max-w-[1200px] mx-auto px-5 sm:px-6 py-10 sm:py-14">
            <div class="grid gap-4 sm:gap-5 md:grid-cols-2">
                @foreach ($guides as $slug => $guide)
                    <a href="{{ route('guides.show', $slug) }}" class="group flex gap-4 sm:gap-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 transition hover:border-emerald-300 hover:shadow-[0_20px_45px_-20px_rgba(5,150,105,.35)]">
                        <span class="flex h-11 w-11 sm:h-12 sm:w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-[17px] font-extrabold text-emerald-700">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="min-w-0">
                            <span class="block text-[12px] font-bold uppercase tracking-[.12em] text-emerald-700">{{ $guide['category'] }} &middot; {{ $guide['minutes'] }} menit</span>
                            <span class="mt-1 block text-[18px] sm:text-[20px] font-extrabold leading-snug text-slate-900 group-hover:text-emerald-700">{{ $guide['title'] }}</span>
                            <span class="mt-2 block text-[15px] leading-relaxed text-slate-600">{{ $guide['summary'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="max-w-[1200px] mx-auto px-5 sm:px-6 pb-14">
            <div class="flex flex-col gap-5 rounded-2xl bg-slate-50 p-6 sm:p-8 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-[22px] sm:text-[26px] font-extrabold tracking-[-.02em] text-slate-900">Masih bingung?</h2>
                    <p class="mt-1 text-[15px] text-slate-600">Chat tim Yoka, kami bantu sampai tokomu jalan.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="rounded-full bg-emerald-600 px-6 py-3 text-[15px] font-bold text-white hover:bg-emerald-700">Coba Gratis 7 Hari</a>
                    <a href="{{ $waLink }}" target="_blank" rel="noopener" class="rounded-full border-2 border-emerald-600 px-6 py-3 text-[15px] font-bold text-emerald-700 hover:bg-emerald-50">Tanya via WhatsApp</a>
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer', ['waLink' => $waLink, 'home' => route('landing')])
</body>
</html>
