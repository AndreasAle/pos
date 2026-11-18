{{-- Header halaman Panduan: putih, sama gaya dengan landing setelah di-scroll. --}}
<header x-data="{ open: false }" class="fixed inset-x-0 top-0 z-40 bg-white shadow-[0_2px_16px_rgba(15,23,42,.08)] pt-[env(safe-area-inset-top)]">
    <div class="max-w-[1200px] mx-auto px-5 sm:px-6 h-[60px] sm:h-[68px] lg:h-[76px] flex items-center">
        <a href="{{ route('landing') }}" class="flex items-center gap-2 shrink-0" aria-label="Yoka POS — Beranda">
            <img src="{{ asset('images/brand/yoka-logo.png') }}" alt="Yoka" class="h-7 w-[78px] sm:h-8 sm:w-[88px] lg:h-9 lg:w-[100px] object-contain object-left">
            <span class="text-[17px] sm:text-[19px] lg:text-[22px] leading-none font-bold tracking-tight text-emerald-700">POS</span>
        </a>

        <nav class="hidden lg:flex items-center gap-9 ml-12 text-[15px] font-bold text-slate-800">
            <a href="{{ route('landing') }}#keunggulan" class="hover:text-emerald-700 transition-colors">Fitur</a>
            <a href="{{ route('guides.index') }}" class="text-emerald-700" aria-current="page">Panduan</a>
            <a href="{{ route('landing') }}#harga" class="hover:text-emerald-700 transition-colors">Paket Harga</a>
            <a href="{{ route('landing') }}#faq" class="hover:text-emerald-700 transition-colors">FAQ</a>
        </nav>

        <div class="hidden lg:flex items-center gap-7 ml-auto">
            <a href="{{ url('/login') }}" class="text-[15px] font-bold text-slate-800 hover:text-emerald-700">Masuk</a>
            <a href="{{ route('register') }}" class="px-7 py-3 rounded-full text-[15px] font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors">Coba Gratis</a>
        </div>

        <button type="button" @click="open = !open" class="lg:hidden ml-auto -mr-2 w-11 h-11 flex items-center justify-center rounded-lg text-slate-800" aria-label="Menu">
            <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div x-show="open" x-cloak x-transition class="lg:hidden border-t border-slate-200 bg-white px-6 py-4 space-y-3">
        <a href="{{ route('landing') }}#keunggulan" class="block text-sm font-medium text-slate-700">Fitur</a>
        <a href="{{ route('guides.index') }}" class="block text-sm font-bold text-emerald-700">Panduan</a>
        <a href="{{ route('landing') }}#harga" class="block text-sm font-medium text-slate-700">Paket Harga</a>
        <a href="{{ route('landing') }}#faq" class="block text-sm font-medium text-slate-700">FAQ</a>
        <a href="{{ url('/login') }}" class="block text-sm font-medium text-slate-700">Masuk</a>
        <a href="{{ route('register') }}" class="block text-center px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold">Coba Gratis 7 Hari</a>
    </div>
</header>
